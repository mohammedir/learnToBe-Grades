@extends('student.layout')
@section('title', 'البوابة متوقفة مؤقتاً')
@section('content')
<div class="suspended-box">
    <div class="sus-icon lock-icon" aria-hidden="true">🔒</div>
    <h1>بوابة الطالب متوقفة مؤقتاً</h1>
    <p class="sub">أوقفت إدارة المدرسة البوابة لجميع الطلاب@if($lock['until']) حتى {{ \Illuminate\Support\Carbon::parse($lock['until'])->format('Y/m/d') }}@endif.</p>
    @if($lock['reason'])
    <div class="sus-reason lock-reason">
        <span>السبب:</span>
        <p>{{ $lock['reason'] }}</p>
    </div>
    @endif
    <p class="sub">بياناتك ودرجاتك محفوظة، وستعود البوابة للعمل فور إعادة تفعيلها.</p>
    <form method="post" action="{{ route('student.logout') }}">@csrf<button class="btn" type="submit">تسجيل الخروج</button></form>
</div>
@endsection
