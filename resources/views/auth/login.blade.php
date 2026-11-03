<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تسجيل الدخول – تطبيق مدرسة تعلم لتكون</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;600;700&family=Noto+Kufi+Arabic:wght@700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=4">
    @include('partials.pwa-head')
</head>
<body>
<div class="login">
    <div class="box">
        <div class="logo">
            <img src="{{ asset('images/logo.png') }}" alt="شعار مدرسة تعلم لتكون">
            <h1>تطبيق مدرسة تعلم لتكون</h1>
            <span class="sub" style="color:var(--muted);font-size:13px">LearnToBe · دخول المدرسين والإدارة</span>
        </div>
        @if($errors->any())<div class="alert bad">{{ $errors->first() }}</div>@endif
        <form class="form" method="post" action="{{ route('login.attempt') }}">
            @csrf
            <div class="field">
                <label for="username">اسم المستخدم</label>
                <input type="text" id="username" name="username" value="{{ old('username') }}" autocomplete="username" dir="ltr" required autofocus>
            </div>
            <div class="field">
                <label for="password">كلمة المرور</label>
                <input type="password" id="password" name="password" autocomplete="current-password" dir="ltr" required>
            </div>
            <label class="check"><input type="checkbox" name="remember" value="1"> تذكّرني على هذا الجهاز</label>
            <button class="btn primary" type="submit" style="justify-content:center">دخول</button>
        </form>
        <p style="color:var(--muted);font-size:12.5px;margin:16px 0 0;text-align:center">نسيت كلمة المرور؟ تواصل مع إدارة المدرسة لإعادة تعيينها.<br><a href="{{ route('student.login') }}">أنا طالب: دخول بوابة الطالب</a></p>
    </div>
</div>
@include('partials.install-prompt')
</body>
</html>
