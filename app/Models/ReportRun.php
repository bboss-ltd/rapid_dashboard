<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportRun extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'report_definition_id',
        'sprint_id',
        'status',
        'params',
        'snapshot_ref',
        'output_format',
        'output_path',
        'started_at',
        'finished_at',
        'error_message',
        'requested_by_user_id',
        'user_id',
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
            'sprint_id' => 'integer',
            'params' => 'array',
            'snapshot_ref' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'requested_by_user_id' => 'integer',
            'user_id' => 'integer',
        ];
    }

    public function reportDefinition(): BelongsTo
    {
        return $this->belongsTo(ReportDefinition::class);
    }

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
