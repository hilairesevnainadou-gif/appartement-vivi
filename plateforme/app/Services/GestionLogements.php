<?php

namespace App\Services;

use App\Enums\Formule;
use App\Enums\ModeGestion;
use App\Enums\StatutLogement;
use App\Enums\TypeLogement;
use App\Models\Logement;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Logements saisis par les propriétaires depuis leur espace.
 *
 * Un nouveau logement (ou un logement refusé, corrigé) part en vérification chez VIVI si l'abonnement du
 * propriétaire le permet ; sinon il reste en brouillon. Un logement publié le reste après modification.
 */
class GestionLogements
{
    /** Nombre de logements publiés ou en vérification, et maximum de l'abonnement actif (null = pas d'abonnement). */
    /**
     * Quotas de l'abonnement, séparés : logements d'un côté, salles (fêtes, conférences…) de l'autre.
     * Les clés « utilises » et « max » concernent les logements (compatibilité) ; « salles » donne le détail des salles.
     *
     * Propriétaire sur commission : quotas des Paramètres, sans abonnement (« commission » vaut true).
     *
     * @return array{utilises: int, max: ?int, formule: ?string, commission: bool, salles: array{utilises: int, max: ?int}}
     */
    public function quota(User $proprietaire, ?Logement $sauf = null): array
    {
        $commission = app(ModeleEconomique::class)->quotas($proprietaire);
        $formule = $commission ? null : $proprietaire->abonnementActif()->with('formule')->first()?->formule;
        $parCategorie = $proprietaire->logements()
            ->whereIn('statut', [StatutLogement::Publie, StatutLogement::EnValidation])
            ->when($sauf, fn ($q) => $q->whereKeyNot($sauf->getKey()))
            ->pluck('type')
            ->countBy(fn (TypeLogement $t) => $t->categorie());

        return [
            'utilises' => (int) ($parCategorie['logement'] ?? 0),
            'max' => $commission ? $commission['logements'] : $formule?->logements_max,
            'formule' => $commission ? 'Commission sur retrait' : $formule?->nom,
            'commission' => $commission !== null,
            'salles' => ['utilises' => (int) ($parCategorie['salle'] ?? 0), 'max' => $commission ? $commission['salles'] : $formule?->salles_max],
        ];
    }

    /**
     * Gestion confiée à VIVI : l'administration désigne le gérant VIVI (compte gestionnaire de l'équipe) qui accueille
     * et scanne pour cette fiche. Il ne compte pas dans le quota de gestionnaires du propriétaire. Null : retire le gérant.
     * $contrat : rémunération de VIVI (gestion_type, gestion_valeur, gestion_contrat, gestion_contrat_le) ; absent, elle est gardée.
     */
    public function designerGerantVivi(Logement $logement, ?User $gerant, array $contrat = []): Logement
    {
        if ($gerant && ! $gerant->hasRole(\App\Enums\Role::Gestionnaire->value)) {
            $gerant->assignRole(\App\Enums\Role::Gestionnaire->value);   // accès au scanner et au calendrier
        }
        $contrat = collect($contrat)->only(['gestion_type', 'gestion_valeur', 'gestion_contrat', 'gestion_contrat_le'])->all();
        if (array_key_exists('gestion_type', $contrat) && ! $contrat['gestion_type']) {
            $contrat['gestion_valeur'] = 0;
        }
        $logement->update(['mode_gestion' => ModeGestion::Vivi, 'gerant_vivi_id' => $gerant?->getKey()] + $contrat);
        $logement->load('proprietaire', 'gerantVivi');

        if ($gerant) {
            \App\Support\Prevenir::sansBloquer(function () use ($logement, $gerant) {
                $gerant->notify(new \App\Notifications\GerantViviDesigne($logement, pourGerant: true));
                $logement->proprietaire->notify(new \App\Notifications\GerantViviDesigne($logement, pourGerant: false));
            });
        }

        return $logement;
    }

