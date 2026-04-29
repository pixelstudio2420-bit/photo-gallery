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

    // Apply immediately (before DOM ready to prevent flash)
    applyTheme(getPreference());

    // Bind toggle buttons on DOM ready
    document.addEventListener('DOMContentLoaded', function () {
        // Re-apply to ensure icons are correct after DOM is parsed
        applyTheme(getPreference());

        // Attach click handlers
        document.querySelectorAll('.theme-toggle').forEach(btn => {
            btn.addEventListener('click', toggleTheme);
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
})();
