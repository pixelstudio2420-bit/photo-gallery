/**
 * Dark Mode Toggle — Global
 * Uses localStorage + data-bs-theme attribute (Bootstrap 5.3+)
 * Respects system preference on first visit
 */
(function () {
    'use strict';

    const STORAGE_KEY = 'theme-preference';

    // Get stored or system preference
    function getPreference() {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored) return stored;
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    // Apply theme to <html>
    function applyTheme(theme) {
        // Smooth transition
        document.documentElement.classList.add('theme-transition');
        document.documentElement.setAttribute('data-bs-theme', theme);
        // Also toggle Tailwind .dark class for utility `dark:` variants
        if (theme === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
        setTimeout(() => document.documentElement.classList.remove('theme-transition'), 350);

        // Update all toggle button icons
        document.querySelectorAll('.theme-toggle').forEach(btn => {
            const icon = btn.querySelector('i');
            if (icon) {
                icon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
            }
            btn.setAttribute('title', theme === 'dark' ? 'สลับเป็นโหมดกลางวัน' : 'สลับเป็นโหมดกลางคืน');
        });
    }

    // Toggle between light and dark
    function toggleTheme() {
        const current = document.documentElement.getAttribute('data-bs-theme') || 'light';
        const next = current === 'dark' ? 'light' : 'dark';
        localStorage.setItem(STORAGE_KEY, next);
        applyTheme(next);
    }

    // Set a specific theme — used by segmented controls where each
    // option is a SET action (not a TOGGLE). Re-clicking the active
    // option is a no-op.
    function setTheme(theme) {
        if (theme !== 'light' && theme !== 'dark') return;
        localStorage.setItem(STORAGE_KEY, theme);
        applyTheme(theme);
    }

    // Apply immediately (before DOM ready to prevent flash)
    applyTheme(getPreference());

    // Bind toggle buttons on DOM ready
    document.addEventListener('DOMContentLoaded', function () {
        // Re-apply to ensure icons are correct after DOM is parsed
        applyTheme(getPreference());

        // Toggle (single-button) variant: clicking flips current ↔ other.
        document.querySelectorAll('.theme-toggle').forEach(btn => {
            btn.addEventListener('click', toggleTheme);
        });

        // Set (segmented-control) variant: data-theme-set="light"|"dark"
        // on each option button. Active state is purely CSS-driven via
        // Tailwind's `dark:` variants reacting to the .dark class on
        // <html>, so no DOM updates needed beyond the applyTheme call.
        document.querySelectorAll('[data-theme-set]').forEach(btn => {
            btn.addEventListener('click', () => {
                const t = btn.getAttribute('data-theme-set');
                if (t === 'light' || t === 'dark') setTheme(t);
            });
        });
    });

    // Listen for system preference changes
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (e) {
        if (!localStorage.getItem(STORAGE_KEY)) {
            applyTheme(e.matches ? 'dark' : 'light');
        }
    });

    // Expose globally
    window.toggleTheme = toggleTheme;
    window.setTheme    = setTheme;
})();
