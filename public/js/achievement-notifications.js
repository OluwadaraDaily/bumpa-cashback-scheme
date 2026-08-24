const notificationStack = document.querySelector('#achievement-notifications');

if (notificationStack) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const displayedNotifications = new Set();
    const attempts = notificationStack.dataset.poll === 'true' ? 10 : 1;

    const dismiss = (notification) => {
        notification.classList.remove('is-visible');
        window.setTimeout(() => notification.remove(), 200);
    };

    const display = (notification) => {
        const card = document.createElement('article');
        card.className = 'achievement-notification';

        const icon = document.createElement('span');
        icon.className = 'achievement-notification-icon';
        icon.textContent = '✓';

        const copy = document.createElement('div');

        const title = document.createElement('strong');
        title.textContent = notification.title;

        const message = document.createElement('p');
        message.textContent = notification.message;

        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'achievement-notification-close';
        close.setAttribute('aria-label', 'Dismiss notification');
        close.textContent = '×';
        close.addEventListener('click', () => dismiss(card));

        copy.append(title, message);
        card.append(icon, copy, close);
        notificationStack.append(card);

        window.requestAnimationFrame(() => card.classList.add('is-visible'));
        window.setTimeout(() => dismiss(card), 10000);
    };

    const markAsRead = async (notificationId) => {
        const url = notificationStack.dataset.readUrl.replace(
            '__NOTIFICATION__',
            encodeURIComponent(notificationId),
        );

        await fetch(url, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
        });
    };

    const checkForNotifications = async () => {
        const response = await fetch(notificationStack.dataset.indexUrl, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            return;
        }

        const payload = await response.json();

        for (const notification of payload.data ?? []) {
            if (displayedNotifications.has(notification.id)) {
                continue;
            }

            displayedNotifications.add(notification.id);
            display(notification);

            try {
                await markAsRead(notification.id);
            } catch {
                // It remains unread and can be shown again on the next page load.
            }
        }
    };

    const poll = async () => {
        for (let attempt = 0; attempt < attempts; attempt += 1) {
            try {
                await checkForNotifications();
            } catch {
                // A later polling attempt can recover from a temporary failure.
            }

            if (attempt < attempts - 1) {
                await new Promise((resolve) => window.setTimeout(resolve, 1000));
            }
        }
    };

    poll();
}
