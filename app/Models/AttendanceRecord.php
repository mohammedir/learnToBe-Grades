<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    protected $fillable = ['user_id', 'attendance_point_id', 'type', 'occurred_at', 'device_at', 'skew_seconds', 'synced_at', 'offline',
        'lat', 'lng', 'accuracy', 'distance', 'source', 'client_uuid', 'note', 'created_by', 'original_at', 'edited_by', 'edited_at'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'original_at' => 'datetime', 'edited_at' => 'datetime', 'device_at' => 'datetime', 'synced_at' => 'datetime', 'offline' => 'boolean',
            'lat' => 'float', 'lng' => 'float', 'skew_seconds' => 'integer', 'distance' => 'integer'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function point(): BelongsTo { return $this->belongsTo(AttendancePoint::class, 'attendance_point_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function editor(): BelongsTo { return $this->belongsTo(User::class, 'edited_by'); }

    /** ملاحظات للمراجعة: سُجّل بلا إنترنت، فرق في ساعة الجهاز، بعيد عن المدرسة، يدوي */
    public function flags(): array
    {
        $f = [];
        if ($this->source === 'manual') {
            $f[] = ['يدوي'.($this->creator ? ' – '.$this->creator->name : ''), 'none'];
        }
        if ($this->edited_by) {
            $f[] = ['عُدّل'.($this->editor ? ' – '.$this->editor->name : '').($this->original_at ? ' (كان '.$this->original_at->format('H:i').')' : ''), 'none'];
        }
        if ($this->offline) {
            $f[] = ['بلا إنترنت، رُفع '.($this->synced_at?->format('H:i') ?? ''), 'info'];
        }
        if (abs($this->skew_seconds) > 120) {
            $f[] = ['ساعة الجهاز مختلفة '.self::mins(abs($this->skew_seconds)), 'part'];
        }
        if ($this->distance !== null && $this->point && $this->distance > $this->point->radius) {
            $f[] = ['بعيد عن المدرسة '.($this->distance >= 1000 ? round($this->distance / 1000, 1).' كم' : $this->distance.' م'), 'off'];
        }

        return $f;
    }

    public static function mins(int $seconds): string
    {
        $m = intdiv($seconds, 60);

        return $m >= 60 ? intdiv($m, 60).' س '.($m % 60).' د' : max(1, $m).' د';
    }
}
