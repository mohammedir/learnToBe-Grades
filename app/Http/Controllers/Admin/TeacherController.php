<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class TeacherController extends Controller
{
    public function index()
    {
        $teachers = User::teachers()->withCount('assignments')->orderBy('name')->get();

        return view('admin.teachers.index', compact('teachers'));
    }

    public function create()
    {
        return view('admin.teachers.form', ['teacher' => new User(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $teacher = User::create($data + ['role' => 'teacher']);

        return redirect()->route('admin.teachers.assignments', $teacher)
            ->with('status', 'تمت إضافة المدرس. اختر الآن الشعب والمواد التي يدرّسها.');
    }

    public function edit(User $teacher)
    {
        abort_unless($teacher->isTeacher(), 404);

        return view('admin.teachers.form', compact('teacher'));
    }

    public function update(Request $request, User $teacher)
    {
        abort_unless($teacher->isTeacher(), 404);
        $data = $this->validated($request, $teacher);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $teacher->update($data);

        return redirect()->route('admin.teachers.index')->with('status', 'تم حفظ بيانات المدرس.');
    }

    public function destroy(User $teacher)
    {
        abort_unless($teacher->isTeacher(), 404);
        $teacher->delete();

        return redirect()->route('admin.teachers.index')->with('status', 'تم حذف المدرس وتكليفاته. الدرجات التي رصدها بقيت محفوظة.');
    }

    private function validated(Request $request, ?User $teacher = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('users')->ignore($teacher?->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => [$teacher ? 'nullable' : 'required', Password::min(6)],
        ], [], ['name' => 'اسم المدرس', 'username' => 'اسم المستخدم', 'phone' => 'رقم الجوال', 'password' => 'كلمة المرور']);
        $data['is_active'] = $request->boolean('is_active');
        $data['show_phone_to_students'] = $request->boolean('show_phone_to_students');
        if (! empty($data['phone'])) {
            $phone = \App\Support\Phone::normalize($data['phone']);
            if (! $phone) {
                throw \Illuminate\Validation\ValidationException::withMessages(['phone' => 'رقم الجوال غير صحيح. اكتبه 10 أرقام يبدأ بـ 05، مثل 0599123456.']);
            }
            $data['phone'] = $phone;
        }

        return $data;
    }
}
