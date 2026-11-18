<?php

namespace App\Http\Controllers;

use App\Models\AttendancePoint;
use App\Models\AttendanceRecord;
use App\Services\AttendanceReport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * تسجيل حضور المدرس/الموظف بمسح رمز QR.
 * يعمل بدون إنترنت: يُحفظ التسجيل في الجهاز ثم يُرفع عند الاتصال عبر sync.
 */
class AttendanceController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['teacher', 'staff', 'admin'], true), 403);
        $from = now()->startOfMonth();
        $s = AttendanceReport::settings();
        $recs = AttendanceRecord::where('user_id', $user->id)->where('occurred_at', '>=', $from)->orderBy('occurred_at')->get()
            ->groupBy(fn ($r) => $r->occurred_at->toDateString());
        $excuses = \App\Models\AttendanceExcuse::where('user_id', $user->id)->where('date', '>=', $from->toDateString())->get()
            ->keyBy(fn ($e) => $e->date->toDateString());
        $days = collect();
        for ($d = now()->startOfDay(); $d->gte($from); $d->subDay()) {
            $e = AttendanceReport::evaluate($user, $d->copy(), $recs->get($d->toDateString(), collect()), $s, $excuses->get($d->toDateString()));
            if ($e['records']->isNotEmpty() || ($e['workday'] && $e['status'] !== 'waiting')) {
                $days->push($e + ['date' => $d->copy()]);
            }
        }

        return view('attendance.scan', [
            'user' => $user,
            'points' => AttendancePoint::where('is_active', true)->get(['id', 'token', 'name']),
            'today' => $days->first(fn ($e) => $e['date']->isToday()),
            'days' => $days,
            'settings' => $s,
        ]);
    }

    /** حالة حديثة للصفحة المحفوظة في الجهاز: رمز CSRF جديد والمستخدم ونقاط التسجيل */
    public function state(Request $request)
    {
        return response()->json([
            'user' => $request->user()->id,
            'csrf' => csrf_token(),
            'now' => (int) round(microtime(true) * 1000),
            'points' => AttendancePoint::where('is_active', true)->get(['id', 'token', 'name']),
        ])->header('Cache-Control', 'no-store');
    }

    /** رفع التسجيلات المحفوظة في الجهاز */
    public function sync(Request $request)
    {
        $data = $request->validate([
            'now' => ['required', 'integer'],
            'records' => ['required', 'array', 'max:200'],
            'records.*.uuid' => ['required', 'string', 'max:64'],
            'records.*.type' => ['required', 'in:in,out'],
            'records.*.token' => ['required', 'string', 'max:64'],
            'records.*.at' => ['required', 'integer'],
            'records.*.online' => ['nullable', 'boolean'],
            'records.*.lat' => ['nullable', 'numeric', 'between:-90,90'],
            'records.*.lng' => ['nullable', 'numeric', 'between:-180,180'],
            'records.*.acc' => ['nullable', 'numeric', 'min:0'],
        ]);
        $user = $request->user();
        $serverMs = (int) round(microtime(true) * 1000);
        $skewMs = $serverMs - (int) $data['now']; // فرق ساعة الجهاز عن الخادم
        $points = AttendancePoint::all()->keyBy('token');
        $result = [];

        foreach ($data['records'] as $r) {
            $point = $points->get(preg_replace('/^LTB-ATT:/', '', $r['token']));
            if (! $point) {
                $result[] = ['uuid' => $r['uuid'], 'ok' => false, 'error' => 'رمز QR غير معروف'];
                continue;
            }
            if (! $point->is_active) {
                $result[] = ['uuid' => $r['uuid'], 'ok' => false, 'error' => 'رمز QR موقوف من الإدارة'];
                continue;
            }
            $device = Carbon::createFromTimestampMs($r['at'])->setTimezone(config('app.timezone'));
            $at = Carbon::createFromTimestampMs(min($r['at'] + $skewMs, $serverMs))->setTimezone(config('app.timezone'));
            if ($at->lt(now()->subDays(14))) {
                $result[] = ['uuid' => $r['uuid'], 'ok' => false, 'error' => 'تسجيل قديم جداً (أكثر من 14 يوماً)'];
                continue;
            }
            $lat = isset($r['lat']) ? (float) $r['lat'] : null;
            $lng = isset($r['lng']) ? (float) $r['lng'] : null;

            $rec = AttendanceRecord::firstOrCreate(
                ['user_id' => $user->id, 'client_uuid' => $r['uuid']],
                [
                    'attendance_point_id' => $point->id,
                    'type' => $r['type'],
                    'occurred_at' => $at,
                    'device_at' => $device,
                    'skew_seconds' => (int) round($skewMs / 1000),
                    'synced_at' => now(),
                    'offline' => ($serverMs - ($r['at'] + $skewMs)) > 10 * 60 * 1000,
                    'lat' => $lat, 'lng' => $lng,
                    'accuracy' => isset($r['acc']) ? (int) round($r['acc']) : null,
                    'distance' => $point->distanceTo($lat, $lng),
                    'source' => 'qr',
                ]
            );
            $result[] = ['uuid' => $r['uuid'], 'ok' => true, 'at' => $rec->occurred_at->format('Y-m-d H:i')];
        }

        return response()->json(['results' => $result]);
    }
}
