<?php

namespace App\Modules\Associations\Models;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Jenssegers\Mongodb\Eloquent\Model;

class Association extends Model
{
    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_ACTIF      = 'actif';
    public const STATUT_SUSPENDU   = 'suspendu';

    public const STATUTS = [
        self::STATUT_EN_ATTENTE,
        self::STATUT_ACTIF,
        self::STATUT_SUSPENDU,
    ];

    public const TYPES_TEXTILE = [
        'Hauts (t-shirts, chemises)',
        'Pulls & sweats',
        'Pantalons & jeans',
        'Robes & jupes',
        'Vêtements enfants',
        'Manteaux & vestes',
        'Chaussures',
        'Accessoires',
        'Linge de maison',
        'Tissus & matières',
    ];

    public const TAILLES = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'Enfant', 'Toutes tailles'];

    protected $connection = 'mongodb';
    protected $collection = 'associations';

    protected $fillable = [
        'user_id',
        'nom',
        'description',
        'adresse',
        'telephone',
        'besoins',   // JSON array: [['type'=>'...','taille'=>'...','quantite'=>N], ...]
        'statut',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'statut'  => self::STATUT_EN_ATTENTE,
        'besoins' => [],
    ];

    /* ── Relations ─────────────────────────────────────────────────── */

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', '_id');
    }

    public function dons(): HasMany
    {
        return $this->hasMany(\App\Modules\Dons\Models\Don::class, 'association_id', '_id');
    }

    /* ── Helpers ────────────────────────────────────────────────────── */

    public function statutLabel(): string
    {
        return match ($this->statut) {
            self::STATUT_ACTIF     => 'Actif',
            self::STATUT_SUSPENDU  => 'Suspendu',
            default                => 'En attente',
        };
    }

    public function statutTone(): string
    {
        return match ($this->statut) {
            self::STATUT_ACTIF    => 'green',
            self::STATUT_SUSPENDU => 'gray',
            default               => 'orange',
        };
    }

    /** Résumé court des besoins pour l'affichage. */
    public function besoinsSummary(): string
    {
        $items = $this->besoins ?? [];
        if (empty($items)) {
            return 'Aucun besoin renseigné';
        }

        return implode(' · ', array_map(
            fn ($b) => ($b['type'] ?? '?').($b['taille'] ? ' ('.$b['taille'].')' : ''),
            array_slice($items, 0, 3)
        ));
    }
}
