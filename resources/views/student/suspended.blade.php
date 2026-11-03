@extends('student.layout')
@section('title', 'الحساب موقوف')
@section('content')
<div class="suspended-box">
    <div class="sus-icon" aria-hidden="true">⏸</div>
    <h1>حسابك في البوابة موقوف حالياً</h1>
    <p class="sub">{{ $student->name }} · منذ {{ $student->suspended_at->format('Y/m/d') }}</p>
    <div class="sus-reason">
        <span>سبب الإيقاف كما سجّلته إدارة المدرسة:</span>
        <p>{{ $student->suspension_reason }}</p>
    </div>
    <p class="sub">لإعادة تفعيل الحساب يرجى مراجعة إدارة المدرسة.</p>
    <form method="post" action="{{ route('student.logout') }}">@csrf<button class="btn" type="submit">تسجيل الخروج</button></form>
</div>
@endsection
