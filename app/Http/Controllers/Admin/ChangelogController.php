<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChangelogEntry;
use Illuminate\Http\Request;

class ChangelogController extends Controller
{
    public function index()
    {
        $entries = ChangelogEntry::orderByDesc('released_on')
            ->orderByDesc('id')
            ->paginate(30);

        return view('admin.changelog.index', compact('entries'));
    }

    public function create()
    {
        $types = ChangelogEntry::types();
        $audiences = ChangelogEntry::audiences();
        return view('admin.changelog.create', compact('types', 'audiences'));
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request);
        ChangelogEntry::create($data);
        return redirect()->route('admin.changelog.index')->with('success', 'เพิ่มรายการ changelog แล้ว');
    }

    public function edit(ChangelogEntry $changelog)
    {
        $types = ChangelogEntry::types();
        $audiences = ChangelogEntry::audiences();
        return view('admin.changelog.edit', ['entry' => $changelog, 'types' => $types, 'audiences' => $audiences]);
    }

    public function update(Request $request, ChangelogEntry $changelog)
    {
        $data = $this->validatePayload($request);
        $changelog->update($data);
        return redirect()->route('admin.changelog.index')->with('success', 'อัพเดตแล้ว');
    }

    public function destroy(ChangelogEntry $changelog)
    {
        $changelog->delete();
        return back()->with('success', 'ลบแล้ว');
    }

    public function togglePublish(ChangelogEntry $changelog)
    {
        $changelog->is_published = !$changelog->is_published;
        $changelog->save();
        return back()->with('success', $changelog->is_published ? 'เผยแพร่แล้ว' : 'ซ่อนแล้ว');
    }

    protected function validatePayload(Request $request): array
    {
        $types     = array_keys(ChangelogEntry::types());
        $audiences = array_keys(ChangelogEntry::audiences());

        $data = $request->validate([
            'version'      => ['required', 'string', 'max:30'],
            'released_on'  => ['required', 'date'],
            'title'        => ['required', 'string', 'max:200'],
            'type'         => ['required', 'string', 'in:' . implode(',', $types)],
            'body'         => ['nullable', 'string'],
            'audience'     => ['required', 'string', 'in:' . implode(',', $audiences)],
            'is_published' => ['nullable', 'boolean'],
        ]);
        $data['is_published'] = (bool) ($data['is_published'] ?? false);
        return $data;
    }
}
