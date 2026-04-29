<?php

/*
|------------------------------------------------------------
| ADMIN SIDEBAR — canonical structure
|------------------------------------------------------------
|
| Schema for each item:
|   id        unique key — used by localStorage to remember
|             which collapse groups the admin had open
|   label     display text (Thai-only here per audit decision:
|             admin UI is single-language; bilingual stayed in
|             customer-facing menus)
|   icon      bootstrap-icons class
|   route     route name (must resolve via Laravel's route()),
|             OR null if this is a parent that only contains
|             children
|   permission  ability key checked via Gate / can() helper —
|               null means "no permission gate" (visible to
|               every authenticated admin)
|   feature   optional app_setting key that gates VISIBILITY
|             (e.g. 'subscription_system_enabled' for the
|             subscriptions sub-tree)
|   badge     optional callable (closure) returning an int —
|             rendered as a red/amber dot if > 0
|   children  array of items (recursive)
|
| Design principles (chosen after auditing the existing 11
| top-level + 6 settings-sub-group structure):
|
|   1. Group by USAGE FREQUENCY first, then by domain.
|      Daily-touch items (orders, slips, moderation) live
|      under "Operations" at the top; rare config lives in
|      "Settings" near the bottom.
|
|   2. Split DevOps from Settings. The previous "System &
|      Operations" sub-group mixed Deployment/Queue/Monitor
|      (DevOps) with Unit Economics/PDPA Export (business
|      metrics). They have different audiences (DevOps =
|      sysadmin; business = product owner). New structure
|      gives each its own top-level.
|
|   3. Marketing Hub split into "Customer-facing" (newsletters,
|      campaigns, landing) and "Tracking" (pixels, UTM,
|      funnel) so an admin running an A/B test isn't drowning
|      in 11 unrelated items.
|
|   4. Photographer-program management (commission tiers,
|      credits, subscriptions) lifted into its own top-level
|      "Programs". Previously buried under "Photographers".
|
|   5. Inbox = messages + notifications, separate from
|      Communications (which previously held them mixed with
|      everything else).
*/

