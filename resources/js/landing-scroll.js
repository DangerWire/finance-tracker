import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

export function initLandingScroll() {
    const container = document.getElementById('landing-viewport');
    const pinnedStage = document.getElementById('pinned-stage');
    if (!container || !pinnedStage) {
        return;
    }

    gsap.registerPlugin(ScrollTrigger);

    const slides = gsap.utils.toArray('.cyber-slide');
    if (slides.length === 0) {
        return;
    }

    const hudIndicators = gsap.utils.toArray('.hud-dot');
    const hudProgressBar = document.getElementById('hud-progress-bar');
    const hudStageCounter = document.getElementById('hud-stage-counter');
    const hudSectionName = document.getElementById('hud-section-name');

    const sectionTitles = [
        'OVERVIEW // PROTOCOL DEPLOYED',
        'AUTOMATION // RECURRING DISPATCH',
        'NEURAL CORE // GEMINI AI REASONING',
        'CAPITAL TARGETS // REAL-TIME BUDGETS',
        'SYSTEM READY // INITIALIZE ACCESS',
    ];

    // Initial setup: Slide 1 visible, other slides hidden and non-interactive
    gsap.set(slides[0], { opacity: 1, scale: 1, y: 0, visibility: 'visible', pointerEvents: 'auto' });
    slides.slice(1).forEach((slide) => {
        gsap.set(slide, { opacity: 0, scale: 1.06, y: 50, visibility: 'hidden', pointerEvents: 'none' });
    });

    // Master Timeline with single pinned ScrollTrigger and scrub damping
    const tl = gsap.timeline({
        scrollTrigger: {
            id: 'pinned-stage-trigger',
            trigger: pinnedStage,
            start: 'top top',
            end: '+=400%',
            pin: true,
            scrub: 0.4,
            anticipatePin: 1,
            invalidateOnRefresh: true,
            onUpdate: (self) => {
                const progress = self.progress;

                // Update vertical progress bar in right HUD
                if (hudProgressBar) {
                    hudProgressBar.style.height = `${Math.round(progress * 100)}%`;
                }

                // Determine active stage (0 to 4)
                const stageIndex = Math.min(Math.floor(progress * 5), 4);

                if (hudStageCounter) {
                    hudStageCounter.innerText = `0${stageIndex + 1} // 05`;
                }

                if (hudSectionName) {
                    hudSectionName.innerText = sectionTitles[stageIndex] || '';
                }

                // Update HUD indicator dots
                hudIndicators.forEach((dot, idx) => {
                    if (idx === stageIndex) {
                        dot.classList.add('bg-[#FFE500]', 'border-[#FFE500]', 'scale-125');
                        dot.classList.remove('bg-gray-800', 'border-gray-700');
                    } else {
                        dot.classList.remove('bg-[#FFE500]', 'border-[#FFE500]', 'scale-125');
                        dot.classList.add('bg-gray-800', 'border-gray-700');
                    }
                });
            },
        },
    });

    // -------------------------------------------------------------
    // TRANSITIONS TIMELINE
    // -------------------------------------------------------------

    // Top rest buffer: Keeps Slide 1 100% visible and stable at the top
    tl.to({}, { duration: 0.25 })

    // --- STEP 1: Slide 1 -> Slide 2 (Recurring Automation) ---
    .to(slides[0], {
        opacity: 0,
        scale: 0.94,
        y: -40,
        visibility: 'hidden',
        pointerEvents: 'none',
        duration: 1,
    })
    .to(slides[1], {
        opacity: 1,
        scale: 1,
        y: 0,
        visibility: 'visible',
        pointerEvents: 'auto',
        duration: 1,
    }, '<')
    .fromTo('.slide-2-card', {
        opacity: 0,
        y: 30,
    }, {
        opacity: 1,
        y: 0,
        stagger: 0.1,
        duration: 0.8,
    }, '-=0.5')

    // --- STEP 2: Slide 2 -> Slide 3 (Gemini Neural AI) ---
    .to(slides[1], {
        opacity: 0,
        scale: 0.94,
        y: -40,
        visibility: 'hidden',
        pointerEvents: 'none',
        duration: 1,
    })
    .to(slides[2], {
        opacity: 1,
        scale: 1,
        y: 0,
        visibility: 'visible',
        pointerEvents: 'auto',
        duration: 1,
    }, '<')
    .fromTo('.slide-3-item', {
        opacity: 0,
        x: -25,
    }, {
        opacity: 1,
        x: 0,
        stagger: 0.12,
        duration: 0.8,
    }, '-=0.5')

    // --- STEP 3: Slide 3 -> Slide 4 (Capital Bounds & Budgets) ---
    .to(slides[2], {
        opacity: 0,
        scale: 0.94,
        y: -40,
        visibility: 'hidden',
        pointerEvents: 'none',
        duration: 1,
    })
    .to(slides[3], {
        opacity: 1,
        scale: 1,
        y: 0,
        visibility: 'visible',
        pointerEvents: 'auto',
        duration: 1,
    }, '<')
    .fromTo('.slide-4-bar', {
        width: '0%',
    }, {
        width: (i, el) => el.getAttribute('data-width') || '85%',
        duration: 1.2,
        ease: 'power2.out',
    }, '-=0.4')

    // --- STEP 4: Slide 4 -> Slide 5 (Terminal Access CTA) ---
    .to(slides[3], {
        opacity: 0,
        scale: 0.94,
        y: -40,
        visibility: 'hidden',
        pointerEvents: 'none',
        duration: 1,
    })
    .to(slides[4], {
        opacity: 1,
        scale: 1,
        y: 0,
        visibility: 'visible',
        pointerEvents: 'auto',
        duration: 1,
    }, '<')
    .fromTo('.slide-5-box', {
        opacity: 0,
        y: 30,
        scale: 0.96,
    }, {
        opacity: 1,
        y: 0,
        scale: 1,
        stagger: 0.15,
        duration: 0.9,
    }, '-=0.5')
    // Bottom rest buffer
    .to({}, { duration: 0.25 });

    // Clickable HUD indicator navigation
    hudIndicators.forEach((dot, idx) => {
        dot.addEventListener('click', (e) => {
            e.preventDefault();
            const st = tl.scrollTrigger;
            if (!st) return;
            const target = st.start + (idx / 4) * (st.end - st.start);
            window.scrollTo({
                top: target,
                behavior: 'smooth',
            });
        });
    });

    // Quick scroll button in hero
    const scrollDownBtn = document.getElementById('hero-scroll-btn');
    if (scrollDownBtn) {
        scrollDownBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const st = tl.scrollTrigger;
            if (!st) return;
            const target = st.start + 0.25 * (st.end - st.start);
            window.scrollTo({
                top: target,
                behavior: 'smooth',
            });
        });
    }

    // Refresh ScrollTrigger once fonts and images load
    window.addEventListener('load', () => {
        ScrollTrigger.refresh();
    });
}
