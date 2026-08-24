# Bumpa Cashback Scheme

Laravel application for purchasing products, unlocking achievements and badges, and issuing ₦300 cashback for each unlocked badge.

## Main flow

1. An authenticated user creates an order.
2. Stock is checked and reduced inside a database transaction.
3. The completed order event is saved to a transactional outbox and placed on the queue.
4. Achievements are evaluated and unlocked once per user, firing `AchievementUnlocked`.
5. A queued listener stores an unread achievement notification for the user.
6. Badges are evaluated after each achievement evaluation.
7. A pending cashback is created for a newly unlocked badge.
8. A queued payment transfer is started when the user has an active payment account.
9. Paystack sends a webhook when the transfer finishes.
10. The webhook marks the cashback as `paid` or `failed`.

## Design choices

- Laravel is used as a modular monolith. This keeps deployment simple while allowing the code to be split into clear services.
- Domain actions communicate through events and queued listeners.
- MySQL stores application data. Redis stores queues and cache data.
- Laravel Sanctum provides bearer-token authentication.
- All money is stored as integer kobo. For example, ₦300 is stored as `30000`.
- Orders support multiple products and store the product name and price at the time of purchase.
- `Idempotency-Key` is required when creating an order to prevent duplicate orders on retries.
- Orders, achievements, badges, and cashbacks are committed with their events in a transactional outbox. Failed queue publishing is retried by the scheduler.
- Achievements and badges are seeded and evaluated using database records.
- Achievement notifications are stored in the database so users can see them after queued processing finishes.
- A badge requires all of its linked achievements.
- Payment accounts store only a provider recipient reference. Bank details are not stored by this application.
- A shared test recipient can be assigned automatically until a user replaces it with their own recipient reference.
- Activating or updating an account retries the user's pending or failed cashbacks.
- Cashback transfers are queued and remain `processing` until the provider webhook confirms the final result.
- The default payment provider is fake so local work cannot accidentally send real money.

## Requirements

- Docker Desktop with Docker Compose
- PHP 8.3 or newer and Composer if you want to run tests outside Docker

## Start the application with Docker

From the project root:

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

Open the application at `http://localhost:8000`.

The seeders add:

- 50 products with stock
- Purchase and spending achievements
- Starter, Loyal, and Premium badges
- A local demo user: `test@example.com` / `password`

The queue worker starts automatically with Docker Compose. It processes achievement, badge, cashback, and transfer jobs. The scheduler retries unpublished outbox events every minute.

Useful commands:

```bash
docker compose ps
docker compose logs -f worker
docker compose logs -f scheduler
docker compose logs -f web
docker compose exec app php artisan migrate:status
docker compose exec app php artisan outbox:relay
docker compose down
```

`docker compose down` stops the containers and keeps database volumes. To remove the database and Redis data as well, use `docker compose down -v`.

## Services and ports

| Service | Purpose | Host port |
| --- | --- | --- |
| `web` | Nginx and the Laravel HTTP application | `8000` |
| `mysql` | Application database | `3307` |
| `redis` | Queue and cache | `6380` |
| `worker` | Laravel queue worker | No HTTP port |
| `scheduler` | Retries unpublished outbox events | No HTTP port |

## Authentication

Create a user:

```bash
curl -X POST http://localhost:8000/signup \
  -H 'Content-Type: application/json' \
  -d '{
    "username": "ada-lovelace",
    "email": "ada@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'
```

Log in and copy the returned token:

```bash
curl -X POST http://localhost:8000/login \
  -H 'Content-Type: application/json' \
  -d '{
    "email": "ada@example.com",
    "password": "password123"
  }'
```

Use the token on protected endpoints:

```text
Authorization: Bearer YOUR_TOKEN
```

There is no `/api` prefix in this project.

## API endpoints

### Public endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `POST` | `/signup` | Create a user |
| `POST` | `/login` | Log in and receive a token |
| `GET` | `/products` | List products |
| `GET` | `/products/{id}` | View a product |
| `POST` | `/cashback/webhook` | Receive a signed Paystack webhook |

### Authenticated endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/me` | View the current user |
| `POST` | `/logout` | Revoke the current token |
| `GET` | `/orders` | List the current user's orders |
| `POST` | `/orders` | Create an order |
| `GET` | `/orders/{id}` | View one of the current user's orders |
| `GET` | `/users/{id}/achievements` | View achievement and badge progress |
| `GET` | `/cashbacks` | List the current user's cashbacks |
| `GET` | `/cashbacks/{id}` | View one cashback and its payment attempts |
| `GET` | `/payment-accounts` | List the current user's payment accounts |
| `PUT` | `/payment-accounts/paystack` | Save or replace a Paystack recipient reference |
| `DELETE` | `/payment-accounts/paystack` | Deactivate the Paystack account |

