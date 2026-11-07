@extends('layouts.app')
@section('title', 'بوابة الطلاب')
@section('module', 'portal')
@section('content')
<div class="page-head">
    <div class="spacer" style="order:2"></div>
    <a class="btn primary" style="order:3" href="{{ route('admin.portal.credentials') }}">بيانات دخول الطلاب</a>
    <div style="order:1"><h1>بوابة الطلاب</h1><div class="sub">يدخل الطالب برقم هويته وكلمة مرور مؤقتة، ويغيّرها عند أول دخول، ثم يرى درجاته بعد نشرها. رابط الدخول: <a href="{{ route('student.login') }}" dir="ltr">{{ route('student.login') }}</a></div></div>
</div>

<div class="stats">
    <div class="stat"><b>{{ $published ? 'منشورة' : 'مخفية' }}</b><span>الدرجات للطلاب</span></div>
    <div class="stat"><b>{{ $totals['accounts'] }}/{{ $totals['students'] }}</b><span>طلاب لهم كلمة مرور</span></div>
    <div class="stat"><b>{{ $totals['logged'] }}</b><span>طالب دخل البوابة</span></div>
    <div class="stat"><b>{{ $totals['no_id'] }}</b><span>بدون رقم هوية (لا يمكنهم الدخول)</span></div>
</div>

@if($suspended->isNotEmpty())
<div class="panel" style="border-color:var(--bad-bg)">
    <h2>حسابات موقوفة ({{ $suspended->count() }})</h2>
    <div class="scroll"><table>
        <thead><tr><th>الطالب</th><th>الشعبة</th><th>السبب</th><th>منذ</th><th></th></tr></thead>
        <tbody>
        @foreach($suspended as $st)
            <tr><td><b>{{ $st->name }}</b></td><td>{{ $st->section->full_name }}</td><td>{{ $st->suspension_reason }}</td><td class="muted">{{ $st->suspended_at->format('Y/m/d') }}</td>
                <td>@perm('portal', 'edit')<form method="post" action="{{ route('admin.portal.unsuspend', $st) }}">@csrf @method('delete')<button class="btn sm" type="submit">إعادة التفعيل</button></form>@endperm</td></tr>
        @endforeach
        </tbody>
    </table></div>
</div>
@endif

@perm('portal', 'edit')
<div class="panel">
    <h2>نشر الدرجات</h2>
    <form class="inline" method="post" action="{{ route('admin.portal.publish') }}">
        @csrf
        <div class="field"><label for="title">عنوان النتائج كما يراه الطالب</label><input type="text" id="title" name="title" value="{{ $title }}" style="min-width:280px" required></div>
        <label class="check"><input type="radio" name="published" value="1" @checked($published)> نشر الدرجات للطلاب</label>
        <label class="check"><input type="radio" name="published" value="0" @checked(! $published)> إخفاء الدرجات</label>
        <button class="btn primary" type="submit">حفظ</button>
    </form>
    <p style="color:var(--muted);font-size:13px;margin-bottom:0">قبل النشر تأكد من اكتمال الرصد في «متابعة الرصد». بعد النشر يرى الطالب كل درجة يرصدها المدرس فور حفظها.</p>
</div>
@endperm

