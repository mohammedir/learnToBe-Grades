<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** رمز QR مطبوع يمسحه المدرس لتسجيل الحضور أو المغادرة */
class AttendancePoint extends Model
{
    protected $fillable = ['name', 'token', 'lat', 'lng', 'radius', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'lat' => 'float', 'lng' => 'float', 'radius' => 'integer'];
    }

    public function records(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public static function newToken(): string
    {
        return Str::lower(Str::random(32));
    }

    /** النص المخزّن داخل رمز QR */
    public function qrText(): string
    {
        return 'LTB-ATT:'.$this->token;
    }

    /** المسافة بالمتر بين موقع ونقطة التسجيل (إن حُدد موقعها) */
    public function distanceTo(?float $lat, ?float $lng): ?int
    {
        if ($this->lat === null || $this->lng === null || $lat === null || $lng === null) {
            return null;
        }
        $r = 6371000;
        $dLat = deg2rad($lat - $this->lat);
        $dLng = deg2rad($lng - $this->lng);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($this->lat)) * cos(deg2rad($lat)) * sin($dLng / 2) ** 2;

        return (int) round($r * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
