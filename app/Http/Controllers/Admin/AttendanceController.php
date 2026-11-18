<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceExcuse;
use App\Models\AttendancePoint;
use App\Models\AttendanceRecord;
use App\Models\Setting;
use App\Models\User;
use App\Services\AttendanceReport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** حضور المدرسين والموظفين: المتابعة اليومية والشهرية ورموز QR */
class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->filled('date') ? Carbon::parse($request->query('date'))->startOfDay() : now()->startOfDay();
        $rows = AttendanceReport::day($date);
        $counts = $rows->countBy('status');

        return view('admin.attendance.index', [
            'date' => $date,
            'rows' => $rows,
            'counts' => $counts,
            'noOut' => $rows->where('no_out', true)->count(),
            'flagged' => $rows->filter(fn ($r) => $r['flags']->isNotEmpty())->count(),
            'settings' => AttendanceReport::settings(),
            'pointsCount' => AttendancePoint::where('is_active', true)->count(),
            'people' => AttendanceReport::people(),
        ]);
    }

    public function month(Request $request)
    {
        [$y, $m] = $this->ym($request);

        $from = Carbon::create($y, $m, 1);

        return view('admin.attendance.month', AttendanceReport::month($y, $m) + ['y' => $y, 'm' => $m,
            'excuses' => AttendanceExcuse::with(['user', 'creator'])->whereBetween('date', [$from->toDateString(), $from->copy()->endOfMonth()->toDateString()])
                ->orderBy('date')->get(),
        ]);
    }

    public function export(Request $request)
    {
        [$y, $m] = $this->ym($request);
        $r = AttendanceReport::month($y, $m);
        $book = new Spreadsheet;
        $ws = $book->getActiveSheet();
        $ws->setTitle('الحضور');
        $ws->setRightToLeft(true);
        $label = $r['from']->locale('ar')->translatedFormat('F Y');
        $head = ['م', 'الاسم', 'أيام الحضور', 'من أيام الدوام', 'نسبة الحضور', 'أيام التأخير', 'دقائق التأخير', 'غياب بدون عذر', 'غياب بعذر (مجاز)', 'خروج مبكر', 'بلا تسجيل مغادرة', 'ساعات العمل'];
        foreach ($r['days'] as $d) {
            $head[] = $d->day;
        }
        $last = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($head));
        $ws->mergeCells("A1:{$last}1");
        $ws->setCellValue('A1', 'مدرسة تعلم لتكون الخاصة — حضور المدرسين والموظفين — '.$label);
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('FFFFFF');
        $ws->getStyle("A1:{$last}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F3864');
        $ws->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getRowDimension(1)->setRowHeight(26);
        $sym = ['present' => '✓', 'late' => 'ت', 'absent' => 'غ', 'excused' => 'ع', 'waiting' => '', 'off' => '-'];
        $rows = [$head];
        foreach ($r['rows'] as $i => $t) {
            $line = [$i + 1, $t['user']->name, $t['present'], $r['workdays'], $t['rate'] === null ? '' : $t['rate'] / 100, $t['late'], $t['late_minutes'], $t['absent'], $t['excused'], $t['early'], $t['no_out'], round($t['minutes'] / 60, 1)];
            foreach ($r['days'] as $d) {
                $line[] = $sym[$t['cells'][$d->day] ?? 'waiting'] ?? '';
            }
            $rows[] = $line;
        }
        $ws->fromArray($rows, null, 'A3', true);
        $end = 2 + count($rows);
        $ws->getStyle("A3:{$last}3")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $ws->getStyle("A3:{$last}3")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2F5DA8');
        $ws->getStyle("A3:{$last}{$end}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('B7C3D6');
        $ws->getStyle("A3:{$last}{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $ws->getStyle("B4:B{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $ws->getStyle("E4:E{$end}")->getNumberFormat()->setFormatCode('0%');
        $ws->getStyle("A3:{$last}{$end}")->getFont()->setName('Arial');
        $ws->getColumnDimension('B')->setWidth(28);
        foreach (range(3, 12) as $c) {
            $ws->getColumnDimensionByColumn($c)->setWidth(12);
        }
        for ($c = 13; $c <= count($head); $c++) {
            $ws->getColumnDimensionByColumn($c)->setWidth(4);
        }
        $ws->getStyle("C3:L3")->getAlignment()->setWrapText(true);
        // ملاحظات الأعذار في ورقة ثانية
        $ex = AttendanceExcuse::with(['user', 'creator'])->whereBetween('date', [$r['from']->toDateString(), $r['from']->copy()->endOfMonth()->toDateString()])->orderBy('date')->get();
        if ($ex->isNotEmpty()) {
            $w2 = $book->createSheet();
            $w2->setTitle('الأعذار والإجازات');
            $w2->setRightToLeft(true);
            $w2->fromArray(array_merge([['التاريخ', 'الاسم', 'الملاحظة', 'سجّلها']], $ex->map(fn ($e) => [$e->date->format('Y-m-d'), $e->user?->name, $e->note ?: 'غائب بعذر (مجاز)', $e->creator?->name])->all()), null, 'A1', true);
            $w2->getStyle('A1:D1')->getFont()->setBold(true);
            foreach (['A' => 12, 'B' => 28, 'C' => 50, 'D' => 20] as $col => $wd) {
                $w2->getColumnDimension($col)->setWidth($wd);
            }
        }
        $ws->setCellValue('A'.($end + 2), 'الرموز: ✓ حاضر · ت متأخر · غ غائب · ع غائب بعذر (مجاز) · - عطلة. الدوام من '.$r['settings']['start'].' إلى '.$r['settings']['end'].' مع سماح '.$r['settings']['grace'].' دقائق.');
        $ws->freezePane('C4');
        $ws->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)->setFitToWidth(1)->setFitToHeight(0);

        return response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), 'حضور المدرسين '.$r['from']->format('Y-m').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** سجل شخص واحد في شهر */
    public function person(Request $request, User $user)
    {
        [$y, $m] = $this->ym($request);
        $from = Carbon::create($y, $m, 1)->startOfDay();
        $to = $from->copy()->endOfMonth();
        $s = AttendanceReport::settings();
        $recs = AttendanceRecord::with(['point', 'creator', 'editor'])->where('user_id', $user->id)->whereBetween('occurred_at', [$from, $to])
            ->orderBy('occurred_at')->get()->groupBy(fn ($r) => $r->occurred_at->toDateString());
        $excuses = AttendanceExcuse::with('creator')->where('user_id', $user->id)->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()->keyBy(fn ($e) => $e->date->toDateString());
        $days = collect();
        // يشمل أياماً قادمة فيها إجازة مسجّلة مسبقاً
        $futureLeave = $excuses->keys()->filter(fn ($k) => $k > now()->toDateString())->max();
        $last = $futureLeave ? Carbon::parse($futureLeave) : ($to->isFuture() ? now()->startOfDay() : $to->copy()->startOfDay());
        for ($d = $last->copy(); $d->gte($from); $d->subDay()) {
            $key = $d->toDateString();
            $days->push(AttendanceReport::evaluate($user, $d->copy(), $recs->get($key, collect()), $s, $excuses->get($key)) + ['date' => $d->copy()]);
        }

        return view('admin.attendance.person', compact('user', 'days', 'y', 'm', 'from'));
    }

    // ===== رموز QR =====
    public function points()
    {
        return view('admin.attendance.points', [
            'points' => AttendancePoint::withCount('records')->orderByDesc('is_active')->orderBy('name')->get(),
            'settings' => AttendanceReport::settings(),
        ]);
    }

    public function storePoint(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']], [], ['name' => 'اسم المكان']);
        $p = AttendancePoint::create(['name' => $data['name'], 'token' => AttendancePoint::newToken()]);

        return redirect()->route('admin.attendance.points.print', $p)->with('status', 'أُنشئ رمز QR «'.$p->name.'». اطبعه وعلّقه في مكانه.');
    }

    public function updatePoint(Request $request, AttendancePoint $point)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['boolean'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'radius' => ['nullable', 'integer', 'min:30', 'max:5000'],
        ]);
        $point->update([
            'name' => $data['name'], 'is_active' => $request->boolean('is_active'),
            'lat' => $data['lat'] ?? null, 'lng' => $data['lng'] ?? null, 'radius' => $data['radius'] ?? 150,
        ]);

        return back()->with('status', 'تم حفظ «'.$point->name.'».');
    }

    /** رمز جديد: الرمز المطبوع القديم يتوقف فوراً (مثلاً إذا صُوّر ونُشر) */
    public function regenerate(AttendancePoint $point)
    {
        $point->update(['token' => AttendancePoint::newToken()]);

        return redirect()->route('admin.attendance.points.print', $point)->with('warning', 'تم تغيير الرمز. الرمز المطبوع القديم لم يعد يعمل؛ اطبع هذا الرمز الجديد وضعه مكانه.');
    }

    /** حذف رمز QR: يتوقف المطبوع فوراً، وتبقى التسجيلات السابقة محفوظة */
    public function destroyPoint(AttendancePoint $point)
    {
        $name = $point->name;
        $point->delete();

        return redirect()->route('admin.attendance.points')->with('status', "حُذف رمز «{$name}». لم يعد الرمز المطبوع يعمل، وتسجيلات الحضور السابقة بقيت محفوظة.");
    }

    /**
     * تعديل يوم مدرس: وقت الحضور، وقت المغادرة، والعذر (غائب بعذر / مجاز) مع ملاحظة.
     * يُحفظ الوقت الأصلي عند التعديل، وتفريغ الخانة يحذف التسجيل.
     */
    public function updateDay(Request $request, User $user)
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:'.now()->addDays(60)->toDateString()],
            'in' => ['nullable', 'date_format:H:i'],
            'out' => ['nullable', 'date_format:H:i'],
            'excused' => ['boolean'],
            'excuse_note' => ['nullable', 'string', 'max:500'],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [], ['in' => 'وقت الحضور', 'out' => 'وقت المغادرة']);
        if (! empty($data['in']) && ! empty($data['out']) && $data['out'] <= $data['in']) {
            return back()->with('warning', 'وقت المغادرة يجب أن يكون بعد وقت الحضور.');
        }
        $date = Carbon::parse($data['date'])->startOfDay();
        $admin = $request->user();
        $changes = [];

        $recs = AttendanceRecord::where('user_id', $user->id)->whereDate('occurred_at', $date->toDateString())->orderBy('occurred_at')->get();
        foreach (['in' => $recs->firstWhere('type', 'in'), 'out' => $recs->where('type', 'out')->last()] as $type => $rec) {
            $label = $type === 'in' ? 'الحضور' : 'المغادرة';
            $time = $data[$type] ?? null;
            if ($time) {
                $at = $date->copy()->setTimeFromTimeString($time);
                if ($rec) {
                    if ($rec->occurred_at->format('H:i') !== $time) {
                        $rec->forceFill(['original_at' => $rec->original_at ?? $rec->occurred_at, 'occurred_at' => $at, 'edited_by' => $admin->id, 'edited_at' => now(),
                            'note' => $data['reason'] ?? $rec->note])->save();
                        $changes[] = "عُدّل {$label} إلى {$time}";
                    }
                } else {
                    AttendanceRecord::create(['user_id' => $user->id, 'type' => $type, 'occurred_at' => $at, 'synced_at' => now(),
                        'source' => 'manual', 'note' => $data['reason'] ?? 'أُضيف من الإدارة', 'created_by' => $admin->id]);
                    $changes[] = "أُضيف {$label} {$time}";
                }
            } elseif ($rec) {
                // تفريغ الخانة = حذف تسجيلات هذا النوع في ذلك اليوم
                AttendanceRecord::where('user_id', $user->id)->whereDate('occurred_at', $date->toDateString())->where('type', $type)->delete();
                $changes[] = "حُذف {$label}";
            }
        }

        $excuse = AttendanceExcuse::where('user_id', $user->id)->whereDate('date', $date->toDateString())->first();
        if ($request->boolean('excused')) {
            $note = trim((string) ($data['excuse_note'] ?? '')) ?: null;
            if (! $excuse) {
                AttendanceExcuse::create(['user_id' => $user->id, 'date' => $date->toDateString(), 'note' => $note, 'created_by' => $admin->id]);
                $changes[] = 'سُجّل العذر';
            } elseif ($excuse->note !== $note) {
                $excuse->update(['note' => $note]);
                $changes[] = 'عُدّلت ملاحظة العذر';
            }
        } elseif ($excuse) {
            $excuse->delete();
            $changes[] = 'أُلغي العذر';
        }

        $who = $user->name.' – '.$date->locale('ar')->translatedFormat('l j/n');

        return back()->with('status', $changes ? $who.': '.implode('، ', $changes).'.' : 'لا تغييرات على '.$who.'.');
    }

    /** إجازة/عذر لعدة أيام دفعة واحدة (أيام الدوام فقط)، ويمكن تسجيلها مسبقاً */
    public function storeExcuse(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'from' => ['required', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [], ['user_id' => 'المدرس', 'from' => 'من تاريخ', 'to' => 'إلى تاريخ', 'note' => 'الملاحظة']);
        $s = AttendanceReport::settings();
        $from = Carbon::parse($data['from'])->startOfDay();
        $to = Carbon::parse($data['to'] ?? $data['from'])->startOfDay();
        if ($from->diffInDays($to) > 60) {
            return back()->with('warning', 'المدة طويلة جداً (أكثر من 60 يوماً).');
        }
        $n = 0;
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            if (! AttendanceReport::isWorkday($d, $s)) {
                continue;
            }
            AttendanceExcuse::updateOrCreate(['user_id' => $data['user_id'], 'date' => $d->toDateString()],
                ['note' => trim((string) ($data['note'] ?? '')) ?: null, 'created_by' => $request->user()->id]);
            $n++;
        }
        $u = User::find($data['user_id']);

        return back()->with('status', "سُجّل عذر/إجازة لـ{$u->name}: {$n} يوم دوام".($n === 0 ? ' (الأيام المختارة ليست أيام دوام)' : '').'.');
    }

    public function destroyExcuse(AttendanceExcuse $excuse)
    {
        $excuse->delete();

        return back()->with('status', 'أُلغي العذر.');
    }

    public function printPoint(AttendancePoint $point)
    {
        return view('admin.attendance.print', ['point' => $point, 'settings' => AttendanceReport::settings()]);
    }

    public function settings(Request $request)
    {
        $data = $request->validate([
            'start' => ['required', 'date_format:H:i'],
            'end' => ['required', 'date_format:H:i', 'after:start'],
            'grace' => ['required', 'integer', 'min:0', 'max:120'],
            'days' => ['required', 'array', 'min:1'],
            'days.*' => ['integer', 'between:0,6'],
        ], [], ['start' => 'بداية الدوام', 'end' => 'نهاية الدوام', 'grace' => 'دقائق السماح', 'days' => 'أيام الدوام']);
        Setting::put('attendance_start', $data['start']);
        Setting::put('attendance_end', $data['end']);
        Setting::put('attendance_grace', (string) $data['grace']);
        Setting::put('attendance_days', json_encode(array_values(array_map('intval', $data['days']))));

        return back()->with('status', 'تم حفظ أوقات الدوام.');
    }

    /** تسجيل يدوي من الإدارة (نسي الجوال، عطل…) */
    public function manual(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'type' => ['required', 'in:in,out'],
            'date' => ['required', 'date'],
            'time' => ['required', 'date_format:H:i'],
            'note' => ['required', 'string', 'min:3', 'max:500'],
        ], [], ['user_id' => 'المدرس', 'type' => 'النوع', 'time' => 'الوقت', 'note' => 'السبب']);
        AttendanceRecord::create([
            'user_id' => $data['user_id'], 'type' => $data['type'],
            'occurred_at' => Carbon::parse($data['date'].' '.$data['time']), 'synced_at' => now(),
            'source' => 'manual', 'note' => $data['note'], 'created_by' => $request->user()->id,
        ]);

        return back()->with('status', 'سُجّل '.($data['type'] === 'in' ? 'الحضور' : 'المغادرة').' يدوياً.');
    }

    public function destroyRecord(AttendanceRecord $record)
    {
        $record->delete();

        return back()->with('status', 'حُذف التسجيل.');
    }

    private function ym(Request $request): array
    {
        $v = $request->query('month', now()->format('Y-m'));
        [$y, $m] = array_map('intval', explode('-', preg_match('/^\d{4}-\d{2}$/', $v) ? $v : now()->format('Y-m')));

        return [$y, max(1, min(12, $m))];
    }
}
