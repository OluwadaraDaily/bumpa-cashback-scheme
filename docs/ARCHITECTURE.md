# Architecture and user journey

This diagram follows a user from authentication and shopping through achievement, badge, cashback, and payment confirmation.

```mermaid
sequenceDiagram
    autonumber
    actor User
    participant Web as Nginx + PHP-FPM
    participant API as Laravel Controllers
    participant Auth as Sanctum Auth
    participant Order as OrderService
    participant DB as MySQL + Transactional Outbox
    participant Relay as OutboxRelay + Scheduler
    participant Queue as Redis Queue
    participant Worker as Laravel Queue Worker
    participant Achievement as AchievementEvaluator
    participant Badge as BadgeEvaluator
    participant Cashback as CashbackCreator
    participant Transfer as CashbackTransferService
    participant Paystack as Paystack API
    participant Webhook as Paystack Webhook Service

    User->>Web: Sign up or log in
    Web->>API: Authentication request
    API->>Auth: Create or verify user
    Auth->>DB: Store user and API token
    API-->>User: Return authenticated session or token

    User->>Web: Browse products
    Web->>API: GET /products
    API->>DB: Read products and available stock
    DB-->>User: Return product list

    User->>Web: Save Paystack recipient reference
    Web->>API: PUT /payment-accounts/paystack
    API->>DB: PaymentAccountService stores active account

    User->>Web: Purchase products with Idempotency-Key
    Web->>API: POST /orders
    API->>Order: Validate and create order
    Order->>DB: Lock stock, reduce quantity, save order and OrderCompleted event
    Order->>Relay: Try immediate event publishing
    Relay->>Queue: Queue OrderCompleted
    Note over DB,Relay: Scheduler retries any unpublished outbox event

    Queue->>Worker: Deliver OrderCompleted
    Worker->>Achievement: EvaluateAchievements listener
    Achievement->>DB: Count purchases and spend, then unlock achievements
    Achievement->>DB: Save AchievementUnlocked and AchievementsEvaluated events
    DB->>Relay: Publish pending reward events
    Relay->>Queue: Queue reward events

    Queue->>Worker: Deliver AchievementUnlocked
    Worker->>DB: StoreAchievementNotification saves one notification
    DB-->>User: UI can display the unlocked achievement

    Queue->>Worker: Deliver AchievementsEvaluated
    Worker->>Badge: EvaluateBadges listener
    Badge->>DB: Check all required achievements and unlock badges
    Badge->>DB: Save BadgeUnlocked event
    DB->>Relay: Publish BadgeUnlocked
    Relay->>Queue: Queue BadgeUnlocked

    Queue->>Worker: Deliver BadgeUnlocked
    Worker->>Cashback: CreateCashback listener
    Cashback->>DB: Create one pending ₦300 cashback and CashbackCreated event
    DB->>Relay: Publish CashbackCreated
    Relay->>Queue: Queue CashbackCreated

    Queue->>Worker: Deliver CashbackCreated
    Worker->>Transfer: TransferCashback listener
    Transfer->>DB: Create or reuse payment attempt and transfer reference
    Transfer->>Paystack: Initiate transfer using active payment account
    Paystack-->>Transfer: Accept transfer for processing
    Transfer->>DB: Keep cashback and attempt processing

    Paystack->>Web: Send signed transfer webhook
    Web->>Webhook: Verify signature and optional source IP
    Webhook->>DB: Match attempt and mark cashback paid, failed, or reversed
    DB-->>User: UI and API show the final cashback status
```

The outbox, database constraints, idempotent listeners, and reusable Paystack transfer reference make the flow safe to retry without creating duplicate rewards or payments.
