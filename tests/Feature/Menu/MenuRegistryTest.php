<?php

namespace Tests\Feature\Menu;

use App\Services\Menu\MenuRegistry;
use Tests\TestCase;

/**
 * Locks down the menu registry's contracts.
 *
 * The most valuable test here is `test_admin_menu_has_no_dead_links`
 * — it walks every route reference in config/menus/admin.php and
 * asserts route() resolution succeeds. This is the regression guard
 * that catches "I renamed the controller method but forgot to update
 * the menu config" before users see a 404.
 */
class MenuRegistryTest extends TestCase
{
    private MenuRegistry $reg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reg = app(MenuRegistry::class);
    }

    public function test_admin_menu_loads(): void
    {
        $items = $this->reg->build('admin');
        $this->assertNotEmpty($items, 'admin menu must not be empty');

        // Top-level structure: should have ~11 sections per the design.
        $this->assertGreaterThanOrEqual(8, count($items),
            'admin menu top-level too thin');
    }

    public function test_admin_menu_has_no_dead_links(): void
    {
        $bad = $this->reg->deadLinks('admin');
        $this->assertSame([], $bad,
            'config/menus/admin.php references unknown routes: '
            . implode(', ', $bad));
    }

    public function test_footer_menu_has_no_dead_links(): void
    {
        $bad = $this->reg->deadLinks('footer');
        $this->assertSame([], $bad,
            'config/menus/footer.php references unknown routes: '
            . implode(', ', $bad));
    }

    public function test_permission_filter_drops_items_without_capability(): void
    {
        // canCallback returns false for everything → all gated items
        // must drop out. The registry should also drop empty parent
        // groups (parents with no surviving children).
        $items = $this->reg->build(
            'admin',
            canCallback: fn ($p) => false,
        );

        // The dashboard item has permission='dashboard'. With our
        // closure returning false, it should disappear.
        $byId = collect($items)->pluck('id')->all();
        $this->assertNotContains('dashboard', $byId,
            'dashboard must be filtered when can returns false');
    }

    public function test_feature_filter_drops_items_with_disabled_feature(): void
    {
        // Subscriptions sub-tree has feature='subscription_system_enabled'.
        // When that feature returns false the sub-tree must drop.
        $items = $this->reg->build(
            'admin',
            canCallback: fn ($p) => true,   // allow all permissions
            featureCheck: fn ($f) => false, // block all features
        );

        // Walk the tree to confirm 'subscriptions' isn't anywhere.
        $allIds = $this->collectIds($items);
        $this->assertNotContains('subscriptions', $allIds);
        $this->assertNotContains('upload_credits', $allIds);
    }

    public function test_footer_condition_callbacks_filter_correctly(): void
    {
        // No authenticated user → guests see register + login links.
        \Auth::guard()->logout();
        $items = $this->reg->build('footer');

        $becomePhotog = collect($items)
            ->firstWhere('title', 'Become a Photographer');
        $this->assertNotNull($becomePhotog);
        $labels = collect($becomePhotog['items'])->pluck('label')->all();
        $this->assertContains('Login', $labels,
            'guest must see Login in footer photographer column');
    }

    public function test_badge_values_attach_to_items(): void
    {
        $items = $this->reg->build(
            'admin',
            canCallback:  fn ($p) => true,
            featureCheck: fn ($f) => true,
            badges:       ['pendingOrders' => 47],
        );

        $orders = $this->findById($items, 'orders');
        $this->assertNotNull($orders);
        $this->assertSame(47, $orders['badge_value'] ?? null);
    }

    public function test_unknown_menu_returns_empty(): void
    {
        $this->assertSame([], $this->reg->build('does-not-exist'));
    }

    /* ─────────────────── helpers ─────────────────── */

    private function collectIds(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            if (!empty($item['id'])) $out[] = $item['id'];
            if (!empty($item['children'])) {
                $out = array_merge($out, $this->collectIds($item['children']));
            }
        }
        return $out;
    }

    private function findById(array $items, string $id): ?array
    {
        foreach ($items as $item) {
            if (($item['id'] ?? null) === $id) return $item;
            if (!empty($item['children'])) {
                $found = $this->findById($item['children'], $id);
                if ($found) return $found;
            }
        }
        return null;
    }
}
