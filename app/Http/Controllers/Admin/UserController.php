<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** مستخدمو الإدارة: المدير العام والمديرون والموظفون بصلاحيات محددة */
class UserController extends Controller
{
    public function index()
    {
        $users = User::whereIn('role', ['admin', 'staff'])->orderByRaw("CASE role WHEN 'admin' THEN 0 ELSE 1 END")->orderBy('name')->get();

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        return view('admin.users.form', ['user' => new User(['role' => 'staff', 'is_active' => true, 'permissions' => []])]);
    }

    public function store(Request $request)
    {
        User::create($this->validated($request));

        return redirect()->route('admin.users.index')->with('status', 'تمت إضافة المستخدم بصلاحياته.');
    }

    public function edit(User $user)
    {
        abort_unless($user->isBackOffice(), 404);

        return view('admin.users.form', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        abort_unless($user->isBackOffice(), 404);
        $data = $this->validated($request, $user);
        if (empty($data['password'])) {
            unset($data['password']);
        }

        // حماية: لا يُلغى آخر مدير عام، ولا يوقف المستخدم حسابه بنفسه
        if ($user->isAdmin() && ($data['role'] !== 'admin' || ! $data['is_active']) && $this->isLastAdmin($user)) {
            return back()->with('warning', 'هذا آخر مدير عام في النظام، فلا يمكن تغيير دوره أو إيقافه.');
        }
        if ($user->is($request->user()) && ! $data['is_active']) {
            return back()->with('warning', 'لا يمكنك إيقاف حسابك بنفسك.');
        }

        $user->update($data);

        return redirect()->route('admin.users.index')->with('status', 'تم حفظ المستخدم وصلاحياته.');
    }

    public function destroy(Request $request, User $user)
    {
        abort_unless($user->isBackOffice(), 404);
        if ($user->is($request->user())) {
            return back()->with('warning', 'لا يمكنك حذف حسابك بنفسك.');
        }
        if ($user->isAdmin() && $this->isLastAdmin($user)) {
            return back()->with('warning', 'هذا آخر مدير عام في النظام، فلا يمكن حذفه.');
        }
        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'تم حذف المستخدم.');
    }

    private function isLastAdmin(User $user): bool
    {
        return User::where('role', 'admin')->where('is_active', true)->where('id', '!=', $user->id)->doesntExist();
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'job_title' => ['nullable', 'string', 'max:80'],
            'username' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('users')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(['admin', 'staff'])],
            'password' => [$user ? 'nullable' : 'required', Password::min(8)],
            'permissions' => ['nullable', 'array'],
        ], [], ['name' => 'الاسم', 'job_title' => 'المسمى الوظيفي', 'username' => 'اسم المستخدم', 'phone' => 'رقم الجوال', 'role' => 'نوع الحساب', 'password' => 'كلمة المرور']);

        $data['is_active'] = $request->boolean('is_active');
        $data['permissions'] = $data['role'] === 'admin' ? null : Permissions::clean($data['permissions'] ?? []);

        return $data;
    }
}