    /** Le quota de la catégorie du logement (logement ou salle) permet-il de l'envoyer en vérification ? */
    public function peutSoumettre(User $proprietaire, ?Logement $logement = null): bool
    {
        $q = $this->quota($proprietaire, $logement);
        $compte = $logement?->categorie() === 'salle' ? $q['salles'] : $q;

        return $compte['max'] !== null
            && ($compte['max'] >= ModeleEconomique::ILLIMITE_LOGEMENTS || $compte['utilises'] < $compte['max']);
    }

    /** Crée ou met à jour le logement et tout ce qui en dépend. Renvoie le logement et un message pour le propriétaire. */
    public function enregistrer(User $proprietaire, array $donnees, ?Logement $logement = null): array
    {
        return DB::transaction(function () use ($proprietaire, $donnees, $logement) {
            $champs = collect($donnees)->only(['titre', 'type', 'ville', 'quartier', 'adresse', 'capacite', 'chambres', 'unites', 'latitude', 'longitude', 'lits',
                'salles_de_bain', 'description', 'temps_preparation_minutes'])->all();

            $champs['unites'] = max(1, (int) ($champs['unites'] ?? 1));   // une seule unité par défaut
            // Part de l'agent marketing (à VIVI sans agent) : seulement si le formulaire l'envoie
            if (array_key_exists('marketing_type', $donnees)) {
                $champs['marketing_type'] = $donnees['marketing_type'] ?: null;
                $champs['marketing_valeur'] = $champs['marketing_type'] ? (float) ($donnees['marketing_valeur'] ?? 0) : 0;
            }
            $nouveau = $logement === null;
            $logement ??= new Logement(['proprietaire_id' => $proprietaire->getKey(), 'statut' => StatutLogement::Brouillon, 'en_ligne' => true]);
            if ($nouveau) {
                $champs['slug'] = $this->slugUnique($donnees['titre'], $donnees['ville']);
            }
            // Gestion : le propriétaire accueille lui-même, ou confie l'accueil à VIVI (un gérant VIVI sera désigné)
            $confieAVivi = false;
            if ($mode = ModeGestion::tryFrom((string) ($donnees['mode_gestion'] ?? ''))) {
                $confieAVivi = $mode === ModeGestion::Vivi && ! $logement->estGereParVivi();
                $champs['mode_gestion'] = $mode;
                if ($confieAVivi) {
                    $champs['gestion_vivi_demandee_le'] = now();
                }
                if ($mode === ModeGestion::Proprietaire) {
                    $champs['gerant_vivi_id'] = null;
                }
            }
            $logement->fill($champs)->save();
            if ($confieAVivi) {
                DB::afterCommit(fn () => \App\Support\Equipe::prevenir('logements.gerer',
                    'Gestion confiée à VIVI : '.$logement->titre,
                    $proprietaire->name.' confie l’accueil de « '.$logement->titre.' » ('.$logement->quartier.', '.$logement->ville.') à VIVI. Désignez un gérant VIVI.',
                    \App\Filament\Resources\Logements\LogementResource::urlFiche($logement, panel: 'admin'),
                    'heroicon-o-user-plus', 'info', 'Désigner un gérant'));
            }

            $logement->equipements()->sync($donnees['equipements'] ?? []);
            $logement->services()->sync($donnees['services'] ?? []);
            $this->enregistrerTarifs($logement, $donnees['tarifs'] ?? []);
            $this->enregistrerCreneaux($logement, $donnees['creneaux'] ?? []);

            // Envoi en vérification : nouveau logement, brouillon ou logement refusé puis corrigé
            $message = 'Logement enregistré.';
            if (in_array($logement->statut, [StatutLogement::Brouillon, StatutLogement::Refuse], true)) {
                if ($this->peutSoumettre($proprietaire, $logement)) {
                    $logement->update(['statut' => StatutLogement::EnValidation, 'motif_refus' => null]);
                    $message = 'Logement envoyé à VIVI : il sera vérifié avant sa mise en ligne.';
                    DB::afterCommit(fn () => \App\Support\Equipe::prevenir('logements.gerer',
                        ($logement->estSalle() ? 'Salle à vérifier : ' : 'Logement à vérifier : ').$logement->titre,
                        $proprietaire->name.' · '.$logement->quartier.', '.$logement->ville.'. À vérifier avant la mise en ligne.',
                        \App\Filament\Resources\Logements\LogementResource::urlFiche($logement, panel: 'admin'),
                        'heroicon-o-home-modern', 'warning', 'Examiner'));
                } else {
                    $message = ($logement->estSalle()
                        ? 'Salle enregistrée en brouillon : votre formule n’inclut plus de salle disponible.'
                        : 'Logement enregistré en brouillon : votre formule n’inclut plus de logement disponible.')
                        .' Choisissez un abonnement (ou une formule plus grande) pour l’envoyer à la vérification.';
                }
            }

            return [$logement, $message];
        });
    }

