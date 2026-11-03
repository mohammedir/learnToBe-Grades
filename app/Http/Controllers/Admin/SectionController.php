<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GradeLevel;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SectionController extends Controller
{
    public function index()
    {
        $grades = GradeLevel::orderBy('sort_order')
            ->with(['sections' => fn ($q) => $q->withCount(['students', 'assignments'])])->get();

        return view('admin.sections.index', compact('grades'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'grade_level_id' => ['required', 'exists:grade_levels,id'],
            'name' => ['required', 'string', 'max:30',
                Rule::unique('sections')->where('grade_level_id', $request->grade_level_id)],
            'period' => ['nullable', Rule::in(['صباحي', 'مسائي'])],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
        ], ['name.unique' => 'هذه الشعبة موجودة في هذا الصف.'], ['name' => 'اسم الشعبة']);
        Section::create($data);

        return back()->with('status', 'تمت إضافة الشعبة. انقل إليها الطلاب من صفحة الطلاب.');
    }

    public function update(Request $request, Section $section)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:30',
                Rule::unique('sections')->where('grade_level_id', $section->grade_level_id)->ignore($section->id)],
            'period' => ['nullable', Rule::in(['صباحي', 'مسائي'])],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
        ], ['name.unique' => 'هذه الشعبة موجودة في هذا الصف.'], ['name' => 'اسم الشعبة']);
        $data['gender'] = $data['gender'] ?? null;
        if ($data['gender'] && $section->students()->where('gender', '!=', $data['gender'])->exists()) {
            return back()->with('warning', 'في الشعبة طلاب من الجنس الآخر. انقلهم أولاً ثم غيّر نوع الشعبة.');
        }
        $section->update($data);

        return back()->with('status', 'تم حفظ الشعبة.');
    }

    public function destroy(Section $section)
    {
        if ($section->students()->exists()) {
            return back()->with('warning', 'لا يمكن حذف شعبة فيها طلاب. انقل الطلاب أولاً.');
        }
        $section->delete();

        return back()->with('status', 'تم حذف الشعبة.');
    }

    public function storeGrade(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60', 'unique:grade_levels,name']], [], ['name' => 'اسم الصف']);
        $grade = GradeLevel::create($data + ['sort_order' => (int) GradeLevel::max('sort_order') + 1]);
        $grade->sections()->create(['name' => '1']);

        return back()->with('status', 'تمت إضافة الصف مع شعبة 1.');
    }
}