<div class="panel">
    <h2>حسابات الطلاب حسب الشعبة</h2>
    <p style="margin-top:0;color:var(--muted);font-size:13.5px">لتغيير كلمة مرور طالب بعينه، افتح شعبته من الجدول ثم «تعيين كلمة مرور محددة». زر «إنشاء للجدد» ينشئ كلمات مرور للطلاب الذين ليس لهم حساب، وينزّل ملف Excel فيه رقم الهوية وكلمة المرور ورسالة جاهزة لكل طالب لترسلها لولي الأمر. <b>كلمات المرور تظهر في هذا الملف مرة واحدة فقط</b>، فاحفظه. زر «إعادة إنشاء للكل» يلغي كلمات المرور القديمة لكل طلاب الشعبة.</p>
    <div class="scroll">
    <table>
        <thead><tr><th>الشعبة</th><th class="c">الطلاب</th><th class="c">لهم حساب</th><th class="c">دخلوا البوابة</th><th class="c">بدون هوية</th><th></th></tr></thead>
        <tbody>
        @foreach($sections as $sec)
            <tr>
                <td><a href="{{ route('admin.portal.index', ['section' => $sec->id]) }}"><b>{{ $sec->full_name }}</b></a></td>
                <td class="c">{{ $sec->students_count }}</td>
                <td class="c"><span class="pill {{ $sec->accounts_count == $sec->students_count - $sec->no_id_count && $sec->accounts_count > 0 ? 'done' : ($sec->accounts_count ? 'part' : 'none') }}">{{ $sec->accounts_count }}</span></td>
                <td class="c">{{ $sec->logged_count }}</td>
                <td class="c">@if($sec->no_id_count)<span class="pill off">{{ $sec->no_id_count }}</span>@else 0 @endif</td>
                <td>
                    @perm('portal', 'edit')
                    <div class="inline">
                        <form method="post" action="{{ route('admin.portal.issue-section', $sec) }}">@csrf<input type="hidden" name="mode" value="new"><button class="btn sm primary" type="submit">إنشاء للجدد</button></form>
                        <form method="post" action="{{ route('admin.portal.issue-section', $sec) }}" onsubmit="return confirm('ستتغير كلمات مرور كل طلاب {{ $sec->full_name }}، ولن تعمل كلماتهم القديمة. متابعة؟')">@csrf<input type="hidden" name="mode" value="all"><button class="btn sm" type="submit">إعادة إنشاء للكل</button></form>
                    </div>
                    @endperm
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>

