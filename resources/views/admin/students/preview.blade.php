@extends('layouts.app')
@section('title', 'معاينة الاستيراد')
@section('module', 'students')
@section('content')
<div class="page-head">
    <div><h1>معاينة استيراد الطلاب</h1><div class="sub">الملف: {{ $fileName }} · لم يُحفظ شيء بعد.</div></div>
</div>
<div class="stats">
    <div class="stat"><b>{{ count($report['created']) }}</b><span>طالب جديد سيُضاف</span></div>
    <div class="stat"><b>{{ $report['updated'] }}</b><span>طالب موجود ستُحدَّث بياناته</span></div>
    <div class="stat"><b>{{ count($report['corrected']) }}</b><span>منهم صُحّح اسمه أو هويته</span></div>
    <div class="stat"><b>{{ $report['phones'] }}</b><span>جوال سيُضاف أو يُحدَّث</span></div>
    <div class="stat"><b>{{ count($report['deactivated']) }}</b><span>طالب سيُوقف {{ $sync ? '' : '(المزامنة غير مفعّلة)' }}</span></div>
</div>

@php($lists = [
    'created' => ['طلاب جدد', 'ok'],
    'corrected' => ['طلاب موجودون صُحّحت بياناتهم (الاسم القديم ← الجديد)', 'ok'],
    'moved' => ['طلاب سيُنقلون إلى شعبة أخرى', 'warn'],
    'deactivated' => ['طلاب سيُوقفون لعدم وجودهم في الملف', 'bad'],
    'warnings' => ['ملاحظات تحتاج مراجعة في الملف', 'warn'],
    'newSections' => ['شعب جديدة ستُنشأ', 'ok'],
])
@foreach($lists as $key => [$label, $cls])
    @if(! empty($report[$key]))
        <div class="panel">
            <h2>{{ $label }} ({{ count($report[$key]) }})</h2>
            <ul style="margin:0;padding-inline-start:20px;line-height:1.9">@foreach($report[$key] as $line)<li>{{ $line }}</li>@endforeach</ul>
        </div>
    @endif
@endforeach

<div class="inline">
    <form method="post" action="{{ route('admin.students.import.confirm') }}">@csrf<button class="btn primary" type="submit">تأكيد الاستيراد</button></form>
    <a class="btn" href="{{ route('admin.students.import') }}">إلغاء</a>
</div>
@endsection
