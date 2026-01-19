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
        'status',
        'trello_board_id',
        'starts_at',
        'ends_at',
        'closed_at',
        'done_list_ids',
        'trello_registry_card_id',
        'remakes_list_id',
        'sprint_goal',
        'trello_control_card_id',
        'trello_status_custom_field_id',
        'trello_closed_option_id',
        'last_polled_at',
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
            'last_polled_at' => 'datetime',
        ];
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(SprintSnapshot::class);
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    public function scopeActive($query)
    {
        $now = now();

        return $query->whereNull('closed_at')
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->orderByDesc('starts_at');
    }
}
