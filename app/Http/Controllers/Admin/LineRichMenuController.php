<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LineRichMenuService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Admin UI for managing the LINE OA Rich Menu.
 *
 * Surface:
 *   GET  /admin/settings/line/richmenu             — list + status + form
 *   POST /admin/settings/line/richmenu/deploy      — create + upload + setDefault
 *   POST /admin/settings/line/richmenu/set-default — set existing menu as default
 *   POST /admin/settings/line/richmenu/delete      — delete a rich menu
 *   POST /admin/settings/line/richmenu/clear       — clear default
 *
 * The "deploy" form is the happy path: admin uploads an image, we POST the menu
 * config + image to LINE, and set it as default for every follower in one go.
 */
class LineRichMenuController extends Controller
{
    public function __construct(private LineRichMenuService $service) {}

    public function index()
    {
        $configured = $this->service->isConfigured();
        $defaultId  = $configured ? $this->service->getDefaultId() : null;
        $listResult = $configured ? $this->service->list() : ['ok' => false, 'menus' => [], 'error' => 'token not set'];

        return view('admin.settings.line-richmenu', [
            'configured' => $configured,
            'defaultId'  => $defaultId,
            'menus'      => $listResult['menus'] ?? [],
            'listError'  => $listResult['ok'] ? null : ($listResult['error'] ?? null),
            'tokenHint'  => $configured ? null : 'ยังไม่ได้ตั้ง LINE Channel Access Token — ไปตั้งที่หน้า "ตั้งค่า LINE" ก่อน',
        ]);
    }

    /**
     * Deploy a new rich menu from an admin form post.
     *
     * Form fields:
     *   • image            — uploaded file (required, PNG/JPEG ≤1 MB, 2500×1686 ideal)
     *   • menu_name        — human-readable name (max 300 chars per LINE)
     *   • chat_bar_text    — text on the chat bar (max 14 chars)
     *   • url_events / url_orders / url_face / url_promo / url_help / url_contact
     *   • label_*          — corresponding labels (max 20 chars)
     *   • set_as_default   — checkbox (default true)
     */
    public function deploy(Request $request)
    {
        $validated = $request->validate([
            'image'         => ['required', 'image', 'mimes:png,jpg,jpeg', 'max:1024'], // KB
            'menu_name'     => ['nullable', 'string', 'max:300'],
            'chat_bar_text' => ['nullable', 'string', 'max:14'],
            'url_events'    => ['nullable', 'url', 'max:1000'],
            'url_orders'    => ['nullable', 'url', 'max:1000'],
            'url_face'      => ['nullable', 'url', 'max:1000'],
            'url_promo'     => ['nullable', 'url', 'max:1000'],
            'url_help'      => ['nullable', 'url', 'max:1000'],
            'url_contact'   => ['nullable', 'url', 'max:1000'],
            'label_events'  => ['nullable', 'string', 'max:20'],
            'label_orders'  => ['nullable', 'string', 'max:20'],
            'label_face'    => ['nullable', 'string', 'max:20'],
            'label_promo'   => ['nullable', 'string', 'max:20'],
            'label_help'    => ['nullable', 'string', 'max:20'],
            'label_contact' => ['nullable', 'string', 'max:20'],
            'set_as_default'=> ['nullable'],
        ]);

        if (!$this->service->isConfigured()) {
            return back()->with('error', 'ยังไม่ได้ตั้ง LINE Channel Access Token');
        }

        $config = $this->service->presetStorefrontConfig(
            urls: array_filter([
                'events'  => $validated['url_events']  ?? null,
                'orders'  => $validated['url_orders']  ?? null,
                'face'    => $validated['url_face']    ?? null,
                'promo'   => $validated['url_promo']   ?? null,
                'help'    => $validated['url_help']    ?? null,
                'contact' => $validated['url_contact'] ?? null,
            ]),
            labels: array_filter([
                'events'  => $validated['label_events']  ?? null,
                'orders'  => $validated['label_orders']  ?? null,
                'face'    => $validated['label_face']    ?? null,
                'promo'   => $validated['label_promo']   ?? null,
                'help'    => $validated['label_help']    ?? null,
                'contact' => $validated['label_contact'] ?? null,
            ]),
        );

        // Override defaults from form input
        if (!empty($validated['menu_name'])) {
            $config['name'] = $validated['menu_name'];
        }
        if (!empty($validated['chat_bar_text'])) {
            $config['chatBarText'] = $validated['chat_bar_text'];
        }

        // Persist the uploaded image to a temp path for the upload step.
        // We don't store it permanently — LINE keeps its own copy after upload.
        $upload = $request->file('image');
        $tmpPath = $upload->getRealPath();

        $setDefault = (bool) ($validated['set_as_default'] ?? true);
        $result = $this->service->deploy($config, $tmpPath, $setDefault);

        if (!$result['ok']) {
            Log::warning('linerichmenu.admin_deploy_failed', $result);
            return back()->withInput()->with('error', 'Deploy ไม่สำเร็จ: ' . ($result['error'] ?? 'unknown'));
        }

        $msg = $setDefault
            ? "Rich Menu deploy + set default สำเร็จ (id: {$result['id']})"
            : "Rich Menu สร้างสำเร็จ (id: {$result['id']}) — ยังไม่ได้ตั้งเป็น default";

        return redirect()->route('admin.settings.line-richmenu')->with('success', $msg);
    }

    public function setDefault(Request $request)
    {
        $validated = $request->validate([
            'id' => ['required', 'string', 'max:128'],
        ]);
        $result = $this->service->setDefault($validated['id']);

        if (!$result['ok']) {
            return back()->with('error', 'Set default ไม่สำเร็จ: ' . ($result['error'] ?? 'unknown'));
        }
        return back()->with('success', 'ตั้ง Rich Menu นี้เป็น default แล้ว');
    }

    public function clearDefault()
    {
        $result = $this->service->clearDefault();
        if (!$result['ok']) {
            return back()->with('error', 'Clear default ไม่สำเร็จ: ' . ($result['error'] ?? 'unknown'));
        }
        return back()->with('success', 'ลบการตั้ง default แล้ว — chat bar จะกลับเป็น "Tap me"');
    }

    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'id' => ['required', 'string', 'max:128'],
        ]);
        $result = $this->service->delete($validated['id']);

        if (!$result['ok']) {
            return back()->with('error', 'ลบไม่สำเร็จ: ' . ($result['error'] ?? 'unknown'));
        }
        return back()->with('success', 'ลบ Rich Menu สำเร็จ');
    }
}
