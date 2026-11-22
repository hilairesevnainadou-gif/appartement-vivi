<?php

namespace Database\Seeders;

use App\Enums\Formule;
use App\Enums\Role;
use App\Enums\StatutAbonnement;
use App\Enums\StatutAvis;
use App\Enums\StatutLogement;
use App\Enums\StatutPaiement;
use App\Enums\StatutReservation;
use App\Enums\TypeMouvement;
use App\Models\Equipement;
use App\Models\FormuleAbonnement;
use App\Models\Logement;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\User;
use App\Services\Caisse;
use App\Support\Parametres;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Données de DÉMONSTRATION (logements, comptes, réservations), reprises de la maquette.
 * Lancé seulement en environnement local : php artisan db:seed --class=DemoSeeder
 * Tous les comptes de démonstration ont le mot de passe « demo-vivi-2026 ».
 */
class DemoSeeder extends Seeder
{
    private const MOT_DE_PASSE = 'demo-vivi-2026';

    /** Centre approximatif des quartiers des logements de démonstration. */
    public const POSITIONS = [
        'Fidjrossè' => [6.3530, 2.3790], 'Cadjèhoun' => [6.3700, 2.3950], 'Ganhi' => [6.3560, 2.4290], 'Haie Vive' => [6.3640, 2.3870],
        'Zogbadjè' => [6.4160, 2.3420], 'Djègbadji' => [6.3290, 2.0790], 'Akpakpa' => [6.3600, 2.4500], 'Ouando' => [6.5050, 2.6150],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoSeeder refusé en production.');

            return;
        }

        $this->call(CatalogueSeeder::class);

        // ---------- Comptes ----------
        $proprio = $this->compte('Rodrigue Houngbo', '0197000001', 'rodrigue@demo.vivi', Role::Proprietaire);
        $proprio2 = $this->compte('Aïcha Dossou', '0197000002', 'aicha@demo.vivi', Role::Proprietaire);
        $gestionnaire = $this->compte('Serge Agbo', '0197000003', 'serge@demo.vivi', Role::Gestionnaire);
        $this->compte('Caissière VIVI', null, 'caisse@demo.vivi', Role::Caissiere);
        $clients = collect([
            ['Mireille Adjovi', '0197000011'], ['Géraud Kiki', '0197000012'], ['Prisca Dossa', '0197000013'],
            ['Arnaud Fagla', '0197000014'], ['Fifamè Houessou', '0197000015'],
        ])->map(fn ($c) => $this->compte($c[0], $c[1], null, Role::Client));

        // ---------- Abonnements ----------
        foreach ([[$proprio, 'Confort'], [$proprio2, 'Premium']] as [$p, $nom]) {
            $formule = FormuleAbonnement::where('nom', $nom)->firstOrFail();
            $p->abonnements()->firstOrCreate(['formule_abonnement_id' => $formule->id], [
                'debut' => today()->subDays(12), 'fin' => today()->addDays(18),
                'prix_paye' => $formule->prix_mensuel, 'statut' => StatutAbonnement::Actif,
            ]);
        }

