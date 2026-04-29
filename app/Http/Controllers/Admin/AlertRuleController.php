<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\AlertEvent;
use App\Models\AlertRule;
use App\Services\AlertEvaluatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AlertRuleController extends Controller
{
    public function __construct(private AlertEvaluatorService $evaluator) {}

    public function index()
    {
        $rules = AlertRule::orderByDesc('is_active')
            ->orderBy('severity')
            ->orderBy('name')
            ->get()
            ->map(function ($rule) {
                $rule->current_value = $this->evaluator->currentValue($rule->metric);
                $rule->would_trigger = $rule->current_value !== null && $rule->matches($rule->current_value);
                return $rule;
            });

        $metrics = AlertEvaluatorService::metrics();
        $channelOptions = AlertRule::channelOptions();
        $severities = AlertRule::severities();

        $stats = [
            'total'      => $rules->count(),
            'active'     => $rules->where('is_active', true)->count(),
            'firing'     => $rules->where('firing', true)->count(),
            'triggering' => $rules->where('would_trigger', true)->count(),
            'events_24h' => AlertEvent::where('triggered_at', '>=', now()->subDay())->count(),
        ];

        return view('admin.alerts.index', compact('rules', 'metrics', 'channelOptions', 'severities', 'stats'));
    }

    public function create()
    {
        $metrics        = AlertEvaluatorService::metrics();
        $operators      = AlertRule::operators();
        $severities     = AlertRule::severities();
        $channelOptions = AlertRule::channelOptions();

        return view('admin.alerts.create', compact('metrics', 'operators', 'severities', 'channelOptions'));
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request);
        AlertRule::create($data);

        return redirect()->route('admin.alerts.index')->with('success', 'สร้าง rule เรียบร้อย');
    }

    public function edit(AlertRule $rule)
    {
        $metrics        = AlertEvaluatorService::metrics();
        $operators      = AlertRule::operators();
        $severities     = AlertRule::severities();
        $channelOptions = AlertRule::channelOptions();

        return view('admin.alerts.edit', compact('rule', 'metrics', 'operators', 'severities', 'channelOptions'));
    }

    public function update(Request $request, AlertRule $rule)
    {
        $data = $this->validatePayload($request);
        $rule->update($data);

        return redirect()->route('admin.alerts.index')->with('success', 'อัพเดต rule เรียบร้อย');
    }

    public function destroy(AlertRule $rule)
    {
        $rule->delete();
        return back()->with('success', 'ลบ rule แล้ว');
    }

    public function toggle(AlertRule $rule)
    {
        $rule->is_active = !$rule->is_active;
        $rule->save();
        return back()->with('success', $rule->is_active ? 'เปิดใช้งานแล้ว' : 'ปิดใช้งานแล้ว');
    }

    public function test(AlertRule $rule)
    {
        try {
            $result = $this->evaluator->trigger($rule);
            $msg = 'ส่งทดสอบเรียบร้อย → ' . implode(', ', $result['channels_sent']);
            return back()->with('success', $msg);
        } catch (\Throwable $e) {
            Log::error('Alert test failed', ['rule' => $rule->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'ส่งทดสอบล้มเหลว: ' . $e->getMessage());
        }
    }

    public function runNow()
    {
        $result = $this->evaluator->run();
        return back()->with('success', sprintf(
            'ตรวจสอบแล้ว %d rules → triggered=%d, resolved=%d, cooldown=%d',
            $result['checked'],
            $result['triggered'],
            $result['resolved'] ?? 0,
            $result['skipped_cooldown']
        ));
    }

    /**
     * Manual "I've seen this, clear the firing flag" button.
     *
     * Useful when the admin has addressed the underlying issue but doesn't
     * want to wait for the next scheduler tick to sweep it. Also useful when
     * the metric is stuck (e.g. monitor unavailable) and we need to force
     * the state machine back to idle.
     */
    public function acknowledge(AlertRule $rule)
    {
        $rule->firing      = false;
        $rule->resolved_at = now();
        $rule->save();

        // Clear matching unread admin notifications from the bell dropdown
        try {
            AdminNotification::where('type', 'ilike', 'alert.%')
                ->where('ref_id', (string) $rule->id)
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('Alert acknowledge dismiss failed', ['rule' => $rule->id, 'err' => $e->getMessage()]);
        }

        // Audit trail entry
        AlertEvent::create([
            'rule_id'       => $rule->id,
            'triggered_at'  => now(),
            'value'         => (float) ($rule->last_value ?? 0),
            'severity'      => 'info',
            'channels_sent' => [],
            'note'          => 'Manually acknowledged by admin',
        ]);

        return back()->with('success', 'รับทราบแล้ว — เคลียร์ firing state + ล้างแจ้งเตือนในกระดิ่ง');
    }

    public function events(Request $request)
    {
        $query = AlertEvent::with('rule')
            ->orderByDesc('triggered_at');

        if ($ruleId = $request->integer('rule')) {
            $query->where('rule_id', $ruleId);
        }
        if ($sev = $request->string('severity')->toString()) {
            $query->where('severity', $sev);
        }

        $events = $query->paginate(30)->withQueryString();
        $rules  = AlertRule::orderBy('name')->get(['id', 'name']);
        $severities = AlertRule::severities();

        return view('admin.alerts.events', compact('events', 'rules', 'severities'));
    }

    protected function validatePayload(Request $request): array
    {
        $metricKeys   = array_keys(AlertEvaluatorService::metrics());
        $operatorKeys = array_keys(AlertRule::operators());
        $sevKeys      = array_keys(AlertRule::severities());
        $chanKeys     = array_keys(AlertRule::channelOptions());

        $data = $request->validate([
            'name'             => ['required', 'string', 'max:120'],
            'description'      => ['nullable', 'string', 'max:255'],
            'metric'           => ['required', 'string', 'in:' . implode(',', $metricKeys)],
            'operator'         => ['required', 'string', 'in:' . implode(',', $operatorKeys)],
            'threshold'        => ['required', 'numeric'],
            'channels'         => ['nullable', 'array'],
            'channels.*'       => ['string', 'in:' . implode(',', $chanKeys)],
            'severity'         => ['required', 'string', 'in:' . implode(',', $sevKeys)],
            'cooldown_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'is_active'        => ['nullable', 'boolean'],
        ]);

        $data['channels']  = $data['channels'] ?? ['admin'];
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return $data;
    }
}
