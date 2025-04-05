<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'deck_id',
        'type',
        'priority',
        'origin',
        'destination',
        'quantity',
        'revenue',
        'is_committed',
        'is_initial',
        'generated_for_room_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'revenue' => 'integer',
        'is_committed' => 'boolean',
        'is_initial' => 'boolean',
    ];

    public function getRouteKeyName()
    {
        return 'id';
    }

    public static function findByKeys($id, $deckId)
    {
        return static::where('id', $id)->where('deck_id', $deckId)->first();
    }

    public function deck()
    {
        return $this->belongsTo(Deck::class);
    }

    public function containers()
    {
        return $this->hasMany(Container::class)
            ->where('deck_id', $this->deck_id);
    }
}
