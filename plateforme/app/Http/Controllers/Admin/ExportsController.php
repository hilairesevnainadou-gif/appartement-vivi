<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Enums\StatutPaiement;
use App\Enums\StatutReservation;
use App\Http\Controllers\Controller;
use App\Models\Paiement;
use App\Models\Reservation;
use App\Models\Retrait;
use App\Support\Telephone;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exports comptables en CSV (séparateur « ; », encodage UTF-8 avec BOM : s'ouvre directement dans Excel).
 * Montants en FCFA entiers. Réservé à l'administrateur et à la caissière.
 */
class ExportsController extends Controller
{
    public const TYPES = [
        'reservations' => 'Réservations (montants figés à la réservation)',
        'paiements' => 'Paiements reçus (FedaPay)',
        'retraits' => 'Retraits des propriétaires et des agents',
        'remboursements' => 'Remboursements aux clients',
        'proprietaires' => 'Synthèse par propriétaire',
        'agents' => 'Synthèse par agent marketing',
        'publicites' => 'Publicités : règlements des annonceurs',
    ];

    public function __invoke(Request $request, string $type): StreamedResponse
    {
        abort_unless((bool) $request->user()?->can('exports.telecharger'), 403);
        abort_unless(isset(self::TYPES[$type]), 404);
        $periode = $request->validate([
            'du' => ['required', 'date_format:Y-m-d'],
            'au' => ['required', 'date_format:Y-m-d', 'after_or_equal:du'],
        ]);
        $du = CarbonImmutable::parse($periode['du'])->startOfDay();
        $au = CarbonImmutable::parse($periode['au'])->endOfDay();

        [$entetes, $lignes] = match ($type) {
            'reservations' => $this->reservations($du, $au),
            'paiements' => $this->paiements($du, $au),
            'retraits' => $this->retraits($du, $au),
            'remboursements' => $this->remboursements($du, $au),
            'proprietaires' => $this->proprietaires($du, $au),
            'agents' => $this->agents($du, $au),
            'publicites' => $this->publicites($du, $au),
        };

        $fichier = 'vivi-'.$type.'-'.$du->format('Ymd').'-'.$au->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($entetes, $lignes) {
            $sortie = fopen('php://output', 'w');
            fwrite($sortie, "\xEF\xBB\xBF");      // BOM : Excel lit les accents
            fputcsv($sortie, $entetes, ';');
            foreach ($lignes as $ligne) {
                fputcsv($sortie, $ligne, ';');
            }
            fclose($sortie);
        }, $fichier, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function reservations(CarbonImmutable $du, CarbonImmutable $au): array
    {
        $lignes = Reservation::with(['client', 'logement.proprietaire', 'agent'])
            ->whereNot('statut', StatutReservation::EnAttentePaiement)
            ->whereBetween('created_at', [$du, $au])->orderBy('created_at')->lazy()
            ->map(fn (Reservation $r) => [
                $r->reference, $r->created_at->format('d/m/Y H:i'), $r->client?->name, $this->contact($r->client),
                $r->logement?->titre, $r->logement?->proprietaire?->name, $r->formule->getLabel(),
                $r->debut->format('d/m/Y H:i'), $r->fin->format('d/m/Y H:i'), $r->statut->getLabel().($r->estManuelle() ? ' (hors plateforme)' : ''),
                $r->montant_location, $r->montant_services, $r->montant_services_proprietaire,
                rtrim(rtrim((string) $r->taux_commission, '0'), '.'), $r->montant_commission,
                (int) $r->montant_marketing, $r->agent?->name ?? ($r->remise_agent > 0 ? 'Remise à l’agent client' : 'VIVI'), (int) $r->remise_agent, $r->partMarketingVivi(), (int) $r->montant_gestion,
                $r->montant_total, (int) $r->montant_bonus, (int) $r->montant_rembourse,
            ]);

        return [['Référence', 'Réservée le', 'Client', 'Contact', 'Logement', 'Propriétaire', 'Formule', 'Début', 'Fin', 'Statut',
            'Location', 'Services', 'dont part propriétaire', 'Taux commission (%)', 'Commission VIVI',
            'Part marketing', 'Part marketing versée à', 'Remise agent', 'Part marketing VIVI', 'Gestion VIVI', 'Total', 'Payé avec bonus', 'Remboursé'], $lignes];
    }

    private function paiements(CarbonImmutable $du, CarbonImmutable $au): array
    {
        $lignes = Paiement::with(['payeur', 'payable'])->where('statut', StatutPaiement::Reussi)
            ->whereBetween('paye_le', [$du, $au])->orderBy('paye_le')->lazy()
            ->map(fn (Paiement $p) => [
                $p->paye_le?->format('d/m/Y H:i'),
                $p->payable_type === 'abonnement' ? 'Abonnement' : 'Réservation',
                $p->payable instanceof Reservation ? $p->payable->reference : '',
                $p->payeur?->name, $p->moyen, $p->passerelle, $p->reference_passerelle, $p->montant,
            ]);

        return [['Payé le', 'Objet', 'Référence', 'Payé par', 'Moyen', 'Passerelle', 'Référence transaction', 'Montant'], $lignes];
    }

    private function retraits(CarbonImmutable $du, CarbonImmutable $au): array
    {
        $lignes = Retrait::with(['proprietaire', 'traitePar'])->whereBetween('created_at', [$du, $au])->orderBy('created_at')->lazy()
            ->map(fn (Retrait $r) => [
                $r->reference, $r->created_at->format('d/m/Y H:i'), $r->proprietaire?->name, $this->contact($r->proprietaire),
                $r->montant, $r->statut->getLabel(), $r->mode_reglement, $r->reference_reglement,
                $r->traitePar?->name, $r->traite_le?->format('d/m/Y H:i'),
            ]);

        return [['Référence', 'Demandé le', 'Bénéficiaire (propriétaire ou agent)', 'Contact', 'Montant', 'Statut', 'Mode de règlement', 'Référence règlement', 'Traité par', 'Traité le'], $lignes];
    }

    private function remboursements(CarbonImmutable $du, CarbonImmutable $au): array
    {
        $lignes = Reservation::with(['client', 'logement'])->where('statut', StatutReservation::Annulee)->where('montant_rembourse', '>', 0)
            ->whereBetween('annulee_le', [$du, $au])->orderBy('annulee_le')->lazy()
            ->map(fn (Reservation $r) => [
                $r->reference, $r->annulee_le?->format('d/m/Y H:i'), $r->client?->name, $this->contact($r->client), $r->logement?->titre,
                $r->montant_total, $r->montant_commission, $r->montant_rembourse,
                $r->rembourse_le ? 'Versé' : 'À verser', $r->mode_remboursement, $r->reference_remboursement, $r->rembourse_le?->format('d/m/Y H:i'),
            ]);

        return [['Référence', 'Annulée le', 'Client', 'Contact', 'Logement', 'Payé', 'Commission (non remboursée)', 'À rembourser', 'État', 'Versé par', 'Référence', 'Versé le'], $lignes];
    }

    /** Par propriétaire : séjours, location, services, commission VIVI, retraits payés sur la période. */
    private function proprietaires(CarbonImmutable $du, CarbonImmutable $au): array
    {
        $acquises = [StatutReservation::EnCours, StatutReservation::Terminee, StatutReservation::Consommee];
        $parProprio = Reservation::with('logement.proprietaire')->whereIn('statut', $acquises)
            ->whereBetween('debut', [$du, $au])->get()
            ->groupBy(fn (Reservation $r) => $r->logement->proprietaire_id);
        $retraits = Retrait::where('statut', 'paye')->whereBetween('traite_le', [$du, $au])
            ->selectRaw('proprietaire_id, sum(montant) as total')->groupBy('proprietaire_id')->pluck('total', 'proprietaire_id');

        $lignes = $parProprio->map(function ($liste) use ($retraits) {
            $p = $liste->first()->logement->proprietaire;

            return [$p->name, $this->contact($p), $liste->count(), $liste->sum('montant_location'),
                $liste->sum('montant_services_proprietaire'), $liste->sum('montant_marketing'), $liste->sum('montant_gestion'),
                $liste->sum('montant_commission'), (int) ($retraits[$p->id] ?? 0)];
        })->values();

        return [['Propriétaire', 'Contact', 'Séjours', 'Location', 'Services (part propriétaire)', 'Part marketing retirée', 'Gestion VIVI retirée', 'Commission VIVI', 'Retraits payés'], $lignes];
    }

    /** Par agent marketing : séjours amenés par son lien, location générée, commissions et retraits payés sur la période. */
    private function agents(CarbonImmutable $du, CarbonImmutable $au): array
    {
        $acquises = [StatutReservation::EnCours, StatutReservation::Terminee, StatutReservation::Consommee];
        $parAgent = Reservation::with('agent')->whereNotNull('agent_id')->whereIn('statut', $acquises)
            ->whereBetween('debut', [$du, $au])->get()->groupBy('agent_id');
        $retraits = Retrait::where('statut', 'paye')->whereBetween('traite_le', [$du, $au])->whereIn('proprietaire_id', $parAgent->keys())
            ->selectRaw('proprietaire_id, sum(montant) as total')->groupBy('proprietaire_id')->pluck('total', 'proprietaire_id');

        $lignes = $parAgent->map(function ($liste) use ($retraits) {
            $a = $liste->first()->agent;

            return [$a?->name, $this->contact($a), $a?->code_agent, $liste->count(), $liste->sum('montant_location'),
                $liste->sum('montant_marketing'), (int) ($retraits[$a?->id] ?? 0)];
        })->sortByDesc(fn ($l) => $l[5])->values();

        return [['Agent', 'Contact', 'Code', 'Séjours', 'Location générée', 'Commissions', 'Retraits payés'], $lignes];
    }

    private function publicites(CarbonImmutable $du, CarbonImmutable $au): array
    {
        $lignes = \App\Models\PublicitePaiement::with(['publicite' => fn ($q) => $q->withSum('paiements', 'montant'), 'caissier'])
            ->whereBetween('paye_le', [$du->toDateString(), $au->toDateString()])->orderBy('paye_le')->orderBy('id')->get()
            ->map(fn (\App\Models\PublicitePaiement $p) => [
                $p->paye_le->format('d/m/Y'), $p->publicite->annonceur ?: 'VIVI', $p->publicite->annonceur_contact, $p->publicite->titre,
                $p->publicite->emplacement->getLabel(), $p->publicite->debut?->format('d/m/Y'), $p->publicite->fin?->format('d/m/Y'),
                $p->publicite->prix, $p->montant, $p->publicite->resteAPayer(), $p->mode, $p->reference, $p->caissier?->name,
            ]);

        return [['Reçu le', 'Annonceur', 'Contact', 'Publicité', 'Espace', 'Du', 'Au', 'Prix', 'Montant reçu', 'Reste à payer (aujourd’hui)', 'Mode', 'Référence', 'Encaissé par'], $lignes];
    }

    private function contact($u): string
    {
        return $u ? ($u->telephone ? Telephone::lisible($u->telephone) : (string) $u->email) : '';
    }
}
