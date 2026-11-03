@extends('layouts.app')
@section('title', 'ملاحظات '.$assignment->subject->name)
@section('content')
<div class="page-head">
    <div><h1>ملاحظات الطلاب · {{ $assignment->subject->name }}</h1><div class="sub">{{ $assignment->section->full_name }} · الملاحظة تظهر للطالب فور إرسالها في بوابته.</div></div>
    <div class="spacer"></div>
    <a class="btn" href="{{ route('teacher.sheet', $assignment) }}">رجوع للكشف</a>
</div>

<div class="panel">
    <h2>ملاحظة جديدة</h2>
    @include('teacher.partials.note-form', ['assignment' => $assignment, 'students' => $students, 'fixed' => null])
</div>

<h2>الملاحظات المرسلة ({{ $notes->count() }})</h2>
@forelse($notes as $n)
    <div class="note-item tone-note-{{ $n->type }}">
        <div class="inline"><span class="note-type">{{ $n->type_label }}</span><b>{{ $n->student->name }}</b><div class="spacer"></div>
            <span class="muted" style="color:var(--muted);font-size:12.5px">{{ $n->created_at->format('Y-m-d H:i') }}</span>
            @if((int) $n->author_id === (int) auth()->id())
            <form method="post" action="{{ route('teacher.notes.destroy', $n) }}" onsubmit="return confirm('حذف الملاحظة من بوابة الطالب؟')">@csrf @method('delete')<button class="btn sm danger" type="submit">حذف</button></form>
            @endif
        </div>
        <p>{{ $n->body }}</p>
    </div>
@empty
    <div class="panel" style="color:var(--muted)">لم تُرسل ملاحظات لطلاب هذا الكشف بعد.</div>
@endforelse
@endsection
