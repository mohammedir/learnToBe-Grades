<?php

namespace App\Services;

use App\Models\AttendanceExcuse;
use App\Models\AttendanceRecord;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** حساب حضور المدرسين والموظفين: يومياً وشهرياً */
class AttendanceReport
{
    public const DAYS = [6 => 'السبت', 0 => 'الأحد', 1 => 'الاثنين', 2 => 'الثلاثاء', 3 => 'الأربعاء', 4 => 'الخميس', 5 => 'الجمعة'];

    public static function settings(): array
    {
        $days = json_decode((string) Setting::get('attendance_days', ''), true);

        return [
            'start' => (string) (Setting::get('attendance_start') ?: '07:30'),
            'end' => (string) (Setting::get('attendance_end') ?: '13:00'),
            'grace' => (int) (Setting::get('attendance_grace') ?? 10),
            'days' => is_array($days) ? array_map('intval', $days) : [6, 0, 1, 2, 3, 4],
        ];
    }

    /** من يُتابَع حضوره: المدرسون والموظفون المفعّلون */
    public static function people(): Collection
    {
        return User::whereIn('role', ['teacher', 'staff'])->where('is_active', true)
            ->orderByRaw("CASE role WHEN 'teacher' THEN 0 ELSE 1 END")->orderBy('name')->get();
    }

    public static function isWorkday(Carbon $d, ?array $s = null): bool
    {
        return in_array($d->dayOfWeek, ($s ?? self::settings())['days'], true);
    }

    /** صف لكل شخص في يوم معيّن */
    public static function day(Carbon $date, ?Collection $people = null): Collection
    {
        $s = self::settings();
        $people ??= self::people();
        $records = AttendanceRecord::with(['point', 'creator', 'editor'])->whereDate('occurred_at', $date->toDateString())
            ->whereIn('user_id', $people->pluck('id'))->orderBy('occurred_at')->get()->groupBy('user_id');
        $excuses = AttendanceExcuse::with('creator')->whereDate('date', $date->toDateString())->whereIn('user_id', $people->pluck('id'))->get()->keyBy('user_id');

        return $people->map(fn ($u) => self::evaluate($u, $date, $records->get($u->id, collect()), $s, $excuses->get($u->id)));
    }

    public static function evaluate(User $u, Carbon $date, Collection $recs, array $s, ?AttendanceExcuse $excuse = null): array
    {
        $in = $recs->firstWhere('type', 'in');
        $out = $recs->where('type', 'out')->last();
        $start = $date->copy()->setTimeFromTimeString($s['start']);
        $end = $date->copy()->setTimeFromTimeString($s['end']);
        $now = now();
        $workday = self::isWorkday($date, $s);

        $late = $in ? max(0, (int) floor($start->diffInMinutes($in->occurred_at, false))) : 0;
        $early = $out ? max(0, (int) floor($out->occurred_at->diffInMinutes($end, false))) : 0;

        if ($in) {
            $status = $late > $s['grace'] ? 'late' : 'present';
        } elseif (! $workday) {
            $status = $out ? 'present' : 'off';
        } elseif ($date->isFuture() || ($date->isToday() && $now->lt($end))) {
            $status = $out ? 'present' : 'waiting';
        } else {
            $status = $out ? 'present' : 'absent';
        }
        $noOut = $in && ! $out && ($date->lt($now->copy()->startOfDay()) || $now->gt($end->copy()->addHour()));
        // العذر: الغائب يصبح «غائب بعذر (مجاز)»، والمتأخر/الخارج مبكراً يُعلَّم «بعذر» ولا يُحتسب عليه
        if ($excuse && $workday && in_array($status, ['absent', 'waiting'], true) && ! $in) {
            $status = 'excused';
        }

        return [
            'user' => $u,
            'in' => $in,
            'out' => $out,
            'records' => $recs,
            'status' => $status,
            'late' => $status === 'late' ? $late : 0,
            'early' => $out && $early > $s['grace'] ? $early : 0,
            'no_out' => $noOut,
            'minutes' => $in && $out && $out->occurred_at->gt($in->occurred_at) ? (int) $in->occurred_at->diffInMinutes($out->occurred_at) : null,
            'flags' => $recs->flatMap(fn ($r) => $r->flags())->unique(0)->values(),
            'workday' => $workday,
            'excuse' => $excuse,
        ];
    }

    public const STATUS = [
        'present' => ['حاضر', 'done'],
        'late' => ['متأخر', 'part'],
        'absent' => ['غائب', 'off'],
        'excused' => ['غائب بعذر (مجاز)', 'info'],
        'waiting' => ['لم يسجّل بعد', 'none'],
        'off' => ['عطلة', 'none'],
    ];

    /** ملخص شهر لكل شخص */
    public static function month(int $year, int $month): array
    {
        $s = self::settings();
        $people = self::people();
        $from = Carbon::create($year, $month, 1)->startOfDay();
        $to = $from->copy()->endOfMonth();
        $last = $to->isFuture() ? now()->startOfDay() : $to->copy()->startOfDay();
        $records = AttendanceRecord::whereBetween('occurred_at', [$from, $to])->whereIn('user_id', $people->pluck('id'))
            ->orderBy('occurred_at')->get()->groupBy(fn ($r) => $r->user_id.'|'.$r->occurred_at->toDateString());
        $excuses = AttendanceExcuse::whereBetween('date', [$from->toDateString(), $to->toDateString()])->whereIn('user_id', $people->pluck('id'))
            ->get()->keyBy(fn ($e) => $e->user_id.'|'.$e->date->toDateString());

        $days = [];
        for ($d = $from->copy(); $d->lte($last); $d->addDay()) {
            $days[] = $d->copy();
        }
        $workdays = collect($days)->filter(fn ($d) => self::isWorkday($d, $s))->count();

        $rows = $people->map(function ($u) use ($days, $records, $excuses, $s, $workdays) {
            $t = ['user' => $u, 'present' => 0, 'late' => 0, 'late_minutes' => 0, 'absent' => 0, 'excused' => 0, 'early' => 0, 'no_out' => 0, 'minutes' => 0, 'flags' => 0, 'cells' => []];
            foreach ($days as $d) {
                $key = $u->id.'|'.$d->toDateString();
                $e = self::evaluate($u, $d, $records->get($key, collect()), $s, $excuses->get($key));
                $t['cells'][$d->day] = $e['status'];
                if (in_array($e['status'], ['present', 'late'], true)) {
                    $t['present']++;
                }
                // التأخير أو الخروج المبكر بعذر لا يُحتسب
                if ($e['status'] === 'late' && ! $e['excuse']) {
                    $t['late']++;
                    $t['late_minutes'] += $e['late'];
                }
                $t['absent'] += $e['status'] === 'absent' ? 1 : 0;
                $t['excused'] += $e['status'] === 'excused' && $e['workday'] ? 1 : 0;
                $t['early'] += $e['early'] && ! $e['excuse'] ? 1 : 0;
                $t['no_out'] += $e['no_out'] ? 1 : 0;
                $t['minutes'] += (int) $e['minutes'];
                $t['flags'] += $e['flags']->count() ? 1 : 0;
            }
            $due = $workdays - $t['excused']; // أيام الإجازة بعذر لا تُحسب من أيام الدوام المطلوبة
            $t['rate'] = $due > 0 ? (int) round(min($t['present'], $due) / $due * 100) : null;

            return $t;
        });

        return ['rows' => $rows, 'days' => $days, 'workdays' => $workdays, 'from' => $from, 'settings' => $s];
    }
}