    /** @param array<string, array<string, mixed>> $tarifs */
    private function enregistrerTarifs(Logement $logement, array $tarifs): void
    {
        $champs = [
            Formule::Heure->value => ['heure_ouverture', 'heure_fermeture', 'duree_minimum_heures'],
            Formule::DemiJournee->value => [],
            Formule::Journee->value => ['heure_arrivee', 'heure_depart'],
        ];
        foreach ($champs as $formule => $propres) {
            $saisie = $tarifs[$formule] ?? [];
            $existant = $logement->tarifs()->where('formule', $formule)->first();
            if (empty($saisie['actif'])) {
                $existant?->update(['actif' => false]);   // gardé pour l'historique et une réactivation

                continue;
            }
            $logement->tarifs()->updateOrCreate(['formule' => $formule], ['actif' => true, 'prix' => (int) $saisie['prix']]
                + collect($saisie)->only($propres)->all());
        }
    }

    /** Créneaux de demi-journée : mis à jour, ajoutés ou supprimés (les réservations passées gardent leurs horaires). */
    private function enregistrerCreneaux(Logement $logement, array $creneaux): void
    {
        $gardes = [];
        foreach (array_values($creneaux) as $ordre => $c) {
            $valeurs = ['nom' => $c['nom'], 'heure_debut' => $c['heure_debut'], 'heure_fin' => $c['heure_fin'], 'ordre' => $ordre + 1];
            $creneau = ! empty($c['id']) ? $logement->creneaux()->whereKey($c['id'])->first() : null;
            $creneau ? $creneau->update($valeurs) : ($creneau = $logement->creneaux()->create($valeurs));
            $gardes[] = $creneau->getKey();
        }
        $logement->creneaux()->whereNotIn('id', $gardes)->delete();
    }

    public function ajouterPhotos(Logement $logement, array $fichiers): int
    {
        $ordre = (int) $logement->photos()->max('ordre');
        foreach ($fichiers as $fichier) {
            /** @var UploadedFile $fichier */
            $chemin = $fichier->storeAs('logements/'.$logement->getKey(), Str::uuid().'.'.$fichier->extension(), 'public');
            $logement->photos()->create(['chemin' => $chemin, 'ordre' => ++$ordre]);
        }

        return count($fichiers);
    }

    public function supprimerPhoto(Logement $logement, int $photoId): void
    {
        $photo = $logement->photos()->findOrFail($photoId);
        // Les photos de démonstration (dossier partagé) ne sont pas effacées du disque
        if (str_starts_with($photo->chemin, 'logements/'.$logement->getKey().'/')) {
            Storage::disk('public')->delete($photo->chemin);
        }
        $photo->delete();
    }

    /** La photo choisie passe en premier : c'est elle qui apparaît dans les résultats de recherche. */
    public function photoPrincipale(Logement $logement, int $photoId): void
    {
        $photo = $logement->photos()->findOrFail($photoId);
        $logement->photos()->whereKeyNot($photo->getKey())->orderBy('ordre')->get()
            ->each(fn ($p, $i) => $p->update(['ordre' => $i + 1]));
        $photo->update(['ordre' => 0]);
    }

    private function slugUnique(string $titre, string $ville): string
    {
        $base = Str::slug($titre) ?: 'logement';
        $slug = $base;
        if (Logement::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::slug($ville);
        }
        for ($n = 2; Logement::withTrashed()->where('slug', $slug)->exists(); $n++) {
            $slug = $base.'-'.$n;
        }

        return $slug;
    }
}
