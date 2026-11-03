<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubjectController extends Controller
{
    public function index()
    {
        $subjects = Subject::withCount('assignments')->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.subjects.index', compact('subjects'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80', 'unique:subjects,name']], [], ['name' => 'اسم المادة']);
        Subject::create($data + ['sort_order' => (int) Subject::max('sort_order') + 1]);

        return back()->with('status', 'تمت إضافة المادة.');
    }

    public function update(Request $request, Subject $subject)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('subjects')->ignore($subject->id)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [], ['name' => 'اسم المادة']);
        $subject->update(array_filter($data, fn ($v) => $v !== null));

        return back()->with('status', 'تم حفظ المادة.');
    }

    public function destroy(Subject $subject)
    {
        if ($subject->assignments()->exists()) {
            return back()->with('warning', 'هذه المادة مكلّف بها مدرسون. ألغِ التكليفات أولاً.');
        }
        $subject->delete();

        return back()->with('status', 'تم حذف المادة.');
    }
}
