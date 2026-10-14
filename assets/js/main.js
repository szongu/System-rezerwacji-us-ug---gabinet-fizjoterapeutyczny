(function initTheme() {
    try {
        const saved = localStorage.getItem('gf-theme');
        if (saved === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    } catch (e) {
    }
})();

document.addEventListener('DOMContentLoaded', function () {
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    // Automatyczne ukrywanie komunikatów flash po kilku sekundach 
    document.querySelectorAll('.alert-success').forEach(function (alertEl) {
        setTimeout(function () {
            const alert = bootstrap.Alert.getOrCreateInstance(alertEl);
            alert.close();
        }, 6000);
    });

    // Cień pod nawigacją po przewinięciu strony (czysto wizualne).
    const navbar = document.querySelector('.navbar');
    if (navbar) {
        const updateNavbarShadow = function () {
            navbar.classList.toggle('navbar-scrolled', window.scrollY > 8);
        };
        updateNavbarShadow();
        window.addEventListener('scroll', updateNavbarShadow, { passive: true });
    }

    // Delikatne "wjeżdżanie" kart i wierszy przy wejściu na stronę / scrollu.
    if (!prefersReducedMotion && 'IntersectionObserver' in window) {
        const revealTargets = document.querySelectorAll(
            '.card-service, .list-group-item, main > .card, main > .row > .col-md-6, main > .row > .col-lg-4'
        );
        if (revealTargets.length) {
            const observer = new IntersectionObserver(function (entries, obs) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('reveal-visible');
                        obs.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1 });

            revealTargets.forEach(function (el, index) {
                el.classList.add('reveal');
                el.style.animationDelay = Math.min(index * 40, 240) + 'ms';
                observer.observe(el);
            });

            // Siatka bezpieczeństwa
            setTimeout(function () {
                document.querySelectorAll('.reveal:not(.reveal-visible)').forEach(function (el) {
                    el.classList.add('reveal-visible');
                });
            }, 1500);
        }
    } else {
        document.querySelectorAll(
            '.card-service, .list-group-item, main > .card, main > .row > .col-md-6, main > .row > .col-lg-4'
        ).forEach(function (el) {
            el.classList.add('reveal', 'reveal-visible');
        });
    }

    initThemeToggle();
});

function initThemeToggle() {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'theme-toggle';
    btn.setAttribute('aria-label', 'Przełącz jasny/ciemny motyw');
    btn.setAttribute('title', 'Przełącz jasny/ciemny motyw');

    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    btn.innerHTML = isDark ? '☀️' : '🌙';

    btn.addEventListener('click', function () {
        const nowDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const next = nowDark ? 'light' : 'dark';

        if (next === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        } else {
            document.documentElement.removeAttribute('data-theme');
        }
        btn.innerHTML = next === 'dark' ? '☀️' : '🌙';

        try {
            localStorage.setItem('gf-theme', next);
        } catch (e) {
        }
    });

    document.body.appendChild(btn);
}
