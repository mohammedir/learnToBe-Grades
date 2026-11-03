@extends('layouts.app')
@section('title', 'كشف '.$assignment->subject->name)
@section('module', 'marks')
@section('content')
<div class="page-head">
    <div>
        <h1>{{ $assignment->subject->name }} · {{ $assignment->section->full_name }}</h1>
        <div class="sub">المدرس: {{ $assignment->teacher->name }} · المكتمل <span id="completed-count">{{ $sheet->completedCount() }}</span> من {{ $sheet->students->count() }}</div>
    </div>
    <div class="spacer"></div>
    @perm('marks', 'edit')<span class="status" id="save-status">تعديلك يُحفظ تلقائياً</span>@endperm
    <a class="btn" href="{{ route('admin.marks.export', $assignment) }}">تنزيل Excel</a>
    @perm('marks', 'edit')<a class="btn" href="{{ route('admin.entry.teacher', $assignment->teacher) }}">كشوف {{ $assignment->teacher->name }}</a>@endperm
    <a class="btn" href="{{ route('admin.dashboard') }}">متابعة الرصد</a>
</div>
@php($canEdit = auth()->user()->hasPermission('marks', 'edit'))
@if($canEdit)<p style="color:var(--muted);margin-top:-8px">أنت ترصد نيابة عن {{ $assignment->teacher->name }}. اكتب الدرجة واضغط Enter للانتقال للطالب التالي، وامسح الرقم لحذف درجة.</p>@endif
@include('partials.sheet-table', ['sheet' => $sheet, 'editable' => $canEdit, 'saveUrl' => route('admin.marks.save', $assignment)])
@endsection
@if($canEdit)
@push('scripts')<script src="{{ asset('js/sheet.js') }}?v=1"></script>@endpush
@endif
