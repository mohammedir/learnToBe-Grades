@extends('layouts.app')
@section('title', $user->exists ? 'تعديل مستخدم' : 'إضافة مستخدم')
@section('content')
@php($modules = \App\Support\Permissions::MODULES)
@php($role = old('role', $user->role))
<div class="page-head"><h1>{{ $user->exists ? 'تعديل: '.$user->name : 'إضافة مدير أو موظف' }}</h1></div>
<form method="post" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}">
    @csrf
    @if($user->exists) @method('put') @endif
    <div class="grid2">
        <div class="panel">
            <h2>بيانات الحساب</h2>
            <div class="form">
                <div class="field"><label for="name">الاسم</label><input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required></div>
                <div class="field"><label for="job_title">المسمى الوظيفي</label><input type="text" id="job_title" name="job_title" value="{{ old('job_title', $user->job_title) }}" placeholder="مثال: مدير المرحلة الأساسية، سكرتير، مرشد"></div>
                <div class="field"><label for="username">اسم المستخدم للدخول</label><input type="text" id="username" name="username" dir="ltr" value="{{ old('username', $user->username) }}" required></div>
                <div class="field"><label for="phone">رقم الجوال (اختياري)</label><input type="text" id="phone" name="phone" dir="ltr" value="{{ old('phone', $user->phone) }}"></div>
                <div class="field">
                    <label for="password">{{ $user->exists ? 'كلمة مرور جديدة (اتركها فارغة إن لم تتغير)' : 'كلمة المرور' }}</label>
                    <input type="text" id="password" name="password" dir="ltr" autocomplete="new-password" {{ $user->exists ? '' : 'required' }}>
                    <span class="hint">8 أحرف على الأقل.</span>
                </div>
                <label class="check"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))> الحساب مفعّل (يستطيع الدخول)</label>
            </div>
        </div>

        <div class="panel">
            <h2>نوع الحساب</h2>
            <label class="check" style="margin-bottom:8px"><input type="radio" name="role" value="staff" @checked($role !== 'admin') onchange="document.getElementById('perm-box').hidden=false"> <span><b>مدير أو موظف بصلاحيات محددة</b> · تختار له الأقسام بالأسفل</span></label>
            <label class="check"><input type="radio" name="role" value="admin" @checked($role === 'admin') onchange="document.getElementById('perm-box').hidden=true"> <span><b>مدير عام</b> · كل الصلاحيات، ومنها إدارة المستخدمين</span></label>
        </div>
    </div>

    <div class="panel" id="perm-box" @if($role === 'admin') hidden @endif>
        <div class="inline" style="margin-bottom:10px">
            <h2 style="margin:0">الصلاحيات</h2>
            <div class="spacer"></div>
            <span style="color:var(--muted);font-size:13px">تحديد سريع:</span>
            <button class="btn sm" type="button" data-all="none">بدون</button>
            <button class="btn sm" type="button" data-all="view">مشاهدة الكل</button>
            <button class="btn sm" type="button" data-all="edit">تعديل الكل</button>
        </div>
        <div class="scroll">
        <table>
            <thead><tr><th>القسم</th>@foreach(\App\Support\Permissions::LEVELS as $lv => $label)<th class="c">{{ $label }}</th>@endforeach</tr></thead>
            <tbody>
            @foreach($modules as $key => $m)
                @php($current = old('permissions.'.$key, $user->permissions[$key] ?? 'none'))
                <tr>
                    <td><b>{{ $m[0] }}</b><div style="font-size:12.5px;color:var(--muted)">مشاهدة: {{ $m[1] }} · تعديل: {{ $m[2] }}</div></td>
                    @foreach(\App\Support\Permissions::LEVELS as $lv => $label)
                        <td class="c"><input type="radio" name="permissions[{{ $key }}]" value="{{ $lv }}" @checked($current === $lv) aria-label="{{ $m[0] }}: {{ $label }}"></td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        <p style="color:var(--muted);font-size:13px;margin-bottom:0">إدارة المستخدمين والصلاحيات متاحة للمدير العام فقط. الموظف الذي يملك «تعديل الدرجات» يستطيع تصحيح أي كشف، ويُسجَّل اسمه على الدرجة التي غيّرها.</p>
    </div>

    <div class="inline">
        <button class="btn primary" type="submit">{{ $user->exists ? 'حفظ' : 'إضافة المستخدم' }}</button>
        <a class="btn" href="{{ route('admin.users.index') }}">إلغاء</a>
    </div>
</form>

@if($user->exists && ! $user->is(auth()->user()))
<div class="panel" style="margin-top:16px">
    <h2>حذف المستخدم</h2>
    <form method="post" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('حذف هذا المستخدم؟')">
        @csrf @method('delete')<button class="btn danger" type="submit">حذف</button>
    </form>
</div>
@endif
@endsection
@push('scripts')
<script>
document.querySelectorAll('[data-all]').forEach(b => b.addEventListener('click', () => {
    document.querySelectorAll('#perm-box input[type=radio][value="' + b.dataset.all + '"]').forEach(r => r.checked = true);
}));
</script>
@endpush
