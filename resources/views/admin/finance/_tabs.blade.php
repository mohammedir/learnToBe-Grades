<nav class="subnav" aria-label="أقسام المالية">
    <span class="subnav-title">المالية</span>
    <a href="{{ route('admin.finance.index') }}" @class(['on' => request()->routeIs('admin.finance.index')])>نظرة عامة</a>
    <a href="{{ route('admin.finance.purchases.index') }}" @class(['on' => request()->routeIs('admin.finance.purchases.*')])>المشتريات</a>
    <a href="{{ route('admin.finance.payments.index') }}" @class(['on' => request()->routeIs('admin.finance.payments.*')])>الدفعات</a>
    <a href="{{ route('admin.finance.statement') }}" @class(['on' => request()->routeIs('admin.finance.statement')])>كشف الحساب</a>
    <a href="{{ route('admin.finance.suppliers.index') }}" @class(['on' => request()->routeIs('admin.finance.suppliers.*')])>المطابع والموردون</a>
</nav>
