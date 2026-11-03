@extends('layouts.app')
@section('title', 'استيراد الطلاب')
@section('content')
<div class="page-head"><div><h1>استيراد الطلاب من Excel</h1><div class="sub">ارفع كشف الطلاب بنفس شكل ملف المدرسة. تُنشأ الصفوف والشعب الجديدة تلقائياً.</div></div></div>
<div class="panel">
    <form class="form" method="post" action="{{ route('admin.students.import.run') }}" enctype="multipart/form-data">
        @csrf
        <div class="field"><label for="file">ملف Excel (.xlsx)</label><input type="file" id="file" name="file" accept=".xlsx,.xls" required></div>
        <label class="check"><input type="checkbox" name="sync" value="1"> <span>الملف هو الكشف الكامل للمدرسة: <b>أوقف الطلاب غير الموجودين فيه</b> (تبقى درجاتهم محفوظة ويمكن إعادتهم)</span></label>
        <div><button class="btn primary" type="submit">معاينة قبل الاستيراد</button></div>
        <span class="hint">لن يُحفظ شيء في هذه الخطوة. ستظهر لك قائمة بما سيتغير، ثم تضغط «تأكيد الاستيراد».</span>
    </form>
</div>
<div class="panel">
    <h2>شكل الملف المطلوب</h2>
    <p style="margin-top:0">الورقة الأولى، والسطر الأول عناوين الأعمدة. يتعرّف النظام على الأعمدة بأسمائها:</p>
    <div class="scroll"><table>
        <thead><tr><th>م</th><th>الاسم الرباعي</th><th>تاريخ الميلاد</th><th>رقم الجوال</th><th>رقم الهوية</th><th>الصف</th><th>الشعبة (اختياري)</th><th>الجنس</th></tr></thead>
        <tbody><tr><td>1</td><td>مثال: أحمد محمد علي حسن</td><td>2015-03-12</td><td>0599000000</td><td>400000000</td><td>الصف الخامس</td><td>2</td><td>ذكر</td></tr></tbody>
    </table></div>
    <ul style="color:var(--muted);font-size:13.5px">
        <li>الطالب الموجود لا يتكرر ودرجاته لا تضيع: يُطابق برقم الهوية، ثم بالاسم الكامل في نفس الصف، ثم بالاسم الأول واسم العائلة (لمن أُكمل اسمه أو صُحّح رقم هويته).</li>
        <li>لا يُنقل الطالب من شعبته إلا إذا تغيّر صفه أو كانت الشعبة لا تناسب جنسه.</li>
        <li>إن لم يوجد عمود «الشعبة» يوضع الطالب في الشعبة المناسبة لصفه وجنسه.</li>
        <li>عمود «رقم الجوال» يُقرأ ويُنظَّف تلقائياً: 599123456 أو +970599123456 تصبح 0599123456. الخانة التي فيها رقمان مثل «0599…/0567…» يُحفظ منها جوالان. الخانة الفارغة لا تمسح جوالاً محفوظاً.</li>
        <li>سطور العناوين داخل الكشف (اسم الصف، الذكور، الإناث) تُفهم تلقائياً.</li>
    </ul>
</div>
@endsection
