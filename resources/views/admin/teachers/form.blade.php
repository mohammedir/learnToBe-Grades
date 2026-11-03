@extends('layouts.app')
@section('title', $teacher->exists ? 'تعديل مدرس' : 'إضافة مدرس')
@section('content')
<div class="page-head"><h1>{{ $teacher->exists ? 'تعديل بيانات '.$teacher->name : 'إضافة مدرس جديد' }}</h1></div>
<div class="panel">
    <form class="form" method="post" action="{{ $teacher->exists ? route('admin.teachers.update', $teacher) : route('admin.teachers.store') }}">
        @csrf
        @if($teacher->exists) @method('put') @endif
        <div class="field"><label for="name">اسم المدرس</label><input type="text" id="name" name="name" value="{{ old('name', $teacher->name) }}" required></div>
        <div class="field"><label for="username">اسم المستخدم للدخول</label><input type="text" id="username" name="username" dir="ltr" value="{{ old('username', $teacher->username) }}" required><span class="hint">حروف إنجليزية وأرقام فقط، مثل: m.ahmad</span></div>
        <div class="field"><label for="phone">رقم الجوال (اختياري)</label><input type="text" id="phone" name="phone" dir="ltr" value="{{ old('phone', $teacher->phone) }}"></div>
        <div class="field">
            <label for="password">{{ $teacher->exists ? 'كلمة مرور جديدة (اتركها فارغة إن لم تتغير)' : 'كلمة المرور' }}</label>
            <input type="text" id="password" name="password" dir="ltr" autocomplete="new-password" {{ $teacher->exists ? '' : 'required' }}>
            <span class="hint">6 أحرف على الأقل. أرسلها للمدرس، ويستطيع تغييرها بعد الدخول.</span>
        </div>
        <label class="check"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $teacher->is_active))> الحساب مفعّل (يستطيع الدخول)</label>
        <div class="inline">
            <button class="btn primary" type="submit">{{ $teacher->exists ? 'حفظ' : 'إضافة ثم اختيار الشعب والمواد' }}</button>
            <a class="btn" href="{{ route('admin.teachers.index') }}">إلغاء</a>
        </div>
    </form>
</div>
@if($teacher->exists)
<div class="panel">
    <h2>حذف المدرس</h2>
    <p style="margin-top:0;color:var(--muted)">تُحذف تكليفاته، وتبقى الدرجات التي رصدها محفوظة. لإيقاف الدخول فقط، ألغِ «الحساب مفعّل» بدل الحذف.</p>
    <form method="post" action="{{ route('admin.teachers.destroy', $teacher) }}" onsubmit="return confirm('حذف المدرس {{ $teacher->name }}؟')">
        @csrf @method('delete')<button class="btn danger" type="submit">حذف المدرس</button>
    </form>
</div>
@endif
@endsection
