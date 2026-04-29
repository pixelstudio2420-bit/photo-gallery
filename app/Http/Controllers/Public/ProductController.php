<?php
namespace App\Http\Controllers\Public;
use App\Http\Controllers\Controller;
use App\Models\DigitalProduct;
use App\Models\DigitalOrder;
use App\Models\UserNotification;
use App\Models\AdminNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\StorageManager;
use App\Services\ImageProcessorService;

class ProductController extends Controller
{
    public function index(Request $request) {
        $query = DigitalProduct::active();

        // Search
        if ($search = trim((string) $request->get('q'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('short_description', 'ilike', "%{$search}%")
                  ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        // Filter by product type
        $type = $request->get('type');
        if ($type && $type !== 'all') {
            $query->where('product_type', $type);
        }

        // Filter: on sale only
        if ($request->boolean('on_sale')) {
            $query->whereNotNull('sale_price');
        }

        // Sorting
        $sort = $request->get('sort', 'latest');
        switch ($sort) {
            case 'price_low':   $query->orderByRaw('COALESCE(sale_price, price) ASC'); break;
            case 'price_high':  $query->orderByRaw('COALESCE(sale_price, price) DESC'); break;
            case 'best_seller': $query->orderByDesc('total_sales'); break;
            case 'featured':    $query->orderByDesc('is_featured')->orderByDesc('created_at'); break;
            default:            $query->orderByDesc('created_at');
        }

        $products = $query->paginate(24)->withQueryString();

        // Featured products (always computed, for the hero strip)
        $featured = DigitalProduct::active()
            ->where('is_featured', true)
            ->orderByDesc('created_at')
            ->take(6)
            ->get();

        // Stats for hero
        $totalCount = DigitalProduct::active()->count();
        $onSaleCount = DigitalProduct::active()->whereNotNull('sale_price')->count();

        // Type pill counts
        $typeCounts = DigitalProduct::active()
            ->selectRaw('product_type, COUNT(*) as c')
            ->groupBy('product_type')
            ->pluck('c', 'product_type')
            ->toArray();

        // Human labels for product types
        $typeLabels = [
            'preset'   => ['label' => 'พรีเซ็ต',     'icon' => 'bi-sliders',   'color' => 'from-rose-500 to-pink-500'],
            'overlay'  => ['label' => 'โอเวอร์เลย์', 'icon' => 'bi-layers',    'color' => 'from-amber-500 to-orange-500'],
            'template' => ['label' => 'เทมเพลต',     'icon' => 'bi-grid-3x3',  'color' => 'from-emerald-500 to-teal-500'],
            'other'    => ['label' => 'อื่นๆ',         'icon' => 'bi-box-seam',  'color' => 'from-indigo-500 to-purple-500'],
        ];

        $seo = app(\App\Services\SeoService::class);
        $seo->title('สินค้าดิจิทัล')
            ->description('เลือกซื้อสินค้าดิจิทัลคุณภาพสูงจากช่างภาพมืออาชีพ')
            ->type('website')
            ->setBreadcrumbs([
                ['name' => 'หน้าแรก', 'url' => url('/')],
                ['name' => 'สินค้าดิจิทัล'],
            ]);

        // AJAX realtime search response
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'html'       => view('public.products._grid', compact('products', 'typeLabels'))->render(),
                'pagination' => view('public.products._pagination', compact('products'))->render(),
                'total'      => $products->total(),
                'showing'    => $products->count(),
            ]);
        }

        return view('public.products.index', compact(
            'products', 'featured', 'totalCount', 'onSaleCount',
            'typeCounts', 'typeLabels', 'type', 'sort', 'search'
        ));
    }

    public function show($slug) {
        $product = DigitalProduct::where('slug', $slug)->active()->firstOrFail();

        $seo = app(\App\Services\SeoService::class);
        $seo->title($product->name)
            ->description($product->description ?? $product->name)
            ->image($product->cover_image ? asset('storage/' . $product->cover_image) : '')
            ->setBreadcrumbs([
                ['name' => 'หน้าแรก', 'url' => url('/')],
                ['name' => 'สินค้าดิจิทัล', 'url' => route('products.index')],
                ['name' => $product->name],
            ]);

        return view('public.products.show', compact('product'));
    }

