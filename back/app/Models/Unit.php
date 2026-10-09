<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Unit extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'abbreviation',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    protected $attributes = [
        'status' => true,
    ];

    protected static function booted(): void
    {
        static::creating(function (Unit $unit): void {
            if (empty($unit->{$unit->getKeyName()})) {
                $unit->{$unit->getKeyName()} = (string) Str::uuid();
            }
        });
    }
}
