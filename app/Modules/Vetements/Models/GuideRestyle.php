<?php

namespace App\Modules\Vetements\Models;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Jenssegers\Mongodb\Eloquent\Model;

class GuideRestyle extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'guides_restyle';

    protected $fillable = [
        'user_id',
        'vetement_id',
        'titre',
        'description',
        'etat_avant',
        'etat_apres',
        'instruction',
        'image_avant',
        'image_apres',
        'envoye_a',
        'envoye_le',
    ];

    protected $casts = [
        'envoye_le' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', '_id');
    }
}
