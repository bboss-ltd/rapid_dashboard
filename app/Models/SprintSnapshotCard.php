<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SprintSnapshotCard extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'sprint_snapshot_id',
        'card_id',
        'trello_list_id',
        'estimate_points',
        'is_done',
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
            'sprint_snapshot_id' => 'integer',
            'card_id' => 'integer',
            'is_done' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(SprintSnapshot::class, 'sprint_snapshot_id');
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}
