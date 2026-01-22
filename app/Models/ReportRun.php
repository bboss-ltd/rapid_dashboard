<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportRun extends Model
{
    use HasFactory;

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

    protected function casts(): array
    {
        return [
            'params' => 'array',
            'snapshot_ref' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function setParamsAttribute($value): void
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;
        $this->attributes['params'] = json_encode($decoded ?? []);
    }

    public function setSnapshotRefAttribute($value): void
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;
        $this->attributes['snapshot_ref'] = $decoded === null ? null : json_encode($decoded);
    }
}
