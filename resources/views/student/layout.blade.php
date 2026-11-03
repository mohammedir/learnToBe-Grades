<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'بوابة الطالب') – تطبيق مدرسة تعلم لتكون</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Noto+Kufi+Arabic:wght@600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=4">
    @include('partials.pwa-head')
</head>
<body>
@auth('student')
<header class="topbar">
    <div class="wrap">
        <a class="brand" href="{{ route('student.home') }}">
            <img src="{{ asset('images/logo.png') }}" alt="">
            <span><b>تطبيق مدرسة تعلم لتكون</b><small>LearnToBe · بوابة الطالب</small></span>
        </a>
        <div class="spacer"></div>
        <div class="userbox">
            <a href="{{ route('student.password') }}">كلمة المرور</a>
            <form method="post" action="{{ route('student.logout') }}">@csrf<button class="linkbtn" type="submit">خروج</button></form>
        </div>
    </div>
</header>
@endauth
<main>
    <div class="wrap">
        @if(session('status'))<div class="alert ok">{{ session('status') }}</div>@endif
        @if(session('warning'))<div class="alert warn">{{ session('warning') }}</div>@endif
        @if($errors->any())<div class="alert bad">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
        @yield('content')
    </div>
</main>
@stack('scripts')
@include('partials.install-prompt')
</body>
</html>
