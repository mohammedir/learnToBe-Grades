@extends('layouts.app')
@section('title', $assignment->subject->name.' – '.$assignment->section->full_name)
@section('content')
<div class="page-head">
    <div>
        <h1>{{ $assignment->subject->name }} · {{ $assignment->section->full_name }}</h1>
        <div class="sub">المكتمل: <span id="completed-count">{{ $sheet->completedCount() }}</span> من {{ $sheet->students->count() }} طالب</div>
    </div>
    <div class="spacer"></div>
    <span class="status" id="save-status">تُحفظ كل درجة تلقائياً</span>
    <a class="btn" href="{{ route('teacher.notes', $assignment) }}">ملاحظات الطلاب</a>
    <a class="btn" href="{{ route('teacher.quizzes.index', ['assignment' => $assignment->id]) }}">الاختبارات</a>
    <a class="btn" href="{{ route('teacher.sheet.export', $assignment) }}">تنزيل Excel</a>
    <a class="btn" href="{{ route('teacher.home') }}">رجوع لكشوفي</a>
</div>
@if($sheet->locked)
    <div class="alert" style="background:#EAF1FB;color:var(--navy)">🔒 {{ $sheet->fullyApproved() ? 'كل درجات هذا الكشف معتمدة من الإدارة، ولا يمكن تعديلها.' : 'الأعمدة المعلّمة 🔒 معتمدة من الإدارة ولا يمكن تعديلها. باقي الأعمدة يمكنك رصدها.' }} لتصحيح درجة معتمدة تواصل مع الإدارة لفك الاعتماد.</div>
@endif
<p class="sub" style="color:var(--muted);margin-top:-8px">اكتب الدرجة واضغط Enter للانتقال للطالب التالي. اترك الخانة فارغة إن لم تُرصد بعد، وامسح الرقم لحذف درجة.</p>
@include('partials.sheet-table', ['sheet' => $sheet, 'editable' => true, 'saveUrl' => route('teacher.sheet.mark', $assignment), 'noteable' => true])

<dialog id="note-dialog" class="dialog">
    <div class="inline" style="margin-bottom:8px"><h2 style="margin:0">ملاحظة لـ<span id="note-dialog-name"></span></h2><div class="spacer"></div><button class="btn sm" type="button" onclick="this.closest('dialog').close()">إغلاق</button></div>
    <form class="form" method="post" action="{{ route('teacher.notes.store', $assignment) }}" style="max-width:none">
        @csrf
        <input type="hidden" name="student_id" id="note-dialog-student">
        <div class="chips">
            @foreach(\App\Models\StudentNote::TYPES as $key => [$label, $hint])
                <label class="tone-note-{{ $key }}"><input type="radio" name="type" value="{{ $key }}" @checked($key === 'praise') required> {{ $label }}</label>
            @endforeach
        </div>
        <textarea name="body" rows="4" maxlength="1000" required placeholder="مثال: أحسنت في مشاركتك هذا الأسبوع، استمر!" aria-label="نص الملاحظة"></textarea>
        <div><button class="btn primary" type="submit">إرسال للطالب</button></div>
    </form>
</dialog>
@endsection
@push('scripts')
<script src="{{ asset('js/sheet.js') }}?v=1"></script>
<script>
document.querySelectorAll('[data-note-student]').forEach(b => b.addEventListener('click', () => {
    document.getElementById('note-dialog-student').value = b.dataset.noteStudent;
    document.getElementById('note-dialog-name').textContent = b.dataset.noteName;
    document.getElementById('note-dialog').showModal();
}));
</script>
@endpush
