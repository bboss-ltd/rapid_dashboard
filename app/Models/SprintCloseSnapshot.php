<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SprintCloseSnapshot extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'sprint_id',
        'closed_at',
        'committed_points',
        'completed_points',
        'scope_points',
        'committed_card_ids',
        'completed_card_ids',
        'scope_card_ids',
        'meta',
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
            'sprint_id' => 'integer',
            'closed_at' => 'datetime',
            'committed_card_ids' => 'array',
            'completed_card_ids' => 'array',
            'scope_card_ids' => 'array',
            'meta' => 'array',
        ];
    }

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }
}
