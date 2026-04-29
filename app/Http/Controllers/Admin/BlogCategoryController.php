<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BlogCategoryController extends Controller
{
    /* ================================================================
     *  INDEX
     * ================================================================ */
    public function index(Request $request)
    {
        $categories = BlogCategory::with('parent')
            ->withCount('posts')
            ->when($request->search, fn ($q, $v) => $q->where('name', 'ilike', "%{$v}%"))
            ->when($request->parent_id, fn ($q, $v) => $q->where('parent_id', $v))
            ->when($request->has('parent_id') && $request->parent_id === '0', fn ($q) => $q->whereNull('parent_id'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $parentCategories = BlogCategory::root()->active()->orderBy('name')->get();

        return view('admin.blog.categories.index', compact('categories', 'parentCategories'));
    }

    /* ================================================================
     *  CREATE / STORE
     * ================================================================ */
    public function create()
    {
        $parentCategories = BlogCategory::root()->active()->orderBy('name')->get();

        return view('admin.blog.categories.create', compact('parentCategories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'slug'             => 'nullable|string|max:255|unique:blog_categories,slug',
            'description'      => 'nullable|string|max:2000',
            'meta_title'       => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'parent_id'        => 'nullable|exists:blog_categories,id',
            'icon'             => 'nullable|string|max:100',
            'color'            => 'nullable|string|max:20',
            'sort_order'       => 'nullable|integer|min:0',
            'is_active'        => 'nullable|boolean',
        ]);

        $validated['slug']       = !empty($validated['slug']) ? $validated['slug'] : Str::slug($validated['name']);
        $validated['is_active']  = $request->boolean('is_active', true);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        // Ensure slug uniqueness
        $validated['slug'] = $this->ensureUniqueSlug($validated['slug']);

        BlogCategory::create($validated);

        return redirect()
            ->route('admin.blog.categories.index')
            ->with('success', 'สร้างหมวดหมู่บทความเรียบร้อยแล้ว');
    }

    /* ================================================================
     *  EDIT / UPDATE
     * ================================================================ */
    public function edit($id)
    {
        $category         = BlogCategory::findOrFail($id);
        $parentCategories = BlogCategory::root()
            ->active()
            ->where('id', '!=', $id)
            ->orderBy('name')
            ->get();

        return view('admin.blog.categories.edit', compact('category', 'parentCategories'));
    }

    public function update(Request $request, $id)
    {
        $category = BlogCategory::findOrFail($id);

        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'slug'             => 'nullable|string|max:255|unique:blog_categories,slug,' . $category->id,
            'description'      => 'nullable|string|max:2000',
            'meta_title'       => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'parent_id'        => 'nullable|exists:blog_categories,id',
            'icon'             => 'nullable|string|max:100',
            'color'            => 'nullable|string|max:20',
            'sort_order'       => 'nullable|integer|min:0',
            'is_active'        => 'nullable|boolean',
        ]);

        // Prevent self-referencing parent
        if (isset($validated['parent_id']) && (int) $validated['parent_id'] === $category->id) {
            $validated['parent_id'] = null;
        }

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $validated['is_active'] = $request->boolean('is_active', true);

        $category->update($validated);

        return redirect()
            ->route('admin.blog.categories.index')
            ->with('success', 'อัพเดทหมวดหมู่บทความเรียบร้อยแล้ว');
    }

    /* ================================================================
     *  DESTROY
     * ================================================================ */
    public function destroy($id)
    {
        $category = BlogCategory::withCount('posts')->findOrFail($id);

        if ($category->posts_count > 0) {
            return redirect()
                ->route('admin.blog.categories.index')
                ->with('error', "ไม่สามารถลบหมวดหมู่นี้ได้ เนื่องจากมีบทความ {$category->posts_count} บทความอยู่ในหมวดหมู่นี้");
        }

        // Re-parent children to null
        BlogCategory::where('parent_id', $category->id)->update(['parent_id' => null]);

        $category->delete();

        return redirect()
            ->route('admin.blog.categories.index')
            ->with('success', 'ลบหมวดหมู่บทความเรียบร้อยแล้ว');
    }

    /* ================================================================
     *  TOGGLE ACTIVE
     * ================================================================ */
    public function toggleActive($id): JsonResponse
    {
        $category = BlogCategory::findOrFail($id);
        $category->is_active = !$category->is_active;
        $category->save();

        return response()->json([
            'success'   => true,
            'is_active' => $category->is_active,
            'message'   => $category->is_active ? 'เปิดใช้งานหมวดหมู่แล้ว' : 'ปิดใช้งานหมวดหมู่แล้ว',
        ]);
    }

    /* ================================================================
     *  PRIVATE HELPERS
     * ================================================================ */
    private function ensureUniqueSlug(string $slug, ?int $excludeId = null): string
    {
        $original = $slug;
        $count    = 1;

        while (BlogCategory::where('slug', $slug)->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))->exists()) {
            $slug = $original . '-' . $count++;
        }

        return $slug;
    }
}
