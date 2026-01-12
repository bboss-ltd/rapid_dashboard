<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrelloAction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'trello_action_id',
        'trello_board_id',
        'trello_card_id',
        'type',
        'occurred_at',
        'payload',
        'processed_at',
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
            'occurred_at' => 'datetime',
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
