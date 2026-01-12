<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sprint extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'starts_at',
        'ends_at',
        'closed_at',
        'trello_board_id',
        'done_list_ids',
        'notes',
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
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'closed_at' => 'datetime',
            'done_list_ids' => 'array',
        ];
    }

    public function sprintCloseSnapshots(): HasMany
    {
        return $this->hasMany(SprintCloseSnapshot::class);
    }

    public function dailySprintSnapshots(): HasMany
    {
        return $this->hasMany(DailySprintSnapshot::class);
    }

    public function reportRuns(): HasMany
    {
        return $this->hasMany(ReportRun::class);
    }
}