@if($section)
<div class="panel">
    <div class="inline" style="margin-bottom:10px">
        <h2 style="margin:0">بيانات دخول طلاب {{ $section->full_name }}</h2>
        <div class="spacer"></div>
        @perm('portal', 'edit')<button class="btn sm" type="button" id="reveal-all">إظهار كل كلمات المرور</button>@endperm
    </div>
    <p style="margin-top:0;color:var(--muted);font-size:13px">تظهر كلمة المرور المؤقتة التي أنشأتها الإدارة حتى يغيّرها الطالب. بعد أن يختار الطالب كلمته الخاصة تُحفظ مشفّرة ولا يراها أحد؛ إن نسيها عيّن له كلمة جديدة. زر «نسخ الرسالة» ينسخ رسالة جاهزة لولي الأمر فيها الرابط ورقم الهوية وكلمة المرور.</p>
    <div class="scroll">
    <table>
        <thead><tr><th>اسم الطالب</th><th>الجوال</th><th>اسم المستخدم (رقم الهوية)</th><th>كلمة المرور</th><th class="c">الحالة</th><th>آخر دخول</th><th></th></tr></thead>
        <tbody>
        @foreach($students as $st)
            @php($canSee = auth()->user()->hasPermission('portal', 'edit') && $st->initial_password)
            <tr>
                <td><b>{{ $st->name }}</b></td>
                <td dir="ltr" style="text-align:right">{{ $st->phone ?: '—' }}</td>
                <td dir="ltr" style="text-align:right">{{ $st->national_id ?: '—' }}</td>
                <td>
                    @if($canSee)
                        <code class="secret" dir="ltr" data-secret="{{ $st->initial_password }}">••••••</code>
                        <button class="btn sm ghost reveal" type="button">إظهار</button>
                    @elseif($st->password)
                        <span class="muted" style="color:var(--muted)">{{ $st->must_change_password ? 'مؤقتة (أُنشئت قبل التحديث)' : 'اختارها الطالب' }}</span>
                    @else
                        <span style="color:var(--muted)">—</span>
                    @endif
                </td>
                <td class="c">
                    @if($st->isSuspended())<span class="pill off" title="{{ $st->suspension_reason }}">موقوف</span>
                    @elseif(! $st->national_id)<span class="pill off">بدون هوية</span>
                    @elseif(! $st->password)<span class="pill none">لا يوجد حساب</span>
                    @elseif($st->must_change_password)<span class="pill part">لم يغيّرها بعد</span>
                    @else<span class="pill done">مفعّل</span>@endif
                </td>
                <td class="muted">{{ $st->last_login_at?->format('Y-m-d H:i') ?? 'لم يدخل' }}</td>
                <td>
                    @perm('portal', 'edit')
                    @if($st->national_id)
                    <div class="inline">
                        @if($canSee && $st->phone)
                            <a class="btn sm wa-btn" target="_blank" rel="noopener" href="{{ $st->whatsappUrl("مدرسة تعلم لتكون الخاصة\nبوابة الطالب: ".$st->name."\nرابط الدخول: ".route('student.login')."\nرقم الهوية: ".$st->national_id."\nكلمة المرور: ".$st->initial_password."\nسيُطلب تغيير كلمة المرور عند أول دخول.") }}">إرسال واتساب</a>
                        @endif
                        @if($canSee)
                            <button class="btn sm copy-msg" type="button" data-msg="{{ "مدرسة تعلم لتكون الخاصة\nبوابة الطالب: ".$st->name."\nرابط الدخول: ".route('student.login')."\nرقم الهوية: ".$st->national_id."\nكلمة المرور: ".$st->initial_password."\nسيُطلب تغيير كلمة المرور عند أول دخول." }}">نسخ الرسالة</button>
                        @endif
                        <details>
                            <summary class="btn sm">تعديل كلمة المرور</summary>
                            <form class="form" method="post" action="{{ route('admin.portal.set-password', $st) }}" style="margin-top:8px;min-width:240px">
                                @csrf @method('put')
                                <input type="text" name="password" dir="ltr" minlength="6" placeholder="كلمة المرور الجديدة" required aria-label="كلمة المرور الجديدة لـ{{ $st->name }}">
                                <label class="check"><input type="checkbox" name="must_change" value="1" checked> يطلب منه تغييرها عند الدخول</label>
                                <div class="inline"><button class="btn sm primary" type="submit">حفظ</button></div>
                            </form>
                            <form method="post" action="{{ route('admin.portal.issue-student', $st) }}" style="margin-top:6px">@csrf<button class="btn sm" type="submit">كلمة عشوائية في ملف Excel</button></form>
                        </details>
                        @if($st->isSuspended())
                            <form method="post" action="{{ route('admin.portal.unsuspend', $st) }}">@csrf @method('delete')<button class="btn sm" type="submit">إعادة التفعيل</button></form>
                        @else
                        <details>
                            <summary class="btn sm danger">إيقاف الحساب</summary>
                            <form class="form" method="post" action="{{ route('admin.portal.suspend', $st) }}" style="margin-top:8px;min-width:260px">
                                @csrf @method('put')
                                <textarea name="reason" rows="2" required minlength="3" maxlength="500" placeholder="سبب الإيقاف كما سيظهر للطالب، مثل: عدم سداد الرسوم" aria-label="سبب إيقاف {{ $st->name }}"></textarea>
                                <div><button class="btn sm danger" type="submit">إيقاف</button></div>
                            </form>
                        </details>
                        @endif
                    </div>
                    @endif
                    @endperm
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
@push('scripts')
<script>
document.querySelectorAll('.reveal').forEach(b => b.addEventListener('click', () => {
    const c = b.previousElementSibling, shown = c.dataset.shown === '1';
    c.textContent = shown ? '••••••' : c.dataset.secret; c.dataset.shown = shown ? '0' : '1'; b.textContent = shown ? 'إظهار' : 'إخفاء';
}));
document.getElementById('reveal-all')?.addEventListener('click', () => document.querySelectorAll('.secret').forEach(c => { c.textContent = c.dataset.secret; c.dataset.shown = '1'; }));
document.querySelectorAll('.copy-msg').forEach(b => b.addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(b.dataset.msg); b.textContent = 'تم النسخ ✓'; }
    catch (e) { prompt('انسخ الرسالة:', b.dataset.msg); }
    setTimeout(() => b.textContent = 'نسخ الرسالة', 2000);
}));
</script>
@endpush
@endif
@endsection
