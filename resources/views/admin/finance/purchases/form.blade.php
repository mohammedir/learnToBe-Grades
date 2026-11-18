@extends('layouts.app')
@section('title', $order->exists ? 'تعديل '.$order->number : 'طلب شراء جديد')
@section('module', 'finance')
@section('content')
@include('admin.finance._tabs')
@php($cur = \App\Support\Money::currency())
<div class="page-head">
    <div><h1>{{ $order->exists ? 'تعديل الطلب '.$order->number : 'طلب شراء جديد' }}</h1>
        <div class="sub">يُسجَّل الطلب باسمك: <b>{{ $order->exists ? ($order->creator?->name ?? '—') : auth()->user()->name }}</b>. بعد أن تبدأ المطبعة التجهيز لا يمكن تعديله أو إلغاؤه.</div></div>
    <div class="spacer"></div>
    <a class="btn" href="{{ $order->exists ? route('admin.finance.purchases.show', $order) : route('admin.finance.purchases.index') }}">رجوع</a>
</div>

<form class="panel po-form" method="post" action="{{ $order->exists ? route('admin.finance.purchases.update', $order) : route('admin.finance.purchases.store') }}" id="po-form">
    @csrf @if($order->exists) @method('put') @endif

    <fieldset>
        <legend>الطلب</legend>
        <div class="po-grid">
            <div class="field span2"><label for="title">عنوان الطلب</label>
                <input id="title" name="title" required maxlength="255" value="{{ old('title', $order->title) }}" placeholder="مثال: طباعة اختبار الرياضيات الشهري الأول"></div>
            <div class="field"><label for="supplier_id">المطبعة</label>
                <select id="supplier_id" name="supplier_id" required>
                    @foreach($suppliers as $s)<option value="{{ $s->id }}" data-page-price="{{ $s->default_page_price }}" @selected(old('supplier_id', $order->supplier_id) == $s->id)>{{ $s->name }}</option>@endforeach
                </select></div>
            <div class="field"><label for="grade_level_id">الصف</label>
                <select id="grade_level_id" name="grade_level_id">
                    <option value="">— عام / بدون صف —</option>
                    @foreach($grades as $g)<option value="{{ $g->id }}" @selected(old('grade_level_id', $order->grade_level_id) == $g->id)>{{ $g->name }}</option>@endforeach
                </select></div>
            <div class="field"><label for="section_id">الشعبة</label>
                <select id="section_id" name="section_id">
                    <option value="">كل شعب الصف</option>
                    @foreach($grades as $g)@foreach($g->sections as $s)
                        <option value="{{ $s->id }}" data-grade="{{ $g->id }}" data-students="{{ $s->students_count }}" @selected(old('section_id', $order->section_id) == $s->id)>{{ $s->short_name }} ({{ $s->students_count }} طالب)</option>
                    @endforeach @endforeach
                </select></div>
            <div class="field"><label for="subject_id">المادة (اختياري)</label>
                <select id="subject_id" name="subject_id"><option value="">—</option>@foreach($subjects as $s)<option value="{{ $s->id }}" @selected(old('subject_id', $order->subject_id) == $s->id)>{{ $s->name }}</option>@endforeach</select></div>
            <div class="field"><label for="needed_by">مطلوب قبل (اختياري)</label>
                <input type="date" id="needed_by" name="needed_by" value="{{ old('needed_by', $order->needed_by?->format('Y-m-d')) }}"></div>
        </div>
    </fieldset>

    <fieldset>
        <legend>من سيستلم الطلبية من المطبعة؟</legend>
        @php($other = old('recipient_name', $order->recipient_name) && ! old('recipient_id', $order->recipient_id))
        <div class="po-grid">
            <div class="field"><label for="recipient_id">المستلم</label>
                <select id="recipient_id" name="recipient_id">
                    <option value="">— شخص آخر (اكتب اسمه) —</option>
                    <optgroup label="المدرسون">@foreach($people->where('role', 'teacher') as $p)<option value="{{ $p->id }}" @selected(old('recipient_id', $order->recipient_id) == $p->id)>{{ $p->name }}</option>@endforeach</optgroup>
                    <optgroup label="الإدارة">@foreach($people->where('role', '!=', 'teacher') as $p)<option value="{{ $p->id }}" @selected(old('recipient_id', $order->recipient_id) == $p->id)>{{ $p->name }}</option>@endforeach</optgroup>
                </select></div>
            <div class="field" id="recipient-name-field"><label for="recipient_name">اسم المستلم</label>
                <input id="recipient_name" name="recipient_name" maxlength="255" value="{{ old('recipient_name', $order->recipient_name) }}" placeholder="مثال: أبو محمد – السائق"></div>
        </div>
    </fieldset>

    <fieldset>
        <legend>السعر</legend>
        <div class="pricing-tabs" role="radiogroup" aria-label="طريقة التسعير">
            @foreach(\App\Models\PurchaseOrder::PRICING as $k => $label)
                <label><input type="radio" name="pricing" value="{{ $k }}" @checked(old('pricing', $order->pricing) === $k)> {{ $label }}</label>
            @endforeach
        </div>
        <p class="muted small price-hint" data-for="per_page">الكمية والسعر <b>اختيارية</b> هنا: اتركها فارغة لتحددها المطبعة عند التجهيز، أو أدخلها إن كانت معروفة.</p>
        <div class="po-grid price-fields">
            <div class="field" data-for="per_page"><label for="pages">عدد الأوراق في النسخة <span class="opt" data-for="per_page">(اختياري)</span></label><input type="number" id="pages" name="pages" min="1" inputmode="numeric" value="{{ old('pages', $order->pages) }}"></div>
            <div class="field" data-for="per_page per_unit"><label for="copies"><span data-for="per_page">عدد النسخ <span class="opt">(اختياري)</span></span><span data-for="per_unit">الكمية</span></label>
                <div class="inline" style="flex-wrap:nowrap"><input type="number" id="copies" name="copies" min="1" inputmode="numeric" value="{{ old('copies', $order->copies) }}"><button class="btn sm" type="button" id="use-students" hidden>= عدد الطلاب</button></div></div>
            <div class="field" data-for="per_page per_unit"><label for="unit_price"><span data-for="per_page">سعر الورقة <span class="opt">(اختياري)</span></span><span data-for="per_unit">سعر القطعة</span> ({{ $cur }})</label><input type="number" id="unit_price" name="unit_price" min="0" step="0.001" inputmode="decimal" value="{{ old('unit_price', $order->unit_price) }}" placeholder="{{ $order->supplier?->default_page_price ? 'المتفق عليه: '.rtrim(rtrim(number_format($order->supplier->default_page_price, 3, '.', ''), '0'), '.') : '' }}"></div>
            <div class="field" data-for="fixed"><label for="amount">سعر الطلبية ({{ $cur }})</label><input type="number" id="amount" name="amount" min="0" step="0.01" inputmode="decimal" value="{{ old('amount', $order->pricing === 'fixed' ? $order->amount : null) }}"></div>
        </div>
        <div class="po-total" aria-live="polite"><span id="po-formula"></span><b><span id="po-total">0.00</span> <span id="po-cur">{{ $cur }}</span></b></div>
    </fieldset>

    <div class="field"><label for="details">تفاصيل للمطبعة (اختياري)</label>
        <textarea id="details" name="details" rows="3" maxlength="2000" placeholder="مثال: طباعة وجهين، أبيض وأسود، تدبيس كل نسخة">{{ old('details', $order->details) }}</textarea></div>

    <div class="inline"><button class="btn primary big" type="submit">{{ $order->exists ? 'حفظ التعديلات' : 'إرسال الطلب للمطبعة' }}</button></div>
