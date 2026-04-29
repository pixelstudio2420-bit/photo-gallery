<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\EventCategory;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = EventCategory::query()
            ->when($request->q, fn($q, $s) => $q->where('name', 'ilike', "%{$s}%"))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.categories.index', compact('categories'));
    }
    public function create() { return view('admin.categories.create'); }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'   => 'required|string|max:255',
            'slug'   => 'nullable|string|max:255|unique:event_categories,slug',
            'icon'   => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive',
        ]);

        $validated['slug'] = !empty($validated['slug']) ? $validated['slug'] : Str::slug($validated['name']);
        $validated['status'] = $validated['status'] ?? 'active';

        $category = EventCategory::create($validated);

        ActivityLogger::admin(
            action: 'category.created',
            target: $category,
            description: "สร้างหมวดหมู่ {$category->name}",
            oldValues: null,
            newValues: $validated,
        );

        return redirect()->route('admin.categories.index')->with('success', 'สร้างหมวดหมู่สำเร็จ');
    }

    public function edit(EventCategory $category) { return view('admin.categories.edit', compact('category')); }

    public function update(Request $request, EventCategory $category)
    {
        $validated = $request->validate([
            'name'   => 'required|string|max:255',
            'slug'   => 'nullable|string|max:255|unique:event_categories,slug,' . $category->id,
            'icon'   => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $old = [
            'name'   => $category->name,
            'slug'   => $category->slug,
            'icon'   => $category->icon,
            'status' => $category->status,
        ];

        $category->update($validated);

        ActivityLogger::admin(
            action: 'category.updated',
            target: $category,
            description: "แก้ไขหมวดหมู่ {$category->name}",
            oldValues: $old,
            newValues: $validated,
        );

        return redirect()->route('admin.categories.index')->with('success', 'อัพเดทสำเร็จ');
    }

    public function destroy(EventCategory $category)
    {
        $snapshot = [
            'id'     => $category->id,
            'name'   => $category->name,
            'slug'   => $category->slug,
            'status' => $category->status,
        ];

        $category->delete();

        ActivityLogger::admin(
            action: 'category.deleted',
            target: ['EventCategory', (int) $snapshot['id']],
            description: "ลบหมวดหมู่ {$snapshot['name']}",
            oldValues: $snapshot,
            newValues: null,
        );

        return redirect()->route('admin.categories.index')->with('success', 'ลบสำเร็จ');
    }
}
