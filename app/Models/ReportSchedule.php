<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportSchedule extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
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

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'report_definition_id' => 'integer',
            'is_enabled' => 'boolean',
            'default_params' => 'array',
            'last_ran_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    public function reportDefinition(): BelongsTo
    {
        return $this->belongsTo(ReportDefinition::class);
    }
}
