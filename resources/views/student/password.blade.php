@extends('student.layout')
@section('title', 'كلمة المرور')
@section('content')
<div class="page-head"><div><h1>{{ $student->must_change_password ? 'اختر كلمة مرور جديدة' : 'تغيير كلمة المرور' }}</h1>
    <div class="sub">{{ $student->name }} · رقم الهوية <span dir="ltr">{{ $student->national_id }}</span></div></div></div>
<div class="panel">
    <form class="form" method="post" action="{{ route('student.password.update') }}">
        @csrf @method('put')
        <div class="field"><label for="current_password">{{ $student->must_change_password ? 'كلمة المرور المؤقتة' : 'كلمة المرور الحالية' }}</label><input type="password" id="current_password" name="current_password" dir="ltr" required></div>
        <div class="field"><label for="password">كلمة المرور الجديدة</label><input type="password" id="password" name="password" dir="ltr" required><span class="hint">6 أحرف أو أرقام على الأقل. احفظها في مكان آمن.</span></div>
        <div class="field"><label for="password_confirmation">أعد كتابة كلمة المرور الجديدة</label><input type="password" id="password_confirmation" name="password_confirmation" dir="ltr" required></div>
        <div><button class="btn primary" type="submit">حفظ كلمة المرور</button></div>
    </form>
</div>
@endsection
