<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_definition_id',
        'name',
        'is_enabled',
        'cron',
        'timezone',
        'default_params',
        'last_ran_at',
        'next_run_at',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'default_params' => 'array',
            'last_ran_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    public function setDefaultParamsAttribute($value): void
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;
        $this->attributes['default_params'] = json_encode($decoded ?? []);
    }
}
