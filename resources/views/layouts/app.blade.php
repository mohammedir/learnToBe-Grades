<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'الرئيسية') – تطبيق مدرسة تعلم لتكون</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Noto+Kufi+Arabic:wght@600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=13">
    @include('partials.pwa-head')
</head>
<body>
@auth
<header class="topbar">
    <div class="wrap">
        <a class="brand" href="{{ route('home') }}">
            <img src="{{ asset('images/logo.png') }}" alt="">
            <span><b>تطبيق مدرسة تعلم لتكون</b><small>LearnToBe · {{ auth()->user()->isSupplier() ? 'بوابة المطبعة' : 'لوحة المدرسين والإدارة' }}</small></span>
        </a>
        <nav class="nav">
            @if(auth()->user()->isBackOffice())
                @perm('marks')<a href="{{ route('admin.dashboard') }}" @class(['active' => request()->routeIs('admin.dashboard', 'admin.marks.*')])>متابعة الرصد</a><a href="{{ route('admin.analytics') }}" @class(['active' => request()->routeIs('admin.analytics')])>الرسوم البيانية</a>@endperm
                @perm('marks', 'edit')<a href="{{ route('admin.entry.index') }}" @class(['active' => request()->routeIs('admin.entry.*')])>رصد الدرجات</a>@endperm
                @perm('approvals')<a href="{{ route('admin.approvals.index') }}" @class(['active' => request()->routeIs('admin.approvals.*')])>اعتماد الدرجات</a>@endperm
                @perm('teachers')<a href="{{ route('admin.teachers.index') }}" @class(['active' => request()->routeIs('admin.teachers.*')])>المدرسون</a>@endperm
                @perm('sections')<a href="{{ route('admin.sections.index') }}" @class(['active' => request()->routeIs('admin.sections.*')])>الصفوف والشعب</a>@endperm
                @perm('students')<a href="{{ route('admin.students.index') }}" @class(['active' => request()->routeIs('admin.students.*')])>الطلاب</a>@endperm
                @perm('subjects')<a href="{{ route('admin.subjects.index') }}" @class(['active' => request()->routeIs('admin.subjects.*')])>المواد</a>@endperm
                @perm('quizzes')<a href="{{ route('admin.quizzes.index') }}" @class(['active' => request()->routeIs('admin.quizzes.*')])>الاختبارات</a>@endperm
                @perm('portal')<a href="{{ route('admin.portal.index') }}" @class(['active' => request()->routeIs('admin.portal.*')])>بوابة الطلاب</a>@endperm
                @perm('components')<a href="{{ route('admin.components.index') }}" @class(['active' => request()->routeIs('admin.components.*')])>عناصر الدرجة</a>@endperm
                @perm('attendance')<a href="{{ route('admin.attendance.index') }}" @class(['active' => request()->routeIs('admin.attendance.*')])>حضور المدرسين</a>@endperm
                @perm('finance')<a href="{{ route('admin.finance.index') }}" @class(['active' => request()->routeIs('admin.finance.*')])>المالية</a>@endperm
                @if(in_array(auth()->user()->role, ['staff', 'admin']))<a href="{{ route('attendance.scan') }}" @class(['active' => request()->routeIs('attendance.scan')])>📍 حضوري</a>@endif
                @if(auth()->user()->isAdmin())<a href="{{ route('admin.users.index') }}" @class(['active' => request()->routeIs('admin.users.*')])>المستخدمون والصلاحيات</a>@endif
            @elseif(auth()->user()->isSupplier())
                <a href="{{ route('supplier.home') }}" @class(['active' => request()->routeIs('supplier.home', 'supplier.orders.*')])>الطلبات</a>
                <a href="{{ route('supplier.statement') }}" @class(['active' => request()->routeIs('supplier.statement')])>كشف الحساب</a>
            @else
                <a href="{{ route('teacher.home') }}" @class(['active' => request()->routeIs('teacher.home', 'teacher.sheet', 'teacher.notes')])>كشوفي</a>
                <a href="{{ route('teacher.analytics') }}" @class(['active' => request()->routeIs('teacher.analytics')])>الرسوم البيانية</a>
                <a href="{{ route('teacher.quizzes.index') }}" @class(['active' => request()->routeIs('teacher.quizzes.*')])>الاختبارات</a>
                <a href="{{ route('attendance.scan') }}" @class(['active' => request()->routeIs('attendance.scan')])>📍 حضوري</a>
            @endif
        </nav>
        <div class="userbox">
            <span>{{ auth()->user()->name }} <small style="opacity:.75">· {{ auth()->user()->role_label }}</small></span>
            <a href="{{ route('password.edit') }}">كلمة المرور</a>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="linkbtn" type="submit">خروج</button></form>
        </div>
    </div>
</header>
@endauth
<main>
    <div class="wrap">
        @if(session('status'))<div class="alert ok">{{ session('status') }}</div>@endif
        @if(session('warning'))<div class="alert warn">{{ session('warning') }}</div>@endif
        @if($errors->any())
            <div class="alert bad">تحقق من الحقول التالية:<ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif
        @hasSection('module')
            @unless(auth()->user()->hasPermission(trim($__env->yieldContent('module')), 'edit'))
                <div class="alert warn">صلاحيتك في هذا القسم: مشاهدة فقط.</div>
            @endunless
        @endif
        @yield('content')
    </div>
</main>
@auth
@if(in_array(auth()->user()->role, ['teacher', 'staff', 'admin']))
<script>try { localStorage.setItem('ltb-att-profile', JSON.stringify({ id: {{ auth()->id() }}, name: @json(auth()->user()->name), at: Date.now() })); } catch (e) {}</script>
@unless(request()->routeIs('attendance.scan'))<script src="{{ asset('js/attendance.js') }}?v=2" defer></script>@endunless
@endif
@endauth
<script>document.addEventListener('change', (e) => { const t = e.target.closest && e.target.closest('[data-toggle]'); if (t) { const el = document.querySelector(t.dataset.toggle); if (el) el.hidden = !t.checked; } });</script>
@stack('scripts')
@include('partials.install-prompt')
</body>
</html>
