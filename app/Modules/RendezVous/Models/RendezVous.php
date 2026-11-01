<?php

namespace App\Modules\RendezVous\Models;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Models\Service;
use App\Modules\Auth\Models\User;
use App\Modules\Vetements\Models\Vetement;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Jenssegers\Mongodb\Eloquent\Model;
use Throwable;

/**
 * Modèle RendezVous — représente un rendez-vous de réparation.
 *
 * Champs : vetement_id, atelier_id, service_id, user_id,
 *          date_rdv, duree, statut, commentaire.
 */
class RendezVous extends Model
{
    /* ---------------------------------------------------------------
     |  Constantes de statut
     |--------------------------------------------------------------- */
    public const STATUT_EN_ATTENTE = 'en_attente';

    public const STATUT_CONFIRME = 'confirme';

    public const STATUT_ANNULE = 'annule';

    public const STATUT_TERMINE = 'termine';

    public const STATUTS = [
        self::STATUT_EN_ATTENTE,
        self::STATUT_CONFIRME,
        self::STATUT_ANNULE,
        self::STATUT_TERMINE,
    ];

    /** Liste prédéfinie des ateliers disponibles (id => nom) */
    public const ATELIERS = [
        'couture-plus'   => 'Couture Plus',
        'atelier-vert'   => "L'Atelier Vert",
        'fil-et-aiguille' => 'Fil & Aiguille',
    ];

    /** Liste prédéfinie des services disponibles (id => nom) */
    public const SERVICES = [
        'retouche-simple'    => 'Retouche simple',
        'reparation-denim'   => 'Réparation denim',
        'upcycling'          => 'Upcycling créatif',
        'raccommodage'       => 'Raccommodage',
        'ajustement-taille'  => 'Ajustement de taille',
    ];

    /**
     * Ateliers proposés au formulaire : ateliers actifs en base, sinon la liste de démonstration.
     *
     * @return array<string, string>
     */
    public static function catalogueAteliers(): array
    {
        try {
            $reels = Atelier::where('statut', Atelier::STATUT_ACTIF)->orderBy('nom')->get();
            if ($reels->isNotEmpty()) {
                return $reels->mapWithKeys(fn (Atelier $atelier) => [(string) $atelier->getKey() => $atelier->nom])->all();
            }
        } catch (Throwable $e) {
            // Mongo indisponible : on retombe sur la liste de démonstration.
        }

        return self::ATELIERS;
    }

    /**
     * Services proposés au formulaire : catalogue réel des ateliers actifs, sinon la liste de démonstration.
     *
     * @return array<string, string>
     */
    public static function catalogueServices(): array
    {
        try {
            $ateliers = Atelier::where('statut', Atelier::STATUT_ACTIF)->orderBy('nom')->get();
            if ($ateliers->isNotEmpty()) {
                $services = [];
                foreach ($ateliers as $atelier) {
                    foreach ($atelier->services as $service) {
                        $services[(string) $service->getKey()] = $atelier->nom.' — '.$service->nom;
                    }
                }
                if ($services !== []) {
                    return $services;
                }
            }
        } catch (Throwable $e) {
            // Mongo indisponible : on retombe sur la liste de démonstration.
        }

        return self::SERVICES;
    }

    /* ---------------------------------------------------------------
     |  Configuration MongoDB
     |--------------------------------------------------------------- */
    protected $connection = 'mongodb';

    protected $collection = 'rendez_vous';

    protected $fillable = [
        'vetement_id',
        'atelier_id',
        'service_id',
        'user_id',
        'date_rdv',
        'duree',
        'statut',
        'commentaire',
        'annule_par',
        'replanifie_id',
    ];

    protected $casts = [
        'date_rdv'   => 'datetime',
        'duree'      => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'statut' => self::STATUT_EN_ATTENTE,
    ];

    /* ---------------------------------------------------------------
     |  Relations
     |--------------------------------------------------------------- */

    /** Le vêtement concerné par le rendez-vous */
    public function vetement(): BelongsTo
    {
        return $this->belongsTo(Vetement::class, 'vetement_id', '_id');
    }