</form>
@endsection
@push('scripts')
<script>
(function () {
    const f = document.getElementById('po-form'); if (!f) return;
    const $ = (id) => document.getElementById(id);
    const grade = $('grade_level_id'), section = $('section_id'), rec = $('recipient_id'), recName = $('recipient-name-field');
    const pages = $('pages'), copies = $('copies'), price = $('unit_price'), amount = $('amount'), useStudents = $('use-students');
    const fmt = (n) => (Math.round(n * 100) / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const mode = () => (f.querySelector('input[name=pricing]:checked') || {}).value || 'fixed';

    function filterSections() {
        const g = grade.value;
        [...section.options].forEach(o => { if (o.value) { o.hidden = !g || o.dataset.grade !== g; } });
        if (section.selectedOptions[0]?.hidden) section.value = '';
        section.disabled = !g;
        studentsBtn();
    }
    function studentsBtn() {
        const n = section.selectedOptions[0]?.dataset.students;
        useStudents.hidden = !(n && +n > 0 && mode() !== 'fixed');
        useStudents.textContent = '= عدد الطلاب (' + (n || 0) + ')';
    }
    function recipient() { recName.hidden = !!rec.value; }
    function pricing() {
        const m = mode();
        f.querySelectorAll('[data-for]').forEach(el => { el.hidden = !el.dataset.for.split(' ').includes(m); });
        f.querySelectorAll('.pricing-tabs label').forEach(l => l.classList.toggle('on', l.querySelector('input').checked));
        studentsBtn(); total();
    }
    function total() {
        const m = mode(); let t = 0, txt = '';
        if (m === 'per_page') {
            if (!pages.value || !copies.value || price.value === '') { $('po-total').textContent = 'تحدده المطبعة'; $('po-cur').hidden = true; $('po-formula').textContent = 'سيُحسب المبلغ عندما تحدد المطبعة الكمية والسعر'; return; }
            t = (+pages.value) * (+copies.value) * (+price.value); txt = pages.value + ' ورقة × ' + copies.value + ' نسخة × ' + price.value + ' = ' + ((+pages.value) * (+copies.value)) + ' ورقة';
        }
        else if (m === 'per_unit') { t = (+copies.value || 0) * (+price.value || 0); txt = (copies.value || 0) + ' × ' + (price.value || 0); }
        else { t = +amount.value || 0; txt = 'سعر ثابت'; }
        $('po-total').textContent = fmt(t); $('po-formula').textContent = txt; $('po-cur').hidden = false;
    }
    grade.addEventListener('change', filterSections);
    section.addEventListener('change', studentsBtn);
    rec.addEventListener('change', recipient);
    f.querySelectorAll('input[name=pricing]').forEach(r => r.addEventListener('change', pricing));
    [pages, copies, price, amount].forEach(i => i.addEventListener('input', total));
    useStudents.addEventListener('click', () => { copies.value = section.selectedOptions[0]?.dataset.students || ''; total(); });
    filterSections(); recipient(); pricing();
})();
</script>
@endpush
