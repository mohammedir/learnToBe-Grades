@extends('layouts.app')
@section('title', 'رصد الدرجات من الإدارة')
@section('content')
<div class="page-head">
    <div><h1>رصد الدرجات من الإدارة</h1><div class="sub">اختر المدرس لتفتح كشوفه وترصد درجاتها نيابة عنه. كل درجة تُحفظ تلقائياً ويُسجَّل أنها رُصدت من حسابك.</div></div>
</div>
@if($rows->isEmpty())
    <div class="panel">لا يوجد مدرسون بعد.</div>
@else
<div class="cards">
    @foreach($rows as $r)
        @php($pct = $r['sheets'] ? round($r['done'] / $r['sheets'] * 100) : 0)
        <a class="card" href="{{ route('admin.entry.teacher', $r['teacher']) }}">
            <b>{{ $r['teacher']->name }}</b>
            <span class="meta">{{ $r['sheets'] }} كشف · مكتمل {{ $r['done'] }} · بدأ {{ $r['started'] }}</span>
            <div class="bar" aria-hidden="true"><i style="width:{{ $pct }}%"></i></div>
            <span class="meta">
                @if(! $r['sheets'])<span class="pill none">بدون كشوف</span>
                @elseif($r['done'] === $r['sheets'])<span class="pill done">كل كشوفه مكتملة</span>
                @elseif($r['started'])<span class="pill part">قيد الرصد</span>
                @else<span class="pill none">لم يبدأ</span>@endif
            </span>
        </a>
    @endforeach
</div>
@endif
@endsection
