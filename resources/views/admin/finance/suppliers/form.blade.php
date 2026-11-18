@extends('layouts.app')
@section('title', $supplier->exists ? 'تعديل '.$supplier->name : 'إضافة مطبعة')
@section('module', 'finance')
@section('content')
@include('admin.finance._tabs')
<div class="page-head"><div><h1>{{ $supplier->exists ? 'تعديل '.$supplier->name : 'إضافة مطبعة / مورد' }}</h1></div><div class="spacer"></div><a class="btn" href="{{ route('admin.finance.suppliers.index') }}">رجوع</a></div>
<form class="panel form" method="post" action="{{ $supplier->exists ? route('admin.finance.suppliers.update', $supplier) : route('admin.finance.suppliers.store') }}" style="max-width:720px">
    @csrf @if($supplier->exists) @method('put') @endif
    <div class="po-grid">
        <div class="field span2"><label for="name">اسم المطبعة / المكتبة</label><input id="name" name="name" required maxlength="255" value="{{ old('name', $supplier->name) }}"></div>
        <div class="field"><label for="contact_name">اسم المسؤول</label><input id="contact_name" name="contact_name" maxlength="255" value="{{ old('contact_name', $supplier->contact_name) }}"></div>
        <div class="field"><label for="phone">الجوال (واتساب)</label><input id="phone" name="phone" dir="ltr" inputmode="tel" maxlength="30" value="{{ old('phone', $supplier->phone) }}" placeholder="059xxxxxxx"></div>
        <div class="field"><label for="default_page_price">سعر الورقة المتفق عليه ({{ \App\Support\Money::currency() }})</label><input id="default_page_price" name="default_page_price" type="number" step="0.001" min="0" value="{{ old('default_page_price', $supplier->default_page_price) }}"><span class="hint">يُعبّأ تلقائياً في طلبات الطباعة بالورقة، ويمكن تغييره لكل طلب.</span></div>
        <div class="field"><label for="notes">ملاحظات</label><input id="notes" name="notes" maxlength="500" value="{{ old('notes', $supplier->notes) }}"></div>
    </div>
    <input type="hidden" name="is_active" value="0"><label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $supplier->is_active))> نشط (يظهر عند إنشاء الطلبات)</label>

    <fieldset>
        <legend>حساب دخول المطبعة (اختياري)</legend>
        <p class="muted small" style="margin-top:0">تدخل المطبعة بهذا الحساب من نفس صفحة الدخول، فترى طلبات المدرسة الموجهة لها فقط وتغيّر حالتها (قيد التجهيز ← جاهز ← تم الاستلام) وتطّلع على كشف الحساب. لا ترى أي بيانات للطلاب أو الدرجات.</p>
        <div class="po-grid">
            <div class="field"><label for="username">اسم المستخدم</label><input id="username" name="username" dir="ltr" maxlength="50" autocomplete="off" value="{{ old('username', $account?->username) }}" placeholder="press"></div>
            <div class="field"><label for="password">كلمة المرور {{ $account ? '(اتركها فارغة لعدم التغيير)' : '' }}</label><input id="password" name="password" type="text" dir="ltr" minlength="6" autocomplete="new-password"></div>
        </div>
        @if($account)
            <input type="hidden" name="account_active" value="0"><label class="check"><input type="checkbox" name="account_active" value="1" @checked($account->is_active)> الحساب مفعّل</label>
        @else
            <input type="hidden" name="account_active" value="1">
        @endif
    </fieldset>
    <div><button class="btn primary" type="submit">حفظ</button></div>
</form>
@endsection
