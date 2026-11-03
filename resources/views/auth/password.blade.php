@extends('layouts.app')
@section('title', 'تغيير كلمة المرور')
@section('content')
<div class="page-head"><h1>تغيير كلمة المرور</h1></div>
<div class="panel">
    <form class="form" method="post" action="{{ route('password.update') }}">
        @csrf @method('put')
        <div class="field"><label for="current_password">كلمة المرور الحالية</label><input type="password" id="current_password" name="current_password" dir="ltr" required></div>
        <div class="field"><label for="password">كلمة المرور الجديدة</label><input type="password" id="password" name="password" dir="ltr" required><span class="hint">8 أحرف على الأقل.</span></div>
        <div class="field"><label for="password_confirmation">تأكيد كلمة المرور الجديدة</label><input type="password" id="password_confirmation" name="password_confirmation" dir="ltr" required></div>
        <div><button class="btn primary" type="submit">حفظ</button></div>
    </form>
</div>
@endsection