return [

    // ── 1. Dashboard ─────────────────────────────────────────
    [
        'id'         => 'dashboard',
        'label'      => 'Dashboard',
        'icon'       => 'bi-grid-1x2-fill',
        'route'      => 'admin.dashboard',
        'permission' => 'dashboard',
    ],

    // ── 2. Operations ── daily queue ─────────────────────────
    [
        'id'    => 'operations',
        'label' => 'Operations',
        'icon'  => 'bi-lightning-charge',
        'children' => [
            [
                'id' => 'orders', 'label' => 'คำสั่งซื้อ',
                'icon' => 'bi-bag-check',
                'route' => 'admin.orders.index',
                'permission' => 'orders',
                'badge' => 'pendingOrders',
            ],
            [
                'id' => 'payment_slips', 'label' => 'ตรวจสอบสลิป',
                'icon' => 'bi-receipt-cutoff',
                'route' => 'admin.payments.slips',
                'permission' => 'payment_slips',
                'badge' => 'pendingSlips',
            ],
            [
                'id' => 'bookings', 'label' => 'คิวงาน Booking',
                'icon' => 'bi-calendar-check',
                'route' => 'admin.bookings.index',
                'permission' => 'bookings',
                'badge' => 'pendingBookings',
            ],
            [
                'id' => 'moderation', 'label' => 'ตรวจสอบภาพ AI',
                'icon' => 'bi-shield-exclamation',
                'route' => 'admin.moderation.index',
                'permission' => 'moderation',
                'badge' => 'flaggedPhotos',
            ],
            [
                'id' => 'reviews', 'label' => 'รีวิว',
                'icon' => 'bi-star',
                'route' => 'admin.reviews.index',
                'permission' => 'reviews',
            ],
            [
                'id' => 'invoices', 'label' => 'ใบเสร็จ',
                'icon' => 'bi-receipt',
                'route' => 'admin.invoices.index',
                'permission' => 'finance',
            ],
        ],
    ],

    // ── 3. Catalog ── content + commerce listings ───────────
    [
        'id'    => 'catalog',
        'label' => 'Catalog',
        'icon'  => 'bi-collection',
        'children' => [
            [
                'id' => 'events', 'label' => 'อีเวนต์',
                'icon' => 'bi-calendar-event',
                'route' => 'admin.events.index',
                'permission' => 'events',
                'badge' => 'draftEvents',
            ],
            [
                'id' => 'categories', 'label' => 'หมวดหมู่อีเวนต์',
                'icon' => 'bi-tags',
                'route' => 'admin.categories.index',
                'permission' => 'events',
            ],
            [
                'id' => 'products_group', 'label' => 'สินค้าดิจิทัล',
                'icon' => 'bi-box-seam',
                'permission' => 'products',
                'children' => [
                    ['id' => 'products_index', 'label' => 'จัดการสินค้า', 'icon' => 'bi-list', 'route' => 'admin.products.index', 'permission' => 'products'],
                    ['id' => 'products_create', 'label' => 'เพิ่มสินค้า', 'icon' => 'bi-plus-circle', 'route' => 'admin.products.create', 'permission' => 'products'],
                    ['id' => 'digital_orders', 'label' => 'คำสั่งซื้อดิจิทัล', 'icon' => 'bi-bag', 'route' => 'admin.digital-orders.index', 'permission' => 'products'],
                ],
            ],
            [
                'id' => 'blog', 'label' => 'บล็อก',
                'icon' => 'bi-journal-text',
                'permission' => 'blog',
                'children' => [
                    ['id' => 'blog_posts', 'label' => 'บทความ', 'icon' => 'bi-file-earmark-text', 'route' => 'admin.blog.posts.index', 'permission' => 'blog'],
                    ['id' => 'blog_categories', 'label' => 'หมวดหมู่', 'icon' => 'bi-tag', 'route' => 'admin.blog.categories.index', 'permission' => 'blog'],
                    ['id' => 'blog_tags', 'label' => 'แท็ก', 'icon' => 'bi-tags', 'route' => 'admin.blog.tags.index', 'permission' => 'blog'],
                    ['id' => 'blog_affiliate', 'label' => 'Affiliate Links', 'icon' => 'bi-link-45deg', 'route' => 'admin.blog.affiliate.index', 'permission' => 'blog'],
                    ['id' => 'blog_ai', 'label' => 'AI Tools', 'icon' => 'bi-robot', 'route' => 'admin.blog.ai.index', 'permission' => 'blog'],
                    ['id' => 'blog_news', 'label' => 'News Aggregator', 'icon' => 'bi-newspaper', 'route' => 'admin.blog.news.index', 'permission' => 'blog'],
                ],
            ],
            [
                'id' => 'pricing_group', 'label' => 'ราคา & แพ็คเกจ',
                'icon' => 'bi-currency-dollar',
                'permission' => 'pricing',
                'children' => [
                    ['id' => 'pricing', 'label' => 'ตั้งราคารูปภาพ', 'icon' => 'bi-cash-coin', 'route' => 'admin.pricing.index', 'permission' => 'pricing'],
                    ['id' => 'packages', 'label' => 'แพ็คเกจ', 'icon' => 'bi-box', 'route' => 'admin.packages.index', 'permission' => 'pricing'],
                ],
            ],
            [
                'id' => 'coupons', 'label' => 'คูปองส่วนลด',
                'icon' => 'bi-ticket-perforated',
                'route' => 'admin.coupons.index',
                'permission' => 'coupons',
            ],
            [
                'id' => 'gift_cards', 'label' => 'Gift Cards',
                'icon' => 'bi-gift',
                'route' => 'admin.gift-cards.index',
                'permission' => 'coupons',
            ],
        ],
    ],

    // ── 4. People ── customers + photographers ──────────────
    [
        'id'    => 'people',
        'label' => 'People',
        'icon'  => 'bi-people',
        'children' => [
            [
                'id' => 'users', 'label' => 'ลูกค้า',
                'icon' => 'bi-person-circle',
                'route' => 'admin.users.index',
                'permission' => 'users',
                'badge' => 'newUsers',
            ],
            [
                'id' => 'online_users', 'label' => 'ออนไลน์ตอนนี้',
                'icon' => 'bi-broadcast',
                'route' => 'admin.online-users',
                'permission' => 'users',
            ],
            [
                'id' => 'photographers', 'label' => 'ช่างภาพ',
                'icon' => 'bi-camera',
                'route' => 'admin.photographers.index',
                'permission' => 'photographers',
            ],
            [
                'id' => 'photographer_onboarding', 'label' => 'Onboarding',
                'icon' => 'bi-person-plus',
                'route' => 'admin.photographer-onboarding.index',
                'permission' => 'photographers',
            ],
            [
                'id' => 'cloud_storage', 'label' => 'Cloud Storage',
                'icon' => 'bi-cloud-arrow-up',
                'permission' => 'users',
                'children' => [
                    ['id' => 'cs_overview', 'label' => 'ภาพรวม', 'icon' => 'bi-pie-chart', 'route' => 'admin.user-storage.index', 'permission' => 'users'],
                    ['id' => 'cs_plans', 'label' => 'แผน', 'icon' => 'bi-grid', 'route' => 'admin.user-storage.plans.index', 'permission' => 'users'],
                    ['id' => 'cs_subscribers', 'label' => 'สมาชิก', 'icon' => 'bi-people-fill', 'route' => 'admin.user-storage.subscribers.index', 'permission' => 'users'],
                    ['id' => 'cs_files', 'label' => 'ไฟล์ผู้ใช้', 'icon' => 'bi-files', 'route' => 'admin.user-storage.files.index', 'permission' => 'users'],
                ],
            ],
        ],
    ],

    // ── 5. Programs ── photographer commission/credits/subs ─
    [
        'id'    => 'programs',
        'label' => 'Photographer Programs',
        'icon'  => 'bi-award',
        'children' => [
            [
                'id' => 'commission', 'label' => 'คอมมิชชั่น',
                'icon' => 'bi-percent',
                'permission' => 'photographers',
                'children' => [
                    ['id' => 'commission_dash', 'label' => 'แดชบอร์ด', 'icon' => 'bi-speedometer2', 'route' => 'admin.commission.index', 'permission' => 'photographers'],
                    ['id' => 'commission_tiers', 'label' => 'Tiers', 'icon' => 'bi-bar-chart', 'route' => 'admin.commission.tiers', 'permission' => 'photographers'],
                    ['id' => 'commission_bulk', 'label' => 'ปรับแบบกลุ่ม', 'icon' => 'bi-collection', 'route' => 'admin.commission.bulk', 'permission' => 'photographers'],
                    ['id' => 'commission_history', 'label' => 'ประวัติ', 'icon' => 'bi-clock-history', 'route' => 'admin.commission.history', 'permission' => 'photographers'],
                    ['id' => 'commission_settings', 'label' => 'ตั้งค่า', 'icon' => 'bi-gear', 'route' => 'admin.commission.settings', 'permission' => 'photographers'],
                ],
            ],
            [
                'id' => 'upload_credits', 'label' => 'Upload Credits',
                'icon' => 'bi-coin',
                'permission' => 'photographers',
                'feature' => 'credits_system_enabled',
                'children' => [
                    ['id' => 'credits_overview', 'label' => 'ภาพรวม', 'icon' => 'bi-pie-chart', 'route' => 'admin.credits.index', 'permission' => 'photographers'],
                    ['id' => 'credits_packages', 'label' => 'แพ็คเกจ', 'icon' => 'bi-box', 'route' => 'admin.credits.packages.index', 'permission' => 'photographers'],
                    ['id' => 'credits_balances', 'label' => 'ยอดช่างภาพ', 'icon' => 'bi-wallet2', 'route' => 'admin.credits.photographers.index', 'permission' => 'photographers'],
                ],
            ],
            [
                'id' => 'subscriptions', 'label' => 'Subscriptions',
                'icon' => 'bi-lightning',
                'permission' => 'photographers',
                'feature' => 'subscription_system_enabled',
                'children' => [
                    ['id' => 'subs_overview', 'label' => 'ภาพรวม', 'icon' => 'bi-pie-chart', 'route' => 'admin.subscriptions.index', 'permission' => 'photographers'],
                    ['id' => 'subs_plans', 'label' => 'แผน', 'icon' => 'bi-grid', 'route' => 'admin.subscriptions.plans', 'permission' => 'photographers'],
                    ['id' => 'subs_features', 'label' => 'Feature Flags', 'icon' => 'bi-toggles', 'route' => 'admin.features.index', 'permission' => 'photographers'],
                    ['id' => 'subs_invoices', 'label' => 'ใบเสร็จ', 'icon' => 'bi-receipt', 'route' => 'admin.subscriptions.invoices', 'permission' => 'photographers'],
                ],
            ],
        ],
    ],

    // ── 6. Marketing ── split into customer-facing vs tracking
    [
        'id'    => 'marketing',
        'label' => 'Marketing',
        'icon'  => 'bi-megaphone',
        'children' => [
            ['id' => 'marketing_hub', 'label' => 'Hub', 'icon' => 'bi-house-gear', 'route' => 'admin.marketing.index', 'permission' => 'marketing'],
            [
                'id' => 'marketing_outreach', 'label' => 'Customer-facing',
                'icon' => 'bi-send',
                'permission' => 'marketing',
                'children' => [
                    ['id' => 'mk_newsletter', 'label' => 'Newsletter', 'icon' => 'bi-envelope', 'route' => 'admin.marketing.subscribers', 'permission' => 'marketing'],
                    ['id' => 'mk_campaigns', 'label' => 'Email Campaigns', 'icon' => 'bi-send-fill', 'route' => 'admin.marketing.campaigns.index', 'permission' => 'marketing'],
                    ['id' => 'mk_landing', 'label' => 'Landing Pages', 'icon' => 'bi-window', 'route' => 'admin.marketing.landing.index', 'permission' => 'marketing'],
                    ['id' => 'mk_push', 'label' => 'Web Push', 'icon' => 'bi-bell', 'route' => 'admin.marketing.push.index', 'permission' => 'marketing'],
                    ['id' => 'mk_referral', 'label' => 'Referral', 'icon' => 'bi-share', 'route' => 'admin.marketing.referral', 'permission' => 'marketing'],
                    ['id' => 'mk_loyalty', 'label' => 'Loyalty', 'icon' => 'bi-heart', 'route' => 'admin.marketing.loyalty', 'permission' => 'marketing'],
                ],
            ],
            [
                'id' => 'marketing_tracking', 'label' => 'Tracking & Analytics',
                'icon' => 'bi-graph-up',
                'permission' => 'marketing',
                'children' => [
                    ['id' => 'mk_pixels', 'label' => 'Pixels', 'icon' => 'bi-eye', 'route' => 'admin.marketing.pixels', 'permission' => 'marketing'],
                    ['id' => 'mk_utm', 'label' => 'UTM Analytics', 'icon' => 'bi-bar-chart-line', 'route' => 'admin.marketing.analytics', 'permission' => 'marketing'],
                    ['id' => 'mk_funnel', 'label' => 'Funnel v2', 'icon' => 'bi-funnel', 'route' => 'admin.marketing.analytics-v2', 'permission' => 'marketing'],
                ],
            ],
            ['id' => 'mk_line', 'label' => 'LINE Marketing', 'icon' => 'bi-chat-dots', 'route' => 'admin.marketing.line', 'permission' => 'marketing'],
            ['id' => 'mk_seo', 'label' => 'SEO & Social', 'icon' => 'bi-search', 'route' => 'admin.marketing.seo', 'permission' => 'marketing'],
        ],
    ],

    // ── 7. Finance ──────────────────────────────────────────
    [
        'id'    => 'finance',
        'label' => 'Finance',
        'icon'  => 'bi-bank',
        'children' => [
            ['id' => 'fin_dashboard', 'label' => 'Dashboard', 'icon' => 'bi-speedometer2', 'route' => 'admin.finance.index', 'permission' => 'finance'],
            ['id' => 'fin_transactions', 'label' => 'Transactions', 'icon' => 'bi-list-ul', 'route' => 'admin.finance.transactions', 'permission' => 'finance'],
            ['id' => 'fin_refunds_manual', 'label' => 'คืนเงิน Manual', 'icon' => 'bi-arrow-counterclockwise', 'route' => 'admin.finance.refunds', 'permission' => 'finance'],
            ['id' => 'fin_refunds_requests', 'label' => 'คำขอคืนเงิน', 'icon' => 'bi-envelope-paper', 'route' => 'admin.refunds.index', 'permission' => 'finance'],
            ['id' => 'fin_reconciliation', 'label' => 'กระทบยอด', 'icon' => 'bi-check2-square', 'route' => 'admin.finance.reconciliation', 'permission' => 'finance'],
            ['id' => 'fin_reports', 'label' => 'Reports', 'icon' => 'bi-file-earmark-bar-graph', 'route' => 'admin.finance.reports', 'permission' => 'finance'],
            ['id' => 'fin_cost_profit', 'label' => 'Cost-Profit', 'icon' => 'bi-graph-up-arrow', 'route' => 'admin.finance.cost-analysis', 'permission' => 'finance'],
            ['id' => 'fin_plan_profit', 'label' => 'Plan Profit', 'icon' => 'bi-bullseye', 'route' => 'admin.finance.plan-profit', 'permission' => 'finance'],
            [
                'id' => 'fin_payments', 'label' => 'ช่องทางชำระเงิน',
                'icon' => 'bi-credit-card',
                'permission' => 'finance',
                'children' => [
                    ['id' => 'pay_methods', 'label' => 'วิธีชำระเงิน', 'icon' => 'bi-wallet', 'route' => 'admin.payments.methods', 'permission' => 'finance'],
                    ['id' => 'pay_banks', 'label' => 'บัญชีธนาคาร', 'icon' => 'bi-bank2', 'route' => 'admin.payments.banks', 'permission' => 'finance'],
                    ['id' => 'pay_payouts', 'label' => 'โอนเงินช่างภาพ', 'icon' => 'bi-arrow-up-right', 'route' => 'admin.payments.payouts', 'permission' => 'finance'],
                    ['id' => 'pay_auto', 'label' => 'Auto-Payout', 'icon' => 'bi-lightning-fill', 'route' => 'admin.payments.payouts.automation', 'permission' => 'finance'],
                ],
            ],
            [
                'id' => 'fin_tax', 'label' => 'ภาษี & ค่าใช้จ่าย',
                'icon' => 'bi-calculator',
                'permission' => 'finance',
                'children' => [
                    ['id' => 'tax_dash', 'label' => 'Dashboard', 'icon' => 'bi-speedometer2', 'route' => 'admin.tax.index', 'permission' => 'finance'],
                    ['id' => 'tax_costs', 'label' => 'Cost Analysis', 'icon' => 'bi-graph-down', 'route' => 'admin.tax.costs', 'permission' => 'finance'],
                    ['id' => 'biz_expenses', 'label' => 'ค่าใช้จ่ายธุรกิจ', 'icon' => 'bi-cash-stack', 'route' => 'admin.business-expenses.index', 'permission' => 'finance'],
                    ['id' => 'biz_calc', 'label' => 'Cost Calculator', 'icon' => 'bi-calculator-fill', 'route' => 'admin.business-expenses.calculator', 'permission' => 'finance'],
                ],
            ],
        ],
    ],

    // ── 8. Inbox ── messages + notifications ────────────────
    [
        'id'    => 'inbox',
        'label' => 'Inbox',
        'icon'  => 'bi-inbox',
        'children' => [
            [
                'id' => 'messages', 'label' => 'ข้อความติดต่อ',
                'icon' => 'bi-chat-left-text',
                'route' => 'admin.messages.index',
                'permission' => 'messages',
                'badge' => 'newMessages',
            ],
            [
                'id' => 'notifications', 'label' => 'การแจ้งเตือน',
                'icon' => 'bi-bell',
                'route' => 'admin.notifications.index',
                'permission' => 'messages',
            ],
        ],
    ],

    // ── 9. Infrastructure ── DevOps (split from Settings) ──
    [
        'id'    => 'infrastructure',
        'label' => 'Infrastructure',
        'icon'  => 'bi-hdd-stack',
        'permission' => 'settings',
        'children' => [
            ['id' => 'infra_deployment', 'label' => 'Deployment / VPS', 'icon' => 'bi-rocket-takeoff', 'route' => 'admin.deployment.index', 'permission' => 'settings'],
            ['id' => 'infra_monitor', 'label' => 'System Monitor', 'icon' => 'bi-activity', 'route' => 'admin.system.dashboard', 'permission' => 'settings'],
            ['id' => 'infra_capacity', 'label' => 'Capacity Planner', 'icon' => 'bi-speedometer', 'route' => 'admin.system.capacity', 'permission' => 'settings'],
            ['id' => 'infra_perf', 'label' => 'Performance', 'icon' => 'bi-graph-up', 'route' => 'admin.settings.performance', 'permission' => 'settings'],
            ['id' => 'infra_queue', 'label' => 'Queue / Sync', 'icon' => 'bi-arrow-repeat', 'route' => 'admin.settings.queue', 'permission' => 'settings'],
            ['id' => 'infra_scheduler', 'label' => 'Scheduler', 'icon' => 'bi-clock', 'route' => 'admin.scheduler.index', 'permission' => 'settings'],
            ['id' => 'infra_alerts', 'label' => 'Alert Rules', 'icon' => 'bi-bell-fill', 'route' => 'admin.alerts.index', 'permission' => 'settings'],
            ['id' => 'infra_event_health', 'label' => 'Event Health', 'icon' => 'bi-heart-pulse', 'route' => 'admin.event-health.index', 'permission' => 'settings'],
            ['id' => 'infra_readiness', 'label' => 'Production Readiness', 'icon' => 'bi-check2-circle', 'route' => 'admin.system.readiness', 'permission' => 'settings'],
            ['id' => 'infra_cache', 'label' => 'CDN Cache Purge', 'icon' => 'bi-arrow-counterclockwise', 'route' => 'admin.cache-purge.index', 'permission' => 'settings'],
        ],
    ],

    // ── 10. Settings ── feature config ─────────────────────
    [
        'id'    => 'settings',
        'label' => 'Settings',
        'icon'  => 'bi-gear',
        'permission' => 'settings',
        'children' => [
            [
                'id' => 'set_general', 'label' => 'ทั่วไป & แบรนด์',
                'icon' => 'bi-globe',
                'children' => [
                    ['id' => 'set_general_main', 'label' => 'ทั่วไป', 'icon' => 'bi-sliders', 'route' => 'admin.settings.general'],
                    ['id' => 'set_seo', 'label' => 'SEO', 'icon' => 'bi-search', 'route' => 'admin.settings.seo'],
                    ['id' => 'set_seo_analyzer', 'label' => 'SEO Analyzer', 'icon' => 'bi-bar-chart', 'route' => 'admin.settings.seo.analyzer'],
                    ['id' => 'set_language', 'label' => 'ภาษา', 'icon' => 'bi-translate', 'route' => 'admin.settings.language'],
                    ['id' => 'set_legal', 'label' => 'กฎหมาย & นโยบาย', 'icon' => 'bi-file-earmark-text', 'route' => 'admin.legal.index'],
                    ['id' => 'set_changelog', 'label' => 'Changelog', 'icon' => 'bi-list-ul', 'route' => 'admin.changelog.index'],
                    ['id' => 'set_manual', 'label' => 'คู่มือ', 'icon' => 'bi-book', 'route' => 'admin.manual'],
                    ['id' => 'set_version', 'label' => 'เวอร์ชัน', 'icon' => 'bi-tag', 'route' => 'admin.settings.version'],
                ],
            ],
            [
                'id' => 'set_security', 'label' => 'Security',
                'icon' => 'bi-shield-lock',
                'children' => [
                    ['id' => 'sec_main', 'label' => 'ตั้งค่าความปลอดภัย', 'icon' => 'bi-shield-check', 'route' => 'admin.settings.security'],
                    ['id' => 'sec_ai', 'label' => 'AI Security', 'icon' => 'bi-robot', 'route' => 'admin.security.dashboard'],
                    ['id' => 'sec_threats', 'label' => 'Threat Intelligence', 'icon' => 'bi-shield-exclamation', 'route' => 'admin.security.threat-intelligence.index'],
                    ['id' => 'sec_geo', 'label' => 'Geo Access', 'icon' => 'bi-globe2', 'route' => 'admin.security.geo-access.index'],
                    ['id' => 'sec_2fa', 'label' => '2FA', 'icon' => 'bi-key', 'route' => 'admin.settings.2fa'],
                    ['id' => 'sec_source', 'label' => 'Source Protection', 'icon' => 'bi-eye-slash', 'route' => 'admin.settings.source-protection'],
                    ['id' => 'sec_proxy', 'label' => 'Proxy Shield', 'icon' => 'bi-shield', 'route' => 'admin.settings.proxy-shield'],
                    ['id' => 'sec_cf', 'label' => 'Cloudflare', 'icon' => 'bi-cloud', 'route' => 'admin.settings.cloudflare'],
                    ['id' => 'sec_apikeys', 'label' => 'API Keys', 'icon' => 'bi-key-fill', 'route' => 'admin.api-keys.index'],
                    ['id' => 'sec_activity', 'label' => 'Activity Log', 'icon' => 'bi-list-check', 'route' => 'admin.activity-log'],
                    ['id' => 'sec_login_history', 'label' => 'Login History', 'icon' => 'bi-clock-history', 'route' => 'admin.login-history'],
                ],
            ],
            [
                'id' => 'set_media', 'label' => 'Images & AI',
                'icon' => 'bi-image',
                'children' => [
                    ['id' => 'media_watermark', 'label' => 'Watermark', 'icon' => 'bi-droplet', 'route' => 'admin.settings.watermark'],
                    ['id' => 'media_image', 'label' => 'ตั้งค่ารูปภาพ', 'icon' => 'bi-images', 'route' => 'admin.settings.image'],
                    ['id' => 'media_perf', 'label' => 'Upload Performance', 'icon' => 'bi-speedometer', 'route' => 'admin.settings.photo-performance'],
                    ['id' => 'media_moderation', 'label' => 'AI Moderation', 'icon' => 'bi-shield-check', 'route' => 'admin.settings.moderation'],
                    ['id' => 'media_face', 'label' => 'Face Search', 'icon' => 'bi-person-badge', 'route' => 'admin.settings.face-search'],
                    ['id' => 'media_face_usage', 'label' => 'Face Search Usage', 'icon' => 'bi-bar-chart', 'route' => 'admin.settings.face-search.usage'],
                    ['id' => 'media_quality', 'label' => 'Photo Quality', 'icon' => 'bi-stars', 'route' => 'admin.photo-quality.index'],
                    ['id' => 'media_retention', 'label' => 'Retention', 'icon' => 'bi-clock', 'route' => 'admin.settings.retention'],
                ],
            ],
            [
                'id' => 'set_storage', 'label' => 'Storage',
                'icon' => 'bi-hdd',
                'children' => [
                    ['id' => 'storage_main', 'label' => 'R2/S3/Drive', 'icon' => 'bi-cloud', 'route' => 'admin.settings.storage'],
                    ['id' => 'storage_drive', 'label' => 'Google Drive', 'icon' => 'bi-google', 'route' => 'admin.settings.google-drive'],
                    ['id' => 'storage_aws', 'label' => 'AWS', 'icon' => 'bi-amazon', 'route' => 'admin.settings.aws'],
                    ['id' => 'storage_quota', 'label' => 'Photographer Quota', 'icon' => 'bi-pie-chart', 'route' => 'admin.settings.photographer-storage'],
                    ['id' => 'storage_overview', 'label' => 'Overview', 'icon' => 'bi-bar-chart', 'route' => 'admin.storage'],
                    ['id' => 'storage_backup', 'label' => 'Backup', 'icon' => 'bi-archive', 'route' => 'admin.settings.backup'],
                ],
            ],
            [
                'id' => 'set_integrations', 'label' => 'Integrations',
                'icon' => 'bi-plug',
                'children' => [
                    ['id' => 'int_payments', 'label' => 'Payment Gateways', 'icon' => 'bi-credit-card', 'route' => 'admin.settings.payment-gateways'],
                    ['id' => 'int_line', 'label' => 'LINE', 'icon' => 'bi-chat-dots', 'route' => 'admin.settings.line'],
                    ['id' => 'int_line_richmenu', 'label' => 'LINE Rich Menu', 'icon' => 'bi-grid-3x3', 'route' => 'admin.settings.line-richmenu'],
                    ['id' => 'int_line_test', 'label' => 'LINE Test', 'icon' => 'bi-bug', 'route' => 'admin.settings.line-test'],
                    ['id' => 'int_social', 'label' => 'Social Login', 'icon' => 'bi-people', 'route' => 'admin.settings.social-auth'],
                    ['id' => 'int_webhooks', 'label' => 'Webhook Monitor', 'icon' => 'bi-broadcast', 'route' => 'admin.settings.webhooks'],
                    ['id' => 'int_delivery', 'label' => 'Photo Delivery', 'icon' => 'bi-truck', 'route' => 'admin.settings.delivery'],
                    ['id' => 'int_analytics', 'label' => 'Analytics', 'icon' => 'bi-graph-up', 'route' => 'admin.settings.analytics'],
                    ['id' => 'int_email_logs', 'label' => 'Email Logs', 'icon' => 'bi-envelope-paper', 'route' => 'admin.settings.email-logs'],
                ],
            ],
            [
                'id' => 'set_business', 'label' => 'Business Operations',
                'icon' => 'bi-briefcase',
                'children' => [
                    ['id' => 'biz_unit_econ', 'label' => 'Unit Economics / LTV', 'icon' => 'bi-graph-up-arrow', 'route' => 'admin.unit-economics.index'],
                    ['id' => 'biz_pdpa', 'label' => 'PDPA Data Export', 'icon' => 'bi-shield-fill-check', 'route' => 'admin.data-export.index'],
                    ['id' => 'biz_reset', 'label' => 'Reset Data', 'icon' => 'bi-arrow-clockwise', 'route' => 'admin.settings.reset'],
                ],
            ],
        ],
    ],

    // ── 11. Admins (super-admin only) ──────────────────────
    [
        'id'    => 'admins',
        'label' => 'Admin Accounts',
        'icon'  => 'bi-shield-check',
        'route' => 'admin.admins.index',
        'permission' => '_super_admin_only',
    ],

];
