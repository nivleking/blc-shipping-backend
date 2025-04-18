<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Container extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'card_id',
        'deck_id',
        'color',
        'type',
        'last_processed_by',
        'last_processed_at',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function card()
    {
        return $this->belongsTo(Card::class, 'card_id', 'id')
            ->where('deck_id', $this->deck_id);
    }
}
