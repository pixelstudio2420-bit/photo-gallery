<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogAiTask;
use App\Models\BlogPost;
use App\Services\Blog\AiContentService;
use App\Services\Blog\AiToggleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BlogAiController extends Controller
{
    public function __construct(
        private AiContentService $aiService,
        private AiToggleService $toggles
    ) {}

    /* ================================================================
     *  INDEX -- AI tools dashboard
     * ================================================================ */
    public function index()
    {
        $stats = [
            'total_tasks'   => BlogAiTask::count(),
            'completed'     => BlogAiTask::where('status', 'completed')->count(),
            'failed'        => BlogAiTask::where('status', 'failed')->count(),
            'pending'       => BlogAiTask::where('status', 'pending')->count(),
            'total_cost'    => (float) BlogAiTask::sum('cost_usd'),
            'tokens_input'  => (int) BlogAiTask::sum('tokens_input'),
            'tokens_output' => (int) BlogAiTask::sum('tokens_output'),
        ];

        $recentTasks = BlogAiTask::with('admin')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $costByProvider = BlogAiTask::where('created_at', '>=', now()->subDays(30))
            ->select('provider', DB::raw('SUM(cost_usd) as total_cost'), DB::raw('COUNT(*) as task_count'))
            ->groupBy('provider')
            ->orderBy('total_cost', 'desc')
            ->get();

        // AI toggle status for dashboard
        $aiStatus = $this->toggles->statusMatrix();

        return view('admin.blog.ai.index', compact('stats', 'recentTasks', 'costByProvider', 'aiStatus'));
    }

    /* ================================================================
     *  TOGGLES -- AI enable/disable settings page
     * ================================================================ */
    public function toggles()
    {
        $matrix = $this->toggles->statusMatrix();
        return view('admin.blog.ai.toggles', compact('matrix'));
    }

    public function saveToggles(Request $request)
    {
        $data = $request->validate([
            'master'             => 'nullable|boolean',
            'tools'              => 'nullable|array',
            'tools.*'            => 'nullable|in:0,1',
            'providers'          => 'nullable|array',
            'providers.*'        => 'nullable|in:0,1',
            'default_provider'   => 'nullable|in:openai,claude,gemini',
            'default_model'      => 'nullable|string|max:100',
            'temperature'        => 'nullable|numeric|min:0|max:2',
        ]);

        $this->toggles->saveSettings($data);

        return back()->with('success', 'บันทึกการตั้งค่า AI เรียบร้อย');
    }

    /* ================================================================
     *  GENERATE ARTICLE
     * ================================================================ */
    public function generateArticle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'keyword'     => 'required|string|max:255',
            'word_count'  => 'nullable|integer|min:300|max:10000',
            'tone'        => 'nullable|string|in:formal,casual,professional,friendly',
            'language'    => 'nullable|string|in:th,en',
            'provider'    => 'nullable|string|in:openai,claude,gemini',
            'include_faq' => 'nullable|boolean',
            'include_toc' => 'nullable|boolean',
        ]);

        $adminId = Auth::guard('admin')->id();

        // Create task record
        $task = BlogAiTask::create([
            'type'       => 'article_generation',
            'title'      => "สร้างบทความ: {$validated['keyword']}",
            'prompt'     => $validated['keyword'],
            'input_data' => $validated,
            'provider'   => $validated['provider'] ?? 'openai',
            'status'     => 'processing',
            'admin_id'   => $adminId,
        ]);

        try {
            $startTime = microtime(true);

            // Switch provider if specified
            if (!empty($validated['provider'])) {
                $this->aiService->setProvider($validated['provider']);
            }

            $result = $this->aiService->generateArticle(
                keyword: $validated['keyword'],
                options: [
                    'word_count'  => $validated['word_count'] ?? 1500,
                    'tone'        => $validated['tone'] ?? 'professional',
                    'language'    => $validated['language'] ?? 'th',
                    'include_faq' => $request->boolean('include_faq', true),
                    'include_toc' => $request->boolean('include_toc', true),
                ],
            );

            $processingTime = (int) ((microtime(true) - $startTime) * 1000);

            $task->update([
                'status'             => 'completed',
                'output_data'        => $result['content'] ?? json_encode($result),
                'tokens_input'       => $result['tokens_input'] ?? 0,
                'tokens_output'      => $result['tokens_output'] ?? 0,
                'cost_usd'           => $result['cost'] ?? 0,
                'processing_time_ms' => $processingTime,
                'model'              => $result['model'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'data'    => $result,
                'task_id' => $task->id,
                'message' => 'สร้างบทความด้วย AI เรียบร้อยแล้ว',
            ]);
        } catch (\Exception $e) {
            $task->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'task_id' => $task->id,
                'message' => 'เกิดข้อผิดพลาดในการสร้างบทความ: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* ================================================================
     *  SUMMARIZE
     * ================================================================ */
    public function summarize(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required_without:url|string|min:100',
            'url'     => 'required_without:content|nullable|url',
        ]);

        $adminId = Auth::guard('admin')->id();

        $task = BlogAiTask::create([
            'type'       => 'summarize',
            'title'      => 'สรุปเนื้อหา',
            'prompt'     => $validated['content'] ?? $validated['url'],
            'input_data' => $validated,
            'status'     => 'processing',
            'admin_id'   => $adminId,
        ]);

        try {
            $startTime = microtime(true);

            $result = $this->aiService->summarizeContent(
                content: $validated['content'] ?? $validated['url'],
            );

            $processingTime = (int) ((microtime(true) - $startTime) * 1000);

            $task->update([
                'status'             => 'completed',
                'output_data'        => $result['content'] ?? json_encode($result),
                'tokens_input'       => $result['tokens_input'] ?? 0,
                'tokens_output'      => $result['tokens_output'] ?? 0,
                'cost_usd'           => $result['cost'] ?? 0,
                'processing_time_ms' => $processingTime,
                'provider'           => $result['provider'] ?? null,
                'model'              => $result['model'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'data'    => $result,
                'task_id' => $task->id,
                'message' => 'สรุปเนื้อหาเรียบร้อยแล้ว',
            ]);
        } catch (\Exception $e) {
            $task->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* ================================================================
     *  REWRITE
     * ================================================================ */
    public function rewrite(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|min:50',
            'style'   => 'nullable|string|in:formal,casual,simplified,expanded,seo_optimized',
        ]);

        $adminId = Auth::guard('admin')->id();

        $task = BlogAiTask::create([
            'type'       => 'rewrite',
            'title'      => 'เขียนเนื้อหาใหม่',
            'prompt'     => Str::limit($validated['content'], 255),
            'input_data' => ['style' => $validated['style'] ?? 'formal', 'content_length' => strlen($validated['content'])],
            'status'     => 'processing',
            'admin_id'   => $adminId,
        ]);

        try {
            $startTime = microtime(true);

            $result = $this->aiService->rewriteContent(
                content: $validated['content'],
                style: $validated['style'] ?? 'formal',
            );

            $processingTime = (int) ((microtime(true) - $startTime) * 1000);

            $task->update([
                'status'             => 'completed',
                'output_data'        => $result['content'] ?? json_encode($result),
                'tokens_input'       => $result['tokens_input'] ?? 0,
                'tokens_output'      => $result['tokens_output'] ?? 0,
                'cost_usd'           => $result['cost'] ?? 0,
                'processing_time_ms' => $processingTime,
                'provider'           => $result['provider'] ?? null,
                'model'              => $result['model'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'data'    => $result,
                'task_id' => $task->id,
                'message' => 'เขียนเนื้อหาใหม่เรียบร้อยแล้ว',
            ]);
        } catch (\Exception $e) {
            $task->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* ================================================================
     *  RESEARCH
     * ================================================================ */
    public function research(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'topic' => 'required|string|max:500',
        ]);

        $adminId = Auth::guard('admin')->id();

        $task = BlogAiTask::create([
            'type'       => 'research',
            'title'      => "ค้นคว้า: {$validated['topic']}",
            'prompt'     => $validated['topic'],
            'input_data' => $validated,
            'status'     => 'processing',
            'admin_id'   => $adminId,
        ]);

        try {
            $startTime = microtime(true);

            $result = $this->aiService->searchWeb(
                query: $validated['topic'],
            );

            $processingTime = (int) ((microtime(true) - $startTime) * 1000);

            $task->update([
                'status'             => 'completed',
                'output_data'        => is_array($result) ? json_encode($result, JSON_UNESCAPED_UNICODE) : $result,
                'tokens_input'       => $result['tokens_input'] ?? 0,
                'tokens_output'      => $result['tokens_output'] ?? 0,
                'cost_usd'           => $result['cost'] ?? 0,
                'processing_time_ms' => $processingTime,
                'provider'           => $result['provider'] ?? null,
                'model'              => $result['model'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'data'    => $result,
                'task_id' => $task->id,
                'message' => 'ค้นคว้าข้อมูลเรียบร้อยแล้ว',
            ]);
        } catch (\Exception $e) {
            $task->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* ================================================================
     *  KEYWORD SUGGEST
     * ================================================================ */
    public function keywordSuggest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'topic' => 'required|string|max:255',
        ]);

        try {
            $result = $this->aiService->suggestKeywords(
                topic: $validated['topic'],
            );

            return response()->json([
                'success' => true,
                'data'    => $result,
                'message' => 'แนะนำคีย์เวิร์ดเรียบร้อยแล้ว',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* ================================================================
     *  SEO ANALYZE
     * ================================================================ */
    public function seoAnalyze(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'post_id' => 'required_without:content|nullable|exists:blog_posts,id',
            'content' => 'required_without:post_id|nullable|string|min:100',
            'keyword' => 'nullable|string|max:255',
        ]);

        try {
            if (!empty($validated['post_id'])) {
                $post   = BlogPost::findOrFail($validated['post_id']);
                $result = $this->aiService->analyzeSeo(
                    content: $post->content,
                    keyword: $post->focus_keyword ?? $post->title,
                );
            } else {
                $result = $this->aiService->analyzeSeo(
                    content: $validated['content'],
                    keyword: $validated['keyword'] ?? '',
                );
            }

            return response()->json([
                'success' => true,
                'data'    => $result,
                'message' => 'วิเคราะห์ SEO เรียบร้อยแล้ว',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* ================================================================
     *  TASK HISTORY
     * ================================================================ */
    public function taskHistory(Request $request)
    {
        $tasks = BlogAiTask::with(['admin', 'post'])
            ->when($request->type, fn ($q, $v) => $q->where('type', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->provider, fn ($q, $v) => $q->where('provider', $v))
            ->when($request->date_from, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->date_to, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $taskTypes = BlogAiTask::select('type')->distinct()->orderBy('type')->pluck('type');
        $providers = BlogAiTask::select('provider')->distinct()->whereNotNull('provider')->orderBy('provider')->pluck('provider');

        return view('admin.blog.ai.task-history', compact('tasks', 'taskTypes', 'providers'));
    }

    /* ================================================================
     *  TASK SHOW
     * ================================================================ */
    public function taskShow($id)
    {
        $task = BlogAiTask::with(['admin', 'post'])->findOrFail($id);

        return view('admin.blog.ai.task-show', compact('task'));
    }

    /* ================================================================
     *  COST REPORT
     * ================================================================ */
    public function costReport(Request $request)
    {
        $dateFrom = $request->input('date_from', now()->subDays(30)->toDateString());
        $dateTo   = $request->input('date_to', now()->toDateString());

        // Cost by provider
        $costByProvider = BlogAiTask::where('status', 'completed')
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->select(
                'provider',
                DB::raw('COUNT(*) as task_count'),
                DB::raw('SUM(cost_usd) as total_cost'),
                DB::raw('SUM(tokens_input) as total_tokens_input'),
                DB::raw('SUM(tokens_output) as total_tokens_output'),
                DB::raw('AVG(processing_time_ms) as avg_processing_time'),
            )
            ->groupBy('provider')
            ->orderBy('total_cost', 'desc')
            ->get();

        // Cost by model
        $costByModel = BlogAiTask::where('status', 'completed')
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->select(
                'provider',
                'model',
                DB::raw('COUNT(*) as task_count'),
                DB::raw('SUM(cost_usd) as total_cost'),
                DB::raw('SUM(tokens_input) as total_tokens_input'),
                DB::raw('SUM(tokens_output) as total_tokens_output'),
            )
            ->groupBy('provider', 'model')
            ->orderBy('total_cost', 'desc')
            ->get();

        // Daily cost trend
        $dailyCost = BlogAiTask::where('status', 'completed')
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(cost_usd) as total_cost'),
                DB::raw('COUNT(*) as task_count'),
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Cost by task type
        $costByType = BlogAiTask::where('status', 'completed')
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->select(
                'type',
                DB::raw('COUNT(*) as task_count'),
                DB::raw('SUM(cost_usd) as total_cost'),
            )
            ->groupBy('type')
            ->orderBy('total_cost', 'desc')
            ->get();

        // Grand totals
        $totals = [
            'total_cost'          => $costByProvider->sum('total_cost'),
            'total_tasks'         => $costByProvider->sum('task_count'),
            'total_tokens_input'  => $costByProvider->sum('total_tokens_input'),
            'total_tokens_output' => $costByProvider->sum('total_tokens_output'),
        ];

        return view('admin.blog.ai.cost-report', compact(
            'costByProvider', 'costByModel', 'dailyCost', 'costByType',
            'totals', 'dateFrom', 'dateTo'
        ));
    }

    /* ================================================================
     *  PROCESS — unified dispatcher for AI tool card UI
     * ================================================================ */
    public function process(Request $request)
    {
        $request->validate(['tool' => 'required|string|in:generate,summarize,rewrite,research,keyword-suggest,seo-analyze']);

        $tool = $request->input('tool');

        // Check toggle — reject if tool is disabled
        try {
            $this->toggles->assertToolEnabled($tool);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
                'code'    => 'TOOL_DISABLED',
            ], 403);
        }

        // Check provider (if specified in request)
        if ($provider = $request->input('provider')) {
            try {
                $this->toggles->assertProviderUsable($provider);
            } catch (\RuntimeException $e) {
                return response()->json([
                    'success' => false,
                    'error'   => $e->getMessage(),
                    'code'    => 'PROVIDER_DISABLED',
                ], 403);
            }
        }

        try {
            return match ($tool) {
                'generate'        => $this->generateArticle($request),
                'summarize'       => $this->summarize($request),
                'rewrite'         => $this->rewrite($request),
                'research'        => $this->research($request),
                'keyword-suggest' => $this->keywordSuggest($request),
                'seo-analyze'     => $this->seoAnalyze($request),
                default           => response()->json(['success' => false, 'error' => 'Unknown tool'], 400),
            };
        } catch (\Throwable $e) {
            \Log::error("BlogAi process [{$tool}] failed: " . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /* ================================================================
     *  SETTINGS — save AI provider preferences
     * ================================================================ */
    public function saveSettings(Request $request)
    {
        $validated = $request->validate([
            'default_provider' => 'required|in:openai,claude,gemini',
            'default_model'    => 'nullable|string|max:100',
            'temperature'      => 'nullable|numeric|min:0|max:2',
        ]);

        \App\Models\AppSetting::set('blog_ai_default_provider', $validated['default_provider']);
        if (!empty($validated['default_model'])) {
            \App\Models\AppSetting::set('blog_ai_default_model', $validated['default_model']);
        }
        if (isset($validated['temperature'])) {
            \App\Models\AppSetting::set('blog_ai_temperature', (string) $validated['temperature']);
        }

        return back()->with('success', 'บันทึกการตั้งค่า AI เรียบร้อย');
    }
}
