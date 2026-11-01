<?php

namespace App\Modules\Ateliers\Services;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Models\Passeport;
use App\Modules\Auth\Models\User;
use App\Modules\RendezVous\Models\RendezVous;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Passeports de réparation. Les rendez-vous (module d'un coéquipier) sont seulement lus :
 * un atelier génère le passeport d'une réparation terminée, n'importe qui le consulte par QR code.
 */
class PasseportService
{
    public const NB_LISTE = 50;

    /**
     * Réparations terminées de l'atelier, chacune avec son passeport s'il existe déjà.
     *
     * @return Collection<int, array{rdv: RendezVous, passeport: ?Passeport}>
     */
    public function reparationsDeLatelier(Atelier $atelier): Collection
    {
        $rdvs = RendezVous::query()
            ->where('atelier_id', (string) $atelier->getKey())
            ->where('statut', RendezVous::STATUT_TERMINE)
            ->with(['vetement', 'user', 'service'])
            ->orderByDesc('date_rdv')
            ->limit(self::NB_LISTE)
            ->get();

        $passeports = Passeport::query()
            ->whereIn('rendez_vous_id', $rdvs->map(fn (RendezVous $rdv) => (string) $rdv->getKey())->all())
            ->get()
            ->keyBy('rendez_vous_id');

        return $rdvs->map(fn (RendezVous $rdv) => [
            'rdv' => $rdv,
            'passeport' => $passeports->get((string) $rdv->getKey()),
        ])->values();
    }

    /**
     * Rendez-vous confirmés (pièce en cours de réparation) : pas encore de passeport possible,
     * l'atelier doit d'abord terminer le rendez-vous.
     *
     * @return Collection<int, RendezVous>
     */
    public function reparationsEnCours(Atelier $atelier): Collection
    {
        return RendezVous::query()
            ->where('atelier_id', (string) $atelier->getKey())
            ->where('statut', RendezVous::STATUT_CONFIRME)
            ->with(['vetement', 'user', 'service'])
            ->orderBy('date_rdv')
            ->limit(self::NB_LISTE)
            ->get();
    }

    /**
     * Crée le passeport d'une réparation terminée de l'atelier, ou rend celui qui existe (idempotent : le mot
     * et la garantie envoyés une seconde fois sont ignorés, l'instantané ne change plus).
     * Un rendez-vous d'un autre atelier ou non terminé donne une 404 : on ne révèle pas son existence.
     *
     * @param  array{mot_atelier?: ?string, garantie_jours?: int|string|null}  $complements
     */
    public function generer(Atelier $atelier, string $rdvId, array $complements = []): Passeport
    {
        $rdv = RendezVous::query()
            ->where('_id', $rdvId)
            ->where('atelier_id', (string) $atelier->getKey())
            ->where('statut', RendezVous::STATUT_TERMINE)
            ->with(['vetement', 'service'])
            ->firstOrFail();

        $existant = Passeport::query()->where('rendez_vous_id', (string) $rdv->getKey())->first();
        if ($existant) {
            return $existant;
        }

        // Un RDV « terminé » mais daté de demain ou plus tard : on ne certifie pas une réparation qui n'a pas eu lieu.
        if (self::estAVenir($rdv)) {
            throw ValidationException::withMessages([
                'rendez_vous_id' => "Ce rendez-vous est prévu le {$rdv->date_rdv->format('d/m/Y')} : le passeport sera possible à partir de ce jour.",
            ]);
        }

        return Passeport::query()->create([
            'code' => $this->nouveauCode(),
            'atelier_id' => (string) $atelier->getKey(),
            'rendez_vous_id' => (string) $rdv->getKey(),
            'vetement_id' => $rdv->vetement_id !== null ? (string) $rdv->vetement_id : null,
            'user_id' => $rdv->user_id !== null ? (string) $rdv->user_id : null,
            'instantane' => self::instantane($atelier, $rdv, $complements),
            'revoque_le' => null,
        ]);
    }

    /** Rendez-vous daté d'un jour futur (aujourd'hui reste possible : la réparation peut finir avant l'heure prévue). */
    public static function estAVenir(RendezVous $rdv): bool
    {
        return $rdv->date_rdv !== null && $rdv->date_rdv->copy()->startOfDay()->gt(today());
    }

    /**
     * Informations publiques figées : rien sur le client (ni nom, ni téléphone, ni commentaire du RDV).
     * Le mot de l'atelier et la garantie viennent du formulaire de génération (GenererPasseportRequest).
     *
     * @param  array{mot_atelier?: ?string, garantie_jours?: int|string|null}  $complements
     * @return array<string, string|int>
     */
    public static function instantane(Atelier $atelier, RendezVous $rdv, array $complements = []): array
    {
        $vetement = $rdv->vetement;
        $reparation = $rdv->serviceNom();
        $service = $rdv->service;
        $mot = trim((string) ($complements['mot_atelier'] ?? ''));

        return [
            'mot_atelier' => $mot,
            'garantie_jours' => (int) ($complements['garantie_jours'] ?? 0),
            'service_description' => trim((string) ($service?->description ?? '')),
            'service_duree' => (int) ($service?->duree_estimee ?? 0),
            'vetement' => (string) ($vetement?->type ?? ''),
            'taille' => (string) ($vetement?->size ?? ''),
            'matiere' => (string) ($vetement?->material ?? ''),
            // serviceNom() rend l'identifiant brut quand le service a été supprimé : on ne l'affiche pas.
            'reparation' => $reparation === '—' || preg_match('/^[0-9a-f]{24}$/i', $reparation) ? '' : $reparation,
            'atelier' => (string) $atelier->nom,
            'ville' => (string) $atelier->ville,
            'repare_le' => $rdv->date_rdv?->toDateString() ?? now()->toDateString(),
        ];
    }

    /**
     * Code lu dans une saisie libre : le code seul, ou l'adresse complète copiée sous le QR (QR abîmé, lien reçu).
     * Le numéro court (« AB3D-EF6H ») ne suffit pas : il ne contient que 8 des 22 caractères du code.
     */
    public static function extraireCode(string $saisie): ?string
    {
        $saisie = trim($saisie);

        if (preg_match('~/passeport/('.Passeport::FORMAT_CODE.')(?:[/?#]|$)~', $saisie, $trouve)) {
            return $trouve[1];
        }

        return preg_match('/^'.Passeport::FORMAT_CODE.'$/', $saisie) ? $saisie : null;
    }

    public function trouverParCode(string $code): ?Passeport
    {
        return Passeport::query()->where('code', $code)->first();
    }

    /**
     * Toutes les réparations certifiées de la même pièce (passeports non révoqués), de la plus ancienne à la plus récente.
     *
     * @return Collection<int, Passeport>
     */
    public function historique(Passeport $passeport): Collection
    {
        if (! $passeport->vetement_id) {
            return collect([$passeport]);
        }

        return Passeport::query()
            ->where('vetement_id', (string) $passeport->vetement_id)
            ->whereNull('revoque_le')
            ->get()
            ->sortBy(fn (Passeport $p) => $p->info('repare_le'))
            ->values();
    }

    /**
     * Ateliers des réparations affichées, pour leur réputation actuelle (note, nombre d'avis) : contrairement à
     * l'instantané, la note évolue avec les avis. Seuls les ateliers actifs sont rendus.
     *
     * @param  Collection<int, Passeport>  $passeports
     * @return Collection<string, Atelier>
     */
    public function ateliersDe(Collection $passeports): Collection
    {
        $ids = $passeports->map(fn (Passeport $p) => (string) $p->atelier_id)->filter()->unique()->values()->all();

        if ($ids === []) {
            return collect();
        }

        return Atelier::query()
            ->whereIn('_id', $ids)
            ->where('statut', Atelier::STATUT_ACTIF)
            ->get()
            ->keyBy(fn (Atelier $atelier) => (string) $atelier->getKey());
    }

    /**
     * @return Collection<int, Passeport>
     */
    public function pourClient(User $user): Collection
    {
        return Passeport::query()
            ->where('user_id', (string) $user->getKey())
            ->orderByDesc('created_at')
            ->limit(self::NB_LISTE)
            ->get();
    }

    /**
     * @return Collection<int, Passeport>
     */
    public function pourAdmin(): Collection
    {
        return Passeport::query()->orderByDesc('created_at')->limit(self::NB_LISTE * 2)->get();
    }

    /** Révoque (ou rétablit) sans supprimer : la page publique affiche alors « révoqué ». */
    public function changerRevocation(string $id, bool $revoquer): Passeport
    {
        $passeport = Passeport::query()->findOrFail($id);
        $passeport->revoque_le = $revoquer ? now() : null;
        $passeport->save();

        return $passeport;
    }

    public function url(Passeport $passeport): string
    {
        return route('front.passeport', ['code' => $passeport->code]);
    }

    /**
     * QR code de l'URL publique, en SVG (vectoriel : net à toute taille d'impression, sans extension GD).
     * Correction d'erreurs Q : reste lisible avec environ 25 % de l'étiquette abîmée.
     */
    public function qrSvg(Passeport $passeport, int $taille = 240): string
    {
        $renderer = new ImageRenderer(new RendererStyle($taille, 4), new SvgImageBackEnd());

        return (new Writer($renderer))->writeString($this->url($passeport), 'UTF-8', ErrorCorrectionLevel::Q());
    }

    /** Jeton imprévisible (random_bytes) ; on retire au cas où un doublon existerait déjà. */
    private function nouveauCode(): string
    {
        do {
            $code = Str::random(Passeport::LONGUEUR_CODE);
        } while (Passeport::query()->where('code', $code)->exists());

        return $code;
    }
}
