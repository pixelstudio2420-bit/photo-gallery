<?php

/*
|------------------------------------------------------------
| FOOTER — canonical structure (dedup target)
|------------------------------------------------------------
| The audit found 9 routes duplicated between navbar dropdown
| and the footer ("My Orders", "Profile", "Referrals", etc.).
| New rule: footer is for NAVIGATION CHEAT-SHEET items and
| EXTERNAL touchpoints. Anything that's already one click
| away from the user-dropdown does NOT get repeated here.
|
| What stays in the footer:
|   • Discovery (Events, Blog) — main entry points for SEO
|   • Help / Contact — last-resort CTAs
|   • Become Photographer — top-of-funnel for the supply side
|   • Legal (privacy, terms, refund) — required by law / trust
|   • Social outbound links — only place these live
|
| What was REMOVED (now lives in navbar dropdown only):
|   • My Account / My Orders / Referrals
|   • Photographer Dashboard / My Events
|   • Login / Register (covered by header CTAs)
|
| Schema:
|   columns:
|     - title         section title
|     - items         array of items (label + route OR url)
|     - condition     optional closure that returns bool
|                     (e.g. show only when guest, etc.)
*/

return [
    'columns' => [
        [
            'title' => 'See More',
            'items' => [
                ['label' => 'Home',   'route' => 'home'],
                ['label' => 'Events', 'route' => 'events.index'],
                ['label' => 'Blog',   'route' => 'blog.index'],
                ['label' => 'Help',   'route' => 'help'],
            ],
        ],
        [
            'title' => 'Become a Photographer',
            'items' => [
                // Single CTA — the full photographer hub lives in
                // the user-dropdown when authenticated.
                [
                    'label' => 'Register',
                    'route' => 'photographer.register',
                    // Show only to guests + non-photographer users.
                    // Approved photographers see their dashboard
                    // link in the user-dropdown instead.
                    'condition' => fn () => !\Illuminate\Support\Facades\Auth::user()?->photographerProfile,
                ],
                [
                    'label' => 'Login',
                    'route' => 'photographer.login',
                    'condition' => fn () => !\Illuminate\Support\Facades\Auth::check(),
                ],
            ],
        ],
        // 4th column ("Contact") is conditional on
        // app_settings.footer_contact_enabled — the existing
        // footer.blade.php already handles this; the config
        // captures only the menu shape, not the contact details.
    ],
];