        // ---------- Logements ----------
        $this->copierPhotos();
        foreach ($this->logements() as $i => $l) {
            $logement = Logement::updateOrCreate(['slug' => Str::slug($l['titre'])], [
                'proprietaire_id' => ($i < 3 ? $proprio : $proprio2)->id,
                'titre' => $l['titre'], 'type' => $l['type'], 'ville' => $l['ville'], 'quartier' => $l['quartier'],
                'description' => $l['description'] ?? 'Logement meublé, climatisé et prêt à vivre : literie faite, linge de maison fourni.',
                'capacite' => $l['capacite'], 'chambres' => $l['chambres'], 'lits' => $l['lits'], 'salles_de_bain' => $l['sdb'],
                'temps_preparation_minutes' => $l['preparation'] ?? 60,
                // Salles : la gestion peut être confiée à VIVI (gérant VIVI désigné plus bas)
                'mode_gestion' => $l['gestion'] ?? 'proprietaire',
                // Position approximative du quartier (carte de la fiche, « près de moi »)
                'latitude' => self::POSITIONS[$l['quartier']][0] ?? null, 'longitude' => self::POSITIONS[$l['quartier']][1] ?? null,
                'statut' => $l['statut'] ?? StatutLogement::Publie,
                'valide_le' => ($l['statut'] ?? StatutLogement::Publie) === StatutLogement::Publie ? now()->subWeeks(3) : null,
            ]);
            $logement->equipements()->sync(Equipement::whereIn('nom', $l['equipements'])->pluck('id'));
            $logement->services()->sync(Service::whereIn('nom', $l['services'])->pluck('id'));

            foreach ($l['tarifs'] as $formule => $t) {
                $logement->tarifs()->updateOrCreate(['formule' => $formule], $t);
            }
            $logement->creneaux()->delete();
            foreach ($l['creneaux'] ?? [] as $ordre => [$nom, $debut, $fin]) {
                $logement->creneaux()->create(['nom' => $nom, 'heure_debut' => $debut, 'heure_fin' => $fin, 'ordre' => $ordre]);
            }
            $logement->photos()->delete();
            foreach ($l['photos'] as $ordre => $photo) {
                $logement->photos()->create(['chemin' => 'logements/'.$photo.'-1280.webp', 'ordre' => $ordre]);
            }
        }
        $lagune = Logement::where('slug', 'studio-lagune')->firstOrFail();
        $cocotiers = Logement::where('slug', 'residence-les-cocotiers')->firstOrFail();
        $lagune->gestionnaires()->syncWithoutDetaching([$gestionnaire->id]);
        // Gestion confiée à VIVI : la salle des Palmiers a son gérant VIVI ; la villa attend qu'on en désigne un
        $gerantVivi = $this->compte('Gérant VIVI', '0197000041', 'gerant@demo.vivi', Role::Gestionnaire);
        Logement::where('slug', 'salle-des-fetes-les-palmiers')->update(['gerant_vivi_id' => $gerantVivi->id, 'gestion_vivi_demandee_le' => now()->subDays(6)]);
        Logement::where('slug', 'villa-evenementielle-ouidah')->update(['gestion_vivi_demandee_le' => now()->subDay()]);
        $lagune->blocages()->firstOrCreate(['date_debut' => today()->addDays(4)], ['date_fin' => today()->addDays(4), 'motif' => 'Travaux de peinture']);

        // ---------- Réservations ----------
        if (Reservation::count() === 0) {
            $this->reservation($lagune, $clients[3], Formule::DemiJournee, today()->setTime(8, 0), today()->setTime(13, 0), StatutReservation::EnCours, scanne: true);
            $this->reservation($lagune, $clients[0], Formule::Heure, today()->setTime(14, 0), today()->setTime(17, 0), StatutReservation::Confirmee, services: ['Pack Boissons' => 1]);
            $this->reservation($cocotiers, $clients[2], Formule::DemiJournee, today()->setTime(16, 0), today()->setTime(22, 0), StatutReservation::Confirmee, services: ['Décoration romantique' => 1]);
            $this->reservation($cocotiers, $clients[1], Formule::Journee, today()->addDay()->setTime(13, 0), today()->addDays(3)->setTime(11, 0), StatutReservation::Confirmee, services: ['Petit-déjeuner' => 3]);
            $passee = $this->reservation($lagune, $clients[1], Formule::Journee, today()->subDays(2)->setTime(12, 0), today()->subDay()->setTime(11, 0), StatutReservation::Terminee, scanne: true, services: ['Ménage en cours de séjour' => 1]);
            $passee->avis()->create(['client_id' => $passee->client_id, 'logement_id' => $passee->logement_id, 'note' => 5,
                'commentaire' => 'Propre, calme, climatisation efficace. L’accueil a pris deux minutes.', 'statut' => StatutAvis::EnAttente]);
            $this->reservation($lagune, $clients[4], Formule::Heure, today()->subDays(5)->setTime(10, 0), today()->subDays(5)->setTime(12, 0), StatutReservation::Consommee);
        }

        // ---------- Retrait en attente de validation ----------
        if ($proprio->retraits()->count() === 0) {
            $proprio->mouvementsSolde()->create(['type' => TypeMouvement::Ajustement, 'montant' => 120000, 'libelle' => 'Solde de démonstration']);
            app(Caisse::class)->demanderRetrait($proprio, 40000);
        }

