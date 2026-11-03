@extends('layouts.app')
@section('title', 'عناصر الدرجة')
@section('module', 'components')
@section('content')
<div class="page-head">
    <div><h1>عناصر الدرجة</h1><div class="sub">كل عنصر يظهر عموداً في كشف المدرس، والمجموع يُحسب تلقائياً. المجموع الحالي للعناصر المفعّلة: <b>{{ $components->where('is_active', true)->sum('max_score') + 0 }}</b></div></div>
</div>
@perm('components', 'edit')
<div class="panel">
    <form class="inline" method="post" action="{{ route('admin.components.store') }}">
        @csrf
        <input type="text" name="name" placeholder="اسم العنصر، مثل: الاختبار النهائي" required aria-label="اسم العنصر">
        <input type="number" name="max_score" placeholder="الدرجة العظمى" step="0.5" min="0.5" required style="width:150px" aria-label="الدرجة العظمى">
        <button class="btn primary" type="submit">إضافة عنصر</button>
    </form>
</div>
@endperm
<div class="scroll">
<table>
    <thead><tr><th>العنصر</th><th class="c">الدرجة العظمى</th><th class="c">الترتيب</th><th class="c">مفعّل</th><th class="c">درجات مرصودة</th><th></th></tr></thead>
    <tbody>
    @foreach($components as $c)
        <tr>
            <td colspan="4">
                @perm('components', 'edit')
                <form class="inline" method="post" action="{{ route('admin.components.update', $c) }}">
                    @csrf @method('put')
                    <input type="text" name="name" value="{{ $c->name }}" aria-label="اسم العنصر">
                    <input type="number" name="max_score" value="{{ $c->max_score + 0 }}" step="0.5" min="0.5" style="width:100px" aria-label="الدرجة العظمى">
                    <input type="number" name="sort_order" value="{{ $c->sort_order }}" min="0" style="width:80px" aria-label="الترتيب">
                    <label class="check"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($c->is_active)> مفعّل</label>
                    <button class="btn sm" type="submit">حفظ</button>
                </form>
                @else
                <b>{{ $c->name }}</b> · من {{ $c->max_score + 0 }} · {{ $c->is_active ? 'مفعّل' : 'موقوف' }}
                @endperm
            </td>
            <td class="c">{{ $used[$c->id] ?? 0 }}</td>
            <td>@perm('components', 'edit')<form method="post" action="{{ route('admin.components.destroy', $c) }}">@csrf @method('delete')<button class="btn sm danger" type="submit">حذف</button></form>@endperm</td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
<p style="color:var(--muted);font-size:13px">العنصر الذي عليه درجات لا يُحذف نهائياً، بل يُوقف حتى لا تضيع الدرجات.</p>
@endsection
