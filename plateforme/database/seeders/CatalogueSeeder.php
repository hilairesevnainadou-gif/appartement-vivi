<?php

namespace Database\Seeders;

use App\Models\Equipement;
use App\Models\FormuleAbonnement;
use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * Catalogue de départ : équipements, services à la carte et formules d'abonnement.
 * Valeurs d'exemple reprises de la maquette, modifiables ensuite dans l'administration.
 * Peut être relancé : rien n'est créé en double.
 */
class CatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $equipements = [
            ['Climatisation', 'bi-snow'], ['Wi-Fi', 'bi-wifi'], ['Cuisine équipée', 'bi-cup-hot'],
            ['Télévision', 'bi-tv'], ['Parking', 'bi-p-square'], ['Groupe électrogène', 'bi-lightning-charge'],
            ['Eau chaude', 'bi-droplet'], ['Gardien 24 h/24', 'bi-shield-check'], ['Piscine', 'bi-water'], ['Balcon', 'bi-door-open'],
        ];
        foreach ($equipements as $i => [$nom, $icone]) {
            Equipement::firstOrCreate(['nom' => $nom], ['icone' => $icone, 'ordre' => $i]);
        }

        $services = [
            ['Pack Boissons', 'Eau, jus et sodas frais déposés avant votre arrivée.', 5000, 'le pack', 'bi-cup-straw'],
            ['Ménage en cours de séjour', 'Un passage de ménage à l’heure qui vous arrange.', 3000, 'le passage', 'bi-stars'],
            ['Décoration romantique', 'Bougies, pétales et mise en scène de la chambre.', 10000, 'la prestation', 'bi-heart'],
            ['Chauffeur', 'Un trajet aller ou retour dans la ville.', 7000, 'le trajet', 'bi-car-front'],
            ['Petit-déjeuner', 'Viennoiseries, fruits, café ou thé.', 2500, 'par personne', 'bi-egg-fried'],
            ['Wi-Fi haut débit', 'Connexion renforcée pour travailler ou regarder un film.', 2000, 'le séjour', 'bi-router'],
        ];
        foreach ($services as $i => [$nom, $description, $prix, $unite, $icone]) {
            Service::firstOrCreate(['nom' => $nom], compact('description', 'prix', 'unite', 'icone') + ['ordre' => $i]);
        }

        // Chaque formule inclut au moins 2 logements et 1 salle (fêtes, conférences…), comptés séparément
        $formules = [
            ['Essentiel', 5000, 2, 1, 0, ['Calendrier et créneaux', 'Scan du QR Code', 'Gestion confiable à VIVI']],
            ['Confort', 12000, 4, 2, 1, ['Calendrier et créneaux', 'Scan du QR Code', '1 gestionnaire délégué', 'Gestion confiable à VIVI']],
            ['Premium', 25000, 10, 5, 99, ['Calendrier et créneaux', 'Scan du QR Code', 'Gestionnaires illimités', 'Mise en avant dans la recherche', 'Gestion confiable à VIVI']],
        ];
        foreach ($formules as $i => [$nom, $prix, $logements, $salles, $gestionnaires, $avantages]) {
            FormuleAbonnement::firstOrCreate(['nom' => $nom], [
                'prix_mensuel' => $prix, 'logements_max' => $logements, 'salles_max' => $salles, 'gestionnaires_max' => $gestionnaires,
                'avantages' => $avantages, 'ordre' => $i,
            ]);
        }
    }
}
