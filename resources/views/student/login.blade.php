<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>دخول الطلاب – تطبيق مدرسة تعلم لتكون</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;600;700&family=Noto+Kufi+Arabic:wght@700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=5">
    @include('partials.pwa-head')
</head>
<body>
<div class="login">
    <div class="box">
        <div class="logo">
            <img src="{{ asset('images/logo.png') }}" alt="شعار مدرسة تعلم لتكون">
            <h1>تطبيق مدرسة تعلم لتكون</h1>
            <span style="color:var(--muted);font-size:13px">LearnToBe · بوابة الطالب: الدرجات والتحصيل</span>
        </div>
        @if($errors->any())<div class="alert bad">{{ $errors->first() }}</div>@endif
        @if(\App\Support\PortalLock::closed())
            @php($lk = \App\Support\PortalLock::state())
            <div class="alert warn"><b>البوابة متوقفة مؤقتاً.</b>@if($lk['reason']) {{ $lk['reason'] }}@endif</div>
        @endif
        <form class="form" method="post" action="{{ route('student.login.attempt') }}">
            @csrf
            <div class="field">
                <label for="national_id">رقم هوية الطالب</label>
                <input type="text" id="national_id" name="national_id" value="{{ old('national_id') }}" inputmode="numeric" autocomplete="username" dir="ltr" required autofocus>
            </div>
            <div class="field">
                <label for="password">كلمة المرور</label>
                <input type="password" id="password" name="password" autocomplete="current-password" dir="ltr" required>
                <span class="hint">في أول دخول استخدم كلمة المرور المؤقتة التي أرسلتها المدرسة.</span>
            </div>
            <label class="check"><input type="checkbox" name="remember" value="1"> تذكّرني على هذا الجهاز</label>
            <button class="btn primary" type="submit" style="justify-content:center">دخول</button>
        </form>
        <p style="color:var(--muted);font-size:12.5px;margin:16px 0 0;text-align:center">نسيت كلمة المرور؟ تواصل مع إدارة المدرسة.<br><a href="{{ route('login') }}">دخول المدرسين والإدارة</a></p>
    </div>
</div>
@include('partials.install-prompt')
</body>
</html>
