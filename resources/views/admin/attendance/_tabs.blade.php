<nav class="subnav" aria-label="أقسام الحضور">
    <span class="subnav-title">حضور المدرسين</span>
    <a href="{{ route('admin.attendance.index') }}" @class(['on' => request()->routeIs('admin.attendance.index')])>اليوم</a>
    <a href="{{ route('admin.attendance.month') }}" @class(['on' => request()->routeIs('admin.attendance.month', 'admin.attendance.person')])>التقرير الشهري</a>
    <a href="{{ route('admin.attendance.points') }}" @class(['on' => request()->routeIs('admin.attendance.points*')])>رموز QR والإعدادات</a>
</nav>