### Browser notification endpoints

These JSON endpoints use the authenticated browser session. The UI checks them after checkout and displays unread achievement notifications.

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/notifications` | List unread notifications |
| `PATCH` | `/notifications/{id}/read` | Mark a notification as read |

### Admin endpoint

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `POST` | `/products` | Create a product |

The admin endpoint requires an authenticated user with `is_admin=true`.

## Create an order

Orders require an `Idempotency-Key` header. Reuse the same key when retrying the same request.

```bash
curl -X POST http://localhost:8000/orders \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Content-Type: application/json' \
  -H 'Idempotency-Key: order-demo-001' \
  -d '{
    "items": [
      {"product_id": 1, "quantity": 2},
      {"product_id": 2, "quantity": 1}
    ]
  }'
```

Product prices and order totals are in kobo.

## Payment setup

The application expects a Paystack recipient reference, such as `RCP_...`:

```bash
curl -X PUT http://localhost:8000/payment-accounts/paystack \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{
    "recipient_reference": "RCP_example",
    "currency": "NGN"
  }'
```

The current application stores the recipient reference only. The recipient must already exist with the payment provider.

For local development, `PAYSTACK_DEFAULT_RECIPIENT` is assigned to new users automatically. Existing users receive it on their next login or when they earn cashback without an account. It never replaces an account the user has already saved. The placeholder in `.env.example` works with the fake provider; replace it with a recipient created in Paystack test mode when testing the real Paystack API.

When a user saves a replacement account, pending or previously failed cashback is attached to the active account and queued again.

### Paystack test mode

1. Get your test secret key from the Paystack dashboard. It starts with `sk_test_`.
2. Create a transfer recipient using Paystack's Nigerian transfer test account:

```text
Account name: Bumpa Cashback Test User
Bank: Zenith Bank
Bank code: 057
Account number: 0000000000
Currency: NGN
Recipient type: nuban
```

Send those details to `POST https://api.paystack.co/transferrecipient` using your test secret key. Copy the `data.recipient_code` value from the response. Recipient codes are tied to a Paystack integration, so each developer must create their own.

3. Add the test key and recipient code to `.env`:

```env
PAYMENT_PROVIDER=paystack
PAYSTACK_SECRET_KEY=sk_test_your_key
PAYSTACK_DEFAULT_RECIPIENT=RCP_your_test_recipient
PAYSTACK_BASE_URL=https://api.paystack.co
PAYSTACK_TIMEOUT=10
PAYSTACK_WEBHOOK_IPS=52.31.139.75,52.49.173.169,52.214.14.220
```

Never commit the test secret key. Recreate the application containers after changing `.env`:

```bash
docker compose up -d --force-recreate app worker
```

4. In the Paystack test dashboard, open **Settings → Preferences** and disable **Confirm transfers before sending**. This allows queued cashback transfers to run without manual OTP approval.

5. Configure a public webhook URL with Paystack:

```text
https://your-domain.example/cashback/webhook
```

The webhook verifies Paystack's signature, optionally checks the source IP, matches the transfer reference, and safely ignores duplicate events. A local `localhost` URL cannot receive Paystack webhooks; use a public development tunnel when testing real webhooks.

Paystack references:

- [Test payment details](https://paystack.com/docs/payments/test-payments/)
- [Transfer recipients](https://paystack.com/docs/transfers/creating-transfer-recipients/)
- [Webhooks](https://paystack.com/docs/payments/webhooks/)
- [Single transfers](https://paystack.com/docs/transfers/single-transfers/)

## Logs

Important flow points are logged without passwords, tokens, recipient details, or provider secrets.

```bash
docker compose exec app tail -f storage/logs/laravel.log
```

## Run tests

The test suite forces an in-memory SQLite database, synchronous queues, and the fake payment provider. It does not use the development MySQL database or Redis.

Run the tests inside Docker:

```bash
docker compose exec app php artisan test
```

Or run them from an environment with PHP, Composer, and the SQLite PHP extension:

```bash
composer install
php artisan test
```

Run the code-style check:

```bash
vendor/bin/pint --test
```

The tests cover authentication, product creation, stock handling, order idempotency, achievement and badge rules, cashback creation, payment transfers, webhook security, and payment account ownership.