    /**
     * Create a digital order and redirect to payment page.
     */
    public function purchase(Request $request, $product)
    {
        $digitalProduct = DigitalProduct::findOrFail($product);

        $orderNumber = 'DIG-' . strtoupper(Str::random(8)) . '-' . date('Ymd');
        $amount      = $digitalProduct->current_price;

        $digitalOrder = DigitalOrder::create([
            'order_number'   => $orderNumber,
            'user_id'        => Auth::id(),
            'product_id'     => $digitalProduct->id,
            'amount'         => $amount,
            'payment_method' => 'pending',
            'status'         => 'pending_payment',
        ]);

        // ─── Notifications ───
        try {
            // User: order created
            UserNotification::notify(
                (int) Auth::id(),
                'digital_order',
                'สร้างคำสั่งซื้อสำเร็จ',
                "คำสั่งซื้อ {$orderNumber} ยอด ฿" . number_format($amount, 2) . " รอการชำระเงิน",
                "products/checkout/{$digitalOrder->id}"
            );

            // Admin: new digital order
            AdminNotification::notify(
                'digital_order',
                "คำสั่งซื้อดิจิทัลใหม่ {$orderNumber}",
                "สินค้า: {$digitalProduct->name} · ยอด ฿" . number_format($amount, 2),
                "admin/digital-orders",
                (string) $digitalOrder->id
            );
        } catch (\Throwable $e) {
            // Non-critical — log but don't block checkout
            \Log::warning('Digital order notification failed: ' . $e->getMessage());
        }

        return redirect()->route('products.checkout', $digitalOrder->id);
    }

    /**
     * Show payment/checkout page for a digital order.
     */
    public function checkout($orderId)
    {
        $order = DigitalOrder::where('id', $orderId)
            ->where('user_id', Auth::id())
            ->with('product')
            ->firstOrFail();

        if ($order->status !== 'pending_payment') {
            return redirect()->route('products.order', $order->id)
                ->with('info', 'คำสั่งซื้อนี้ได้ดำเนินการแล้ว');
        }

        $bankAccounts = DB::table('bank_accounts')->where('is_active', 1)->get();
        $paymentMethods = DB::table('payment_methods')->where('is_active', 1)->orderBy('sort_order')->get();

        return view('public.products.checkout', compact('order', 'bankAccounts', 'paymentMethods'));
    }

