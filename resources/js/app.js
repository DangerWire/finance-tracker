import Alpine from 'alpinejs';
import { initLandingScroll } from './landing-scroll.js';
import { initTelegramMiniApp } from './telegram-mini-app.js';

window.Alpine = Alpine;
Alpine.start();

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        initLandingScroll();
        initTelegramMiniApp();
    });
} else {
    initLandingScroll();
    initTelegramMiniApp();
}