        $this->command?->info('Données de démonstration prêtes. Mot de passe des comptes : '.self::MOT_DE_PASSE);
    }

    private function compte(string $nom, ?string $telephone, ?string $email, Role $role): User
    {
        $user = User::updateOrCreate(
            $email ? ['email' => $email] : ['telephone' => $telephone],
            ['name' => $nom, 'telephone' => $telephone, 'email' => $email, 'password' => self::MOT_DE_PASSE, 'actif' => true],
        );
        $user->forceFill(['telephone_verified_at' => $telephone ? now() : null, 'email_verified_at' => $email ? now() : null])->save();
        $user->syncRoles([$role->value]);

        return $user;
    }

    /** @param array<string, int> $services */
    private function reservation(Logement $logement, User $client, Formule $formule, Carbon $debut, Carbon $fin, StatutReservation $statut, bool $scanne = false, array $services = []): Reservation
    {
        $tarif = $logement->tarifs()->where('formule', $formule)->firstOrFail();
        $location = match ($formule) {
            Formule::Heure => $tarif->prix * $debut->diffInHours($fin),
            Formule::DemiJournee => $tarif->prix,
            Formule::Journee => $tarif->prix * max(1, (int) ceil($debut->copy()->startOfDay()->diffInDays($fin->copy()->startOfDay()))),
        };
        $lignes = collect($services)->map(function (int $quantite, string $nom) {
            $s = Service::where('nom', $nom)->firstOrFail();

            return ['service_id' => $s->id, 'nom' => $s->nom, 'prix_unitaire' => $s->prix, 'quantite' => $quantite, 'montant' => $s->prix * $quantite, 'beneficiaire' => Parametres::get('services_beneficiaire')];
        })->values();
        $montantServices = (int) $lignes->sum('montant');
        $taux = Parametres::commissionPourcentage();
        $commission = (int) round(($location + $montantServices) * $taux / 100);

        $r = Reservation::create([
            'reference' => 'VIVI-'.strtoupper(Str::random(6)),
            'client_id' => $client->id, 'logement_id' => $logement->id, 'formule' => $formule,
            'creneau_id' => $formule === Formule::DemiJournee ? $logement->creneaux()->where('heure_debut', $debut->format('H:i:s'))->value('id') : null,
            'debut' => $debut, 'fin' => $fin, 'voyageurs' => 2, 'statut' => $statut,
            'montant_location' => $location, 'montant_services' => $montantServices, 'taux_commission' => $taux,
            'montant_commission' => $commission, 'montant_total' => $location + $montantServices + $commission,
            'services_beneficiaire' => Parametres::get('services_beneficiaire'),
            'montant_services_proprietaire' => Parametres::get('services_beneficiaire') === 'proprietaire' ? $montantServices : 0,
            'code_secours' => (string) random_int(100000, 999999),
            'scanne_le' => $scanne ? $debut->copy()->addMinutes(7) : null,
            'scanne_par' => $scanne ? $logement->proprietaire_id : null,
        ]);
        $r->services()->createMany($lignes->all());
        $r->paiements()->create([
            'payeur_id' => $client->id, 'moyen' => 'MTN Mobile Money', 'montant' => $r->montant_total,
            'statut' => StatutPaiement::Reussi, 'reference_passerelle' => 'DEMO-'.random_int(100000, 999999), 'paye_le' => $debut->copy()->subDays(2),
        ]);
        if ($scanne) {
            $logement->proprietaire->mouvementsSolde()->create([
                'type' => TypeMouvement::CreditSejour, 'montant' => $location, 'reservation_id' => $r->id,
                'libelle' => 'Séjour '.$r->reference.' démarré',
            ]);
        }

        return $r;
    }

    /** Copie les photos de la maquette dans storage/app/public/logements (si la maquette est présente). */
    private function copierPhotos(): void
    {
        $source = base_path('../maquette/assets/img/logements');
        if (! File::isDirectory($source)) {
            $this->command?->warn('Photos de la maquette introuvables : logements sans images.');

            return;
        }
        foreach (File::files($source) as $fichier) {
            if (str_ends_with($fichier->getFilename(), '-1280.webp') && ! Storage::disk('public')->exists('logements/'.$fichier->getFilename())) {
                Storage::disk('public')->put('logements/'.$fichier->getFilename(), File::get($fichier->getPathname()));
            }
        }
    }

    /** Les 8 logements de la maquette, puis les salles et espaces événementiels de démonstration. */
    private function logements(): array
    {
        $heure = fn ($prix, $de, $a, $min) => ['prix' => $prix, 'heure_ouverture' => $de, 'heure_fermeture' => $a, 'duree_minimum_heures' => $min];
        $demi = fn ($prix) => ['prix' => $prix];
        $jour = fn ($prix, $arrivee, $depart) => ['prix' => $prix, 'heure_arrivee' => $arrivee, 'heure_depart' => $depart];
        $base = ['Climatisation', 'Wi-Fi', 'Télévision'];

        return [
            ['titre' => 'Studio Lagune', 'type' => 'studio', 'ville' => 'Cotonou', 'quartier' => 'Fidjrossè', 'capacite' => 2, 'chambres' => 1, 'lits' => 1, 'sdb' => 1,
                'tarifs' => ['heure' => $heure(3500, '08:00', '22:00', 2), 'demi_journee' => $demi(10000), 'journee' => $jour(18000, '12:00', '11:00')],
                'creneaux' => [['Matin', '08:00', '13:00'], ['Après-midi', '14:00', '19:00'], ['Soirée', '20:00', '01:00']],
                'equipements' => [...$base, 'Cuisine équipée', 'Groupe électrogène', 'Eau chaude'], 'services' => ['Pack Boissons', 'Ménage en cours de séjour', 'Décoration romantique', 'Chauffeur', 'Petit-déjeuner', 'Wi-Fi haut débit'],
                'photos' => ['studio-loft', 'chambre-grise', 'cuisine-blanche', 'salle-de-bain', 'coin-lecture']],
            ['titre' => 'Résidence Les Cocotiers', 'type' => 'f2', 'ville' => 'Cotonou', 'quartier' => 'Cadjèhoun', 'capacite' => 3, 'chambres' => 1, 'lits' => 2, 'sdb' => 1,
                'tarifs' => ['heure' => $heure(5000, '09:00', '21:00', 2), 'demi_journee' => $demi(14000), 'journee' => $jour(25000, '13:00', '11:00')],
                'creneaux' => [['Journée', '09:00', '15:00'], ['Soirée', '16:00', '22:00']],
                'equipements' => [...$base, 'Cuisine équipée', 'Parking', 'Eau chaude'], 'services' => ['Pack Boissons', 'Ménage en cours de séjour', 'Décoration romantique', 'Petit-déjeuner'],
                'photos' => ['salon-classique', 'chambre-orange', 'salle-de-bain']],
            ['titre' => 'Chambre meublée Ganhi', 'type' => 'chambre', 'ville' => 'Cotonou', 'quartier' => 'Ganhi', 'capacite' => 2, 'chambres' => 1, 'lits' => 1, 'sdb' => 1,
                'statut' => StatutLogement::EnValidation,
                'tarifs' => ['heure' => $heure(2000, '09:00', '20:00', 2), 'demi_journee' => $demi(6000)],
                'creneaux' => [['Matin', '09:00', '14:00'], ['Après-midi', '15:00', '20:00']],
                'equipements' => $base, 'services' => ['Pack Boissons', 'Ménage en cours de séjour'],
                'photos' => ['chambre-grise', 'coin-lecture', 'salle-de-bain']],
            ['titre' => 'Appartement Haie Vive', 'type' => 'f3', 'ville' => 'Cotonou', 'quartier' => 'Haie Vive', 'capacite' => 4, 'chambres' => 2, 'lits' => 2, 'sdb' => 2,
                'tarifs' => ['demi_journee' => $demi(20000), 'journee' => $jour(35000, '14:00', '12:00')],
                'creneaux' => [['Matin', '08:00', '13:00'], ['Après-midi', '14:00', '19:00']],
                'equipements' => [...$base, 'Cuisine équipée', 'Parking', 'Groupe électrogène', 'Gardien 24 h/24', 'Balcon'], 'services' => ['Pack Boissons', 'Ménage en cours de séjour', 'Chauffeur', 'Petit-déjeuner', 'Wi-Fi haut débit'],
                'photos' => ['salon-cuir', 'chambre-capitonnee', 'cuisine-blanche', 'salle-de-bain-claire', 'salon-canape-clair']],
            ['titre' => 'Studio Campus', 'type' => 'studio', 'ville' => 'Abomey-Calavi', 'quartier' => 'Zogbadjè', 'capacite' => 2, 'chambres' => 1, 'lits' => 1, 'sdb' => 1,
                'tarifs' => ['heure' => $heure(2500, '07:00', '23:00', 2), 'demi_journee' => $demi(7000), 'journee' => $jour(12000, '12:00', '10:00')],
                'creneaux' => [['Matin', '07:00', '12:00'], ['Après-midi', '13:00', '18:00'], ['Soirée', '19:00', '00:00']],
                'equipements' => [...$base, 'Eau chaude'], 'services' => ['Pack Boissons', 'Ménage en cours de séjour', 'Wi-Fi haut débit'],
                'photos' => ['studio-minimal', 'chambre-grise', 'salle-de-bain']],
            ['titre' => 'Villa-appartement Ouidah Plage', 'type' => 'f4', 'ville' => 'Ouidah', 'quartier' => 'Djègbadji', 'capacite' => 6, 'chambres' => 3, 'lits' => 4, 'sdb' => 2,
                'tarifs' => ['journee' => $jour(60000, '15:00', '11:00')],
                'equipements' => [...$base, 'Cuisine équipée', 'Parking', 'Groupe électrogène', 'Piscine', 'Gardien 24 h/24'], 'services' => ['Pack Boissons', 'Ménage en cours de séjour', 'Décoration romantique', 'Chauffeur', 'Petit-déjeuner'],
                'photos' => ['villa-piscine', 'salon-baies', 'chambre-capitonnee', 'cuisine-blanche', 'salle-de-bain-claire']],
            ['titre' => 'F2 Akpakpa Centre', 'type' => 'f2', 'ville' => 'Cotonou', 'quartier' => 'Akpakpa', 'capacite' => 3, 'chambres' => 1, 'lits' => 2, 'sdb' => 1,
                'tarifs' => ['heure' => $heure(3000, '08:00', '22:00', 3), 'demi_journee' => $demi(9000), 'journee' => $jour(16000, '12:00', '11:00')],
                'creneaux' => [['Matin', '08:00', '13:00'], ['Après-midi', '14:00', '19:00']],
                'equipements' => [...$base, 'Cuisine équipée', 'Groupe électrogène'], 'services' => ['Pack Boissons', 'Ménage en cours de séjour', 'Chauffeur'],
                'photos' => ['sejour-rouge', 'chambre-orange', 'cuisine-blanche']],
            ['titre' => 'Loft Les Jacarandas', 'type' => 'f2', 'ville' => 'Porto-Novo', 'quartier' => 'Ouando', 'capacite' => 4, 'chambres' => 1, 'lits' => 2, 'sdb' => 1,
                'tarifs' => ['demi_journee' => $demi(12000), 'journee' => $jour(22000, '14:00', '11:00')],
                'creneaux' => [['Matin', '08:00', '13:00'], ['Après-midi', '14:00', '19:00']],
                'equipements' => [...$base, 'Cuisine équipée', 'Parking', 'Balcon'], 'services' => ['Pack Boissons', 'Ménage en cours de séjour', 'Petit-déjeuner', 'Wi-Fi haut débit'],
                'photos' => ['salon-plantes', 'salon-soir', 'cuisine-blanche']],
            // ---------- Salles et espaces événementiels (capacité en personnes, ni chambres ni lits) ----------
            ['titre' => 'Salle des fêtes Les Palmiers', 'type' => 'salle_fetes', 'ville' => 'Cotonou', 'quartier' => 'Akpakpa', 'capacite' => 300, 'chambres' => 0, 'lits' => 0, 'sdb' => 4,
                'description' => 'Grande salle climatisée pour mariages, anniversaires et baptêmes : piste de danse, loge pour les mariés, parking surveillé.',
                'preparation' => 120, 'gestion' => 'vivi',
                'tarifs' => ['heure' => $heure(30000, '08:00', '02:00', 4), 'demi_journee' => $demi(120000), 'journee' => $jour(250000, '08:00', '02:00')],
                'creneaux' => [['Journée', '08:00', '16:00'], ['Soirée', '17:00', '02:00']],
                'equipements' => ['Climatisation', 'Sonorisation', 'Micro', 'Tables et chaises', 'Scène ou estrade', 'Parking', 'Groupe électrogène', 'Gardien 24 h/24'],
                'services' => ['Décoration de salle', 'Traiteur', 'Agent de sécurité', 'Nettoyage après l’événement', 'Pack Boissons'],
                'photos' => []],
            ['titre' => 'Centre de conférences Haie Vive', 'type' => 'salle_conference', 'ville' => 'Cotonou', 'quartier' => 'Haie Vive', 'capacite' => 120, 'chambres' => 0, 'lits' => 0, 'sdb' => 2,
                'description' => 'Salle de conférence équipée pour séminaires, formations et assemblées : vidéoprojecteur, sonorisation, Wi-Fi haut débit.',
                'preparation' => 60,
                'tarifs' => ['heure' => $heure(25000, '07:00', '22:00', 2), 'demi_journee' => $demi(90000), 'journee' => $jour(160000, '07:00', '22:00')],
                'creneaux' => [['Matinée', '08:00', '13:00'], ['Après-midi', '14:00', '19:00']],
                'equipements' => ['Climatisation', 'Wi-Fi', 'Vidéoprojecteur et écran', 'Sonorisation', 'Micro', 'Tables et chaises', 'Parking', 'Groupe électrogène', 'Accès personnes à mobilité réduite'],
                'services' => ['Traiteur', 'Pack Boissons', 'Nettoyage après l’événement'],
                'photos' => []],
            ['titre' => 'Salle de réunion Ganhi Business', 'type' => 'salle_reunion', 'ville' => 'Cotonou', 'quartier' => 'Ganhi', 'capacite' => 20, 'chambres' => 0, 'lits' => 0, 'sdb' => 1,
                'description' => 'Salle de réunion au calme, en plein quartier d’affaires : grande table, écran, Wi-Fi et café.',
                'preparation' => 30,
                'tarifs' => ['heure' => $heure(10000, '08:00', '20:00', 1), 'demi_journee' => $demi(35000)],
                'creneaux' => [['Matinée', '08:00', '13:00'], ['Après-midi', '14:00', '19:00']],
                'equipements' => ['Climatisation', 'Wi-Fi', 'Vidéoprojecteur et écran', 'Tables et chaises', 'Groupe électrogène'],
                'services' => ['Pack Boissons', 'Traiteur'],
                'photos' => []],
            ['titre' => 'Jardin Les Flamboyants', 'type' => 'espace_plein_air', 'ville' => 'Abomey-Calavi', 'quartier' => 'Zogbadjè', 'capacite' => 500, 'chambres' => 0, 'lits' => 0, 'sdb' => 3,
                'description' => 'Grand jardin arboré pour réceptions en plein air, mariages et concerts privés, avec électricité et sanitaires.',
                'preparation' => 120,
                'tarifs' => ['demi_journee' => $demi(150000), 'journee' => $jour(300000, '08:00', '02:00')],
                'creneaux' => [['Journée', '08:00', '17:00'], ['Soirée', '17:00', '02:00']],
                'equipements' => ['Espace extérieur', 'Sonorisation', 'Tables et chaises', 'Parking', 'Groupe électrogène', 'Gardien 24 h/24'],
                'services' => ['Décoration de salle', 'Traiteur', 'Agent de sécurité', 'Nettoyage après l’événement'],
                'photos' => []],
            ['titre' => 'Villa événementielle Ouidah', 'type' => 'espace_complet', 'ville' => 'Ouidah', 'quartier' => 'Djègbadji', 'capacite' => 80, 'chambres' => 0, 'lits' => 0, 'sdb' => 3,
                'description' => 'Villa entière avec piscine et jardin, louée pour votre événement : anniversaires, shootings, séminaires au vert.',
                'preparation' => 120, 'gestion' => 'vivi',
                'tarifs' => ['journee' => $jour(200000, '09:00', '23:00')],
                'equipements' => ['Climatisation', 'Piscine', 'Espace extérieur', 'Sonorisation', 'Tables et chaises', 'Parking', 'Groupe électrogène'],
                'services' => ['Décoration de salle', 'Traiteur', 'Agent de sécurité', 'Chauffeur'],
                'photos' => ['villa-piscine', 'salon-baies']],
        ];
    }
}