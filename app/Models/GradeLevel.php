<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class GradeLevel extends Model
{
    protected $fillable = ['name', 'sort_order', 'split_by_gender'];

    protected function casts(): array
    {
        return ['split_by_gender' => 'boolean'];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('name');
    }

    public function students(): HasManyThrough
    {
        return $this->hasManyThrough(Student::class, Section::class);
    }
}
