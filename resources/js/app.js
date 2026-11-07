import Alpine from 'alpinejs';
import { initLandingScroll } from './landing-scroll.js';

window.Alpine = Alpine;
Alpine.start();

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        initLandingScroll();
    });
} else {
    initLandingScroll();
}