    /** L'utilisateur propriétaire du rendez-vous */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', '_id');
    }

    public function atelier(): BelongsTo
    {
        return $this->belongsTo(Atelier::class, 'atelier_id', '_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id', '_id');
    }

    /* ---------------------------------------------------------------
     |  Accesseurs & helpers
     |--------------------------------------------------------------- */

    /** Libellé lisible du statut */
    public function statutLabel(): string
    {
        return match ($this->statut) {
            self::STATUT_CONFIRME  => 'Confirmé',
            self::STATUT_ANNULE   => 'Annulé',
            self::STATUT_TERMINE  => 'Terminé',
            default               => 'En attente',
        };
    }

    /** Couleur (tone) associée au statut pour le badge */
    public function statutTone(): string
    {
        return match ($this->statut) {
            self::STATUT_CONFIRME => 'green',
            self::STATUT_ANNULE  => 'red',
            self::STATUT_TERMINE => 'gray',
            default              => 'orange',
        };
    }

    /** Nom de l'atelier à partir de l'ID (liste de démo ou fiche atelier réelle). */
    public function atelierNom(): string
    {
        if (isset(self::ATELIERS[$this->atelier_id])) {
            return self::ATELIERS[$this->atelier_id];
        }

        try {
            $nom = $this->atelier?->nom;
            if (is_string($nom) && $nom !== '') {
                return $nom;
            }
        } catch (Throwable $e) {
        }

        return $this->atelier_id ?? '—';
    }

    /** Nom du service à partir de l'ID (liste de démo ou prestation réelle). */
    public function serviceNom(): string
    {
        if (empty($this->service_id)) {
            return '—';
        }

        if (isset(self::SERVICES[$this->service_id])) {
            return self::SERVICES[$this->service_id];
        }

        try {
            $nom = $this->service?->nom;
            if (is_string($nom) && $nom !== '') {
                return $nom;
            }
        } catch (Throwable $e) {
        }

        return $this->service_id;
    }

    /** Durée formatée en heures et minutes */
    public function dureeFormatee(): string
    {
        $heures = intdiv($this->duree, 60);
        $minutes = $this->duree % 60;

        if ($heures > 0 && $minutes > 0) {
            return "{$heures}h{$minutes}min";
        }

        if ($heures > 0) {
            return "{$heures}h";
        }

        return "{$minutes} min";
    }

    /* ---------------------------------------------------------------
     |  Vérifications de permissions par statut
     |--------------------------------------------------------------- */

    /** Vérifie si le RDV peut être modifié */
    public function peutEtreModifie(): bool
    {
        return $this->statut === self::STATUT_EN_ATTENTE;
    }

    /** Vérifie si le RDV peut être annulé */
    public function peutEtreAnnule(): bool
    {
        return in_array($this->statut, [
            self::STATUT_EN_ATTENTE,
            self::STATUT_CONFIRME,
        ], true);
    }

    /** Vérifie si le RDV peut être supprimé */
    public function peutEtreSupprime(): bool
    {
        return in_array($this->statut, [
            self::STATUT_EN_ATTENTE,
            self::STATUT_ANNULE,
        ], true);
    }

    /** Annulé et pas encore remplacé par un nouveau RDV. */
    public function peutEtreReplanifie(): bool
    {
        return $this->statut === self::STATUT_ANNULE && empty($this->replanifie_id);
    }

    /** L'annulation vient de l'atelier ou de l'administration, pas du citoyen. */
    public function annuleParLatelier(): bool
    {
        return in_array($this->annule_par, ['atelier', 'admin'], true);
    }

    /**
     * Transitions autorisées pour l'atelier qui reçoit le rendez-vous.
     *
     * @return list<string>
     */
    public function transitionsAtelier(): array
    {
        return match ($this->statut) {
            self::STATUT_EN_ATTENTE => [self::STATUT_CONFIRME, self::STATUT_ANNULE],
            self::STATUT_CONFIRME => [self::STATUT_TERMINE, self::STATUT_ANNULE],
            default => [],
        };
    }

    public function appartientALatelier(string $atelierId): bool
    {
        return $this->atelier_id !== null && (string) $this->atelier_id === (string) $atelierId;
    }

    /**
     * @return array<string, string>
     */
    public static function catalogueServicesPourAtelier(?string $atelierId): array
    {
        if ($atelierId === null || $atelierId === '') {
            return self::catalogueServices();
        }

        try {
            $atelier = Atelier::query()->with('services')->find($atelierId);
            if ($atelier && $atelier->services->isNotEmpty()) {
                return $atelier->services
                    ->mapWithKeys(fn (Service $service) => [(string) $service->getKey() => $service->nom])
                    ->all();
            }
        } catch (Throwable $e) {
        }

        return self::catalogueServices();
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function catalogueServicesParAtelier(): array
    {
        try {
            $ateliers = Atelier::query()->where('statut', Atelier::STATUT_ACTIF)->with('services')->orderBy('nom')->get();
            $map = [];
            foreach ($ateliers as $atelier) {
                $map[(string) $atelier->getKey()] = $atelier->services
                    ->mapWithKeys(fn (Service $service) => [(string) $service->getKey() => $service->nom])
                    ->all();
            }
            if ($map !== []) {
                return $map;
            }
        } catch (Throwable $e) {
        }

        return [];
    }
}
