export function initTelegramMiniApp() {
    if (typeof window === 'undefined' || !window.Telegram?.WebApp) {
        return;
    }

    const tg = window.Telegram.WebApp;
    try {
        tg.ready();
        tg.expand();
    } catch (e) {
        // Ignored in non-Telegram contexts
    }

    const isGuest = document.body.dataset.auth === 'false' 
        || window.location.pathname.startsWith('/login') 
        || window.location.pathname === '/';

    if (isGuest && tg.initData && tg.initData.length > 0) {
        fetch('/telegram/mini-app-auth', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ init_data: tg.initData })
        })
        .then(res => res.json())
        .then(data => {
            if (data.ok && data.redirect) {
                if (window.location.pathname !== '/dashboard') {
                    window.location.href = data.redirect;
                }
            }
        })
        .catch(err => {
            console.warn('Telegram Mini App auth error:', err);
        });
    }
}
