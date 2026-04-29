<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BlogTagController extends Controller
{
    /* ================================================================
     *  INDEX
     * ================================================================ */
    public function index(Request $request)
    {
        $tags = BlogTag::query()
            ->withCount('posts')
            ->when($request->search, fn ($q, $v) => $q->where('name', 'ilike', "%{$v}%"))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.blog.tags.index', compact('tags'));
    }

    /* ================================================================
     *  STORE -- AJAX friendly
     * ================================================================ */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:blog_tags,name',
        ]);

        $slug = Str::slug($validated['name']);

        // Ensure slug uniqueness
        $originalSlug = $slug;
        $count        = 1;
        while (BlogTag::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count++;
        }

        $tag = BlogTag::create([
            'name' => $validated['name'],
            'slug' => $slug,
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'   => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ],
            'message' => 'สร้างแท็กเรียบร้อยแล้ว',
        ], 201);
    }

    /* ================================================================
     *  UPDATE
     * ================================================================ */
    public function update(Request $request, $id): JsonResponse
    {
        $tag = BlogTag::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:blog_tags,name,' . $tag->id,
        ]);

        $slug = Str::slug($validated['name']);

        // Ensure slug uniqueness excluding self
        $originalSlug = $slug;
        $count        = 1;
        while (BlogTag::where('slug', $slug)->where('id', '!=', $tag->id)->exists()) {
            $slug = $originalSlug . '-' . $count++;
        }

        $tag->update([
            'name' => $validated['name'],
            'slug' => $slug,
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'   => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ],
            'message' => 'อัพเดทแท็กเรียบร้อยแล้ว',
        ]);
    }

    /* ================================================================
     *  DESTROY
     * ================================================================ */
    public function destroy($id): JsonResponse
    {
        $tag = BlogTag::findOrFail($id);

        // Detach from all posts first
        $tag->posts()->detach();

        $tag->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบแท็กเรียบร้อยแล้ว',
        ]);
    }

    /* ================================================================
     *  SUGGEST / SEARCH -- autocomplete endpoint (shared method)
     * ================================================================ */
    public function suggest(Request $request): JsonResponse
    {
        $q = (string) $request->input('q', '');
        if ($q === '') {
            return response()->json(['success' => true, 'data' => []]);
        }

        $tags = BlogTag::where('name', 'ilike', '%' . $q . '%')
            ->orderBy('post_count', 'desc')
            ->limit(10)
            ->get(['id', 'name', 'slug', 'post_count']);

        return response()->json([
            'success' => true,
            'data'    => $tags,
        ]);
    }

    /* ================================================================
     *  BULK DELETE
     * ================================================================ */
    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'integer|exists:blog_tags,id',
        ]);

        $deleted = 0;
        foreach (BlogTag::whereIn('id', $request->ids)->get() as $tag) {
            $tag->posts()->detach();
            $tag->delete();
            $deleted++;
        }

        return response()->json([
            'success' => true,
            'message' => "ลบ {$deleted} แท็กเรียบร้อย",
            'deleted' => $deleted,
        ]);
    }
}
