<?php

namespace App\Http\Resources\Api;

use App\Enums\StatutPaiement;
use App\Enums\StatutReservation;
use App\Models\Reservation;
use App\Support\Parametres;
use App\Support\QrCode;
use App\Support\Telephone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Réservation vue par son client (billet). Le QR Code, le code de secours et l'adresse exacte
 * ne sont donnés que pour une réservation payée et active (confirmée ou séjour en cours), comme sur le site.
 * ReservationProResource : vue du propriétaire ou du gestionnaire (client, encaissement, crédit, notes).
 *
 * @mixin Reservation
 */
class ReservationResource extends JsonResource
{
    protected bool $pro = false;

    public function toArray(Request $request): array
    {
        $actif = in_array($this->statut, [StatutReservation::Confirmee, StatutReservation::EnCours], true);
        $detail = $request->route('reference') !== null && ! $this->pro;
        $logement = $this->whenLoaded('logement');

        $donnees = [
            'reference' => $this->reference,
            'statut' => Format::enum($this->statut),
            'formule' => Format::enum($this->formule),
            'debut' => Format::date($this->debut),
            'fin' => Format::date($this->fin),
            'creneau' => $this->creneauEnClair(),
            'duree' => $this->dureeCourte(),
            'voyageurs' => (int) $this->voyageurs,   // à ne pas afficher pour une salle
            'personnes' => $this->personnes(),       // « 2 voyageurs », ou null pour une salle
            'logement' => $this->relationLoaded('logement') ? [
                'id' => $logement->id,
                'slug' => $logement->slug,
                'titre' => $logement->titre,
                'ville' => $logement->ville,
                'quartier' => $logement->quartier,
                'photo' => $logement->relationLoaded('photos') ? $logement->urlPhoto() : null,
                'adresse' => $actif || $this->pro ? $logement->adresseComplete() : null,
                'itineraire' => $actif || $this->pro ? $logement->lienItineraire() : null,
                'categorie' => $logement->categorie(),
                'est_salle' => $logement->estSalle(),   // salle de fêtes ou de conférence : pas de « voyageurs »
            ] : null,
            'montants' => [
                'location' => (int) $this->montant_location,
                'services' => (int) $this->montant_services,
                'commission' => (int) $this->montant_commission,
                'remise_agent' => (int) $this->remise_agent,   // agent qui réserve un logement qu'il promeut : déjà déduite du total
                'bonus' => (int) $this->montant_bonus,
                'total' => (int) $this->montant_total,
                'paye' => $this->montantPaye(),
                'rembourse' => (int) $this->montant_rembourse,
            ],
            // Suivi du remboursement après une annulation (null s'il n'y a rien à rembourser)
            'remboursement' => (int) $this->montant_rembourse > 0 ? [
                'montant' => (int) $this->montant_rembourse,
                'statut' => $this->rembourse_le ? 'verse' : 'a_verser',
                'verse_le' => Format::date($this->rembourse_le),
                'mode' => $this->mode_remboursement,
                'reference' => $this->reference_remboursement,
            ] : null,
            'services' => $this->whenLoaded('services', fn () => $this->services->map(fn ($s) => [
                'service_id' => $s->service_id, 'nom' => $s->nom, 'prix_unitaire' => (int) $s->prix_unitaire,
                'quantite' => (int) $s->quantite, 'montant' => (int) $s->montant,
            ])->values()),
            // Paiement à finaliser : créneau retenu jusqu'à cette heure
            'retenu_jusqua' => $this->statut === StatutReservation::EnAttentePaiement ? Format::date($this->bloque_jusqua) : null,
            'nombre_reports' => (int) $this->nombre_reports,
            'annulee_le' => Format::date($this->annulee_le),
            'scanne_le' => Format::date($this->scanne_le),
            'cree_le' => Format::date($this->created_at),
        ];

        if (! $this->pro) {
            $donnees += [
                'billet' => $detail && $actif ? [
                    'qr_contenu' => $this->jetonQr(),
                    'qr_svg' => QrCode::svg($this->jetonQr(), 260),
                    'code_secours' => (string) $this->code_secours,
                ] : null,
                'actions' => [
                    'payer' => $this->statut === StatutReservation::EnAttentePaiement && $this->bloque_jusqua?->isFuture(),
                    'reporter' => $this->reportPossible(),
                    'annuler' => app(\App\Services\ApresVente::class)->annulable($this->resource),
                    'donner_avis' => $this->statut === StatutReservation::Terminee && ! ($this->relationLoaded('avis') ? $this->avis : $this->avis()->exists()),
                ],
                'avis' => $this->whenLoaded('avis', fn () => $this->avis ? [
                    'note' => (int) $this->avis->note, 'commentaire' => $this->avis->commentaire, 'statut' => Format::enum($this->avis->statut),
                ] : null),
                'delai_report_heures' => (int) Parametres::get('report_delai_minimum_heures'),
                'moyen_paiement' => $this->whenLoaded('paiements', fn () => $this->paiements->firstWhere('statut', StatutPaiement::Reussi)?->moyen),
            ];
        } else {
            $donnees += [
                'client' => $this->relationLoaded('client') ? [
                    'id' => $this->client->id, 'nom' => $this->client->name, 'telephone' => $this->client->telephone,
                    // Numéro WhatsApp donné à la réservation (tout pays, format +indicatif) et lien pour ouvrir la conversation
                    'whatsapp' => $this->whatsapp ?: $this->client->whatsapp,
                    'lien_whatsapp' => Telephone::lienWhatsapp($this->whatsapp ?: $this->client->whatsapp),
                ] : null,
                'origine' => $this->estManuelle() ? 'manuelle' : 'plateforme',
                'credit_proprietaire' => $this->estManuelle() ? 0 : $this->creditProprietaire(),
                // Part marketing retirée de la location : à l'agent du lien d'affiliation, sinon à VIVI
                'part_marketing' => (int) $this->montant_marketing,
                // Gestion confiée à VIVI : rémunération du contrat, retirée de la location
                'part_gestion' => (int) $this->montant_gestion,
                'agent' => $this->agent_id && $this->relationLoaded('agent') && $this->agent ? ['id' => $this->agent->id, 'nom' => $this->agent->name] : null,
                'montant_encaisse' => (int) $this->montant_encaisse,
                'reste_a_payer' => $this->resteAPayer(),
                'note_interne' => $this->note_interne,
            ];
        }

        return $donnees;
    }
}