    /**
     * Upload payment slip for a digital order.
     */
    public function uploadSlip(Request $request, $orderId)
    {
        $request->validate([
            'slip_image'     => 'required|image|max:5120',
            'payment_method' => 'required|string|max:50',
        ]);

        $order = DigitalOrder::where('id', $orderId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if (!in_array($order->status, ['pending_payment'])) {
            return back()->with('error', 'คำสั่งซื้อนี้ไม่สามารถอัพโหลดสลิปได้');
        }

        // Compress + store slip to cloud. Slips are now scoped per-customer
        // (users/{user_id}/payment-slips/) instead of per-order so a user's
        // full slip history lives under one folder — auditing is trivial and
        // wiping an account cascades cleanly. Adaptive encoder targets ≤ 2MiB
        // so 6-10MiB phone uploads don't blow R2 bandwidth budgets.
        $storage  = app(StorageManager::class);
        $disk     = $storage->uploadDriver();
        $slipDir  = $storage->directoryFor('users', (int) Auth::id(), 'payment-slips');
        $slipPath = app(ImageProcessorService::class)
            ->processSlipUpload($request->file('slip_image'), $slipDir, $disk, 2 * 1024 * 1024);

        $order->update([
            'slip_image'     => $slipPath,
            'payment_method' => $request->payment_method,
            'status'         => 'pending_review',
        ]);

        // ─── Notifications ───
        try {
            // Admin: new slip to review
            AdminNotification::notify(
                'digital_slip',
                "สลิปชำระเงิน (ดิจิทัล) {$order->order_number}",
                "ยอด ฿" . number_format($order->amount, 2) . " · รอตรวจสอบ",
                "admin/digital-orders",
                (string) $order->id
            );

            // User: acknowledgement
            UserNotification::notify(
                (int) Auth::id(),
                'digital_slip_uploaded',
                'อัปโหลดสลิปสำเร็จ',
                "คำสั่งซื้อ {$order->order_number} กำลังรอแอดมินตรวจสอบ (ภายใน 24 ชม.)",
                "products/order/{$order->id}"
            );
        } catch (\Throwable $e) {
            \Log::warning('Digital slip notification failed: ' . $e->getMessage());
        }

        return redirect()->route('products.order', $order->id)
            ->with('success', 'อัพโหลดสลิปสำเร็จ กรุณารอการตรวจสอบจากแอดมิน');
    }

    /**
     * Show digital order status page.
     */
    public function order($orderId)
    {
        $order = DigitalOrder::where('id', $orderId)
            ->where('user_id', Auth::id())
            ->with('product')
            ->firstOrFail();

        return view('public.products.order', compact('order'));
    }

    /**
     * JSON endpoint for realtime polling of an order's status.
     */
    public function orderStatus($orderId)
    {
        $order = DigitalOrder::where('id', $orderId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $statusLabels = [
            'pending_payment' => 'รอการชำระเงิน',
            'pending_review'  => 'รอแอดมินตรวจสอบ',
            'paid'            => 'ชำระเงินแล้ว',
            'cancelled'       => 'ยกเลิก',
        ];

        return response()->json([
            'status'              => $order->status,
            'status_label'        => $statusLabels[$order->status] ?? $order->status,
            'download_token'      => $order->download_token,
            'downloads_remaining' => $order->downloads_remaining,
            'expires_at'          => $order->expires_at,
            'paid_at'             => $order->paid_at,
            'note'                => $order->note,
            'download_url'        => $order->download_token
                ? route('products.download', $order->download_token)
                : null,
        ]);
    }

    /**
     * List user's digital orders.
     */
    public function myOrders(Request $request)
    {
        $userId = Auth::id();
        $status = $request->get('status', 'all');

        $query = DigitalOrder::where('user_id', $userId)->with('product');
        if ($status !== 'all' && in_array($status, ['pending_payment', 'pending_review', 'paid', 'cancelled'], true)) {
            $query->where('status', $status);
        }
        $orders = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        // Aggregate stats across ALL user's orders (not just current page/filter).
        // Postgres requires single quotes for string literals; double quotes
        // are identifier delimiters (column/table names) and would error.
        $raw = DigitalOrder::where('user_id', $userId)
            ->selectRaw("COUNT(*)                                                                       as total_count,
                         COUNT(*) FILTER (WHERE status IN ('pending_payment','pending_review'))         as pending_count,
                         COUNT(*) FILTER (WHERE status = 'paid')                                        as paid_count,
                         COALESCE(SUM(amount) FILTER (WHERE status = 'paid'), 0)                        as total_spent")
            ->first();

        $stats = [
            'total'   => (int) ($raw->total_count ?? 0),
            'pending' => (int) ($raw->pending_count ?? 0),
            'paid'    => (int) ($raw->paid_count ?? 0),
            'revenue' => (float) ($raw->total_spent ?? 0),
        ];

        return view('public.products.my-orders', compact('orders', 'stats', 'status'));
    }

    /**
     * Download a purchased digital product.
     */
    public function download($token)
    {
        $order = DigitalOrder::where('download_token', $token)->firstOrFail();

        // Authorization: only the owner can download
        if (Auth::id() !== (int) $order->user_id) {
            abort(403, 'ไม่มีสิทธิ์ดาวน์โหลด');
        }

        // Must be paid
        if ($order->status !== 'paid') {
            abort(403, 'คำสั่งซื้อยังไม่ได้ชำระเงิน');
        }

        // Check expiry
        if ($order->expires_at && now()->isAfter($order->expires_at)) {
            abort(410, 'ลิงก์ดาวน์โหลดหมดอายุแล้ว');
        }

        // Check downloads remaining
        $remaining = $order->downloads_remaining ?? 0;
        if ($remaining <= 0) {
            abort(410, 'ถึงจำนวนดาวน์โหลดสูงสุดแล้ว');
        }

        $order->decrement('downloads_remaining');

        $product = $order->product;

        // Serve the file based on source type
        $fileSource = $product->file_source ?? 'drive';

        // Restore the download credit on any failure below
        $restoreCredit = function () use ($order) {
            $order->increment('downloads_remaining');
        };

        if ($fileSource === 'local' && $product->local_file) {
            $filePath = storage_path('app/digital-products/' . $product->local_file);
            if (file_exists($filePath)) {
                return response()->download($filePath);
            }
        }

        if (!empty($product->drive_file_id)) {
            return redirect("https://drive.google.com/uc?id={$product->drive_file_id}&export=download");
        }

        if (!empty($product->direct_url)) {
            return redirect($product->direct_url);
        }

        // Nothing configured — restore the credit and guide the user
        $restoreCredit();
        \Log::warning("Digital download: product #{$product->id} ({$product->name}) has no file configured for order #{$order->order_number}");

        return redirect()->route('products.order', $order->id)
            ->with('error', 'ไฟล์สินค้ายังไม่พร้อมดาวน์โหลด กรุณาติดต่อแอดมินเพื่อแจ้งให้ตั้งค่าไฟล์ของสินค้านี้');
    }
}
