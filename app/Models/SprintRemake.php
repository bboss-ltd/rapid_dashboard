<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SprintRemake extends Model
{
    protected $fillable = [
        'sprint_id',
        'card_id',
        'trello_card_id',
        'estimate_points',
        'label_name',
        'label_points',
        'label_set_at',
        'reason_label',
        'reason_set_at',
        'first_seen_at',
        'last_seen_at',
        'removed_at',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'removed_at' => 'datetime',
        'label_set_at' => 'datetime',
        'reason_set_at' => 'datetime',
    ];
}
