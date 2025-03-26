<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CapacityUptake extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'room_id',
        'week',
        'port',
        'accepted_cards',
        'rejected_cards',
        'dry_containers_accepted',
        'reefer_containers_accepted',
        'committed_containers_accepted',
        'non_committed_containers_accepted',
        'dry_containers_rejected',
        'reefer_containers_rejected',
        'committed_containers_rejected',
        'non_committed_containers_rejected'
    ];

    protected $casts = [
        'accepted_cards' => 'array',
        'rejected_cards' => 'array',
        'dry_containers_accepted' => 'integer',
        'reefer_containers_accepted' => 'integer',
        'committed_containers_accepted' => 'integer',
        'non_committed_containers_accepted' => 'integer',
        'dry_containers_rejected' => 'integer',
        'reefer_containers_rejected' => 'integer',
        'committed_containers_rejected' => 'integer',
        'non_committed_containers_rejected' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}
