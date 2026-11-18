<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** عذر أو إجازة لمدرس في يوم معيّن (غائب بعذر / مجاز) */
class AttendanceExcuse extends Model
{
    protected $fillable = ['user_id', 'date', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
