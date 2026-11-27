<?php

namespace App\Http\Controllers\Api\V1\Pro;

use App\Enums\StatutAbonnement;
use App\Enums\StatutLogement;
use App\Http\Controllers\Api\V1\PaiementsController;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Format;
use App\Models\FormuleAbonnement;
use App\Models\Paiement;
use App\Models\Retrait;
use App\Models\User;
use App\Paiement\EchecPaiement;
use App\Paiement\Passerelle;
use App\Services\Abonnements;
use App\Services\Caisse;
use App\Services\Delegations;
use App\Services\ReservationImpossible;
use App\Support\Montant;
use App\Support\Parametres;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Revenus, retraits, abonnement et gestionnaires : réservé au propriétaire. */
class FinancesController extends Controller
{
    /* ---------- Revenus et retraits ---------- */

    public function revenus(Request $request): JsonResponse
    {
        $p = $request->user();
        $mouvements = $p->mouvementsSolde()->with('reservation:id,reference')->latest('id')->paginate(20);

        return response()->json([
            'data' => [
                'solde' => $p->solde(),
                'ce_mois' => (int) $p->mouvementsSolde()->where('montant', '>', 0)->whereNotNull('reservation_id')->where('created_at', '>=', now()->startOfMonth())->sum('montant'),
                'retrait_minimum' => 1000,
                // Sur commission : % gardé par VIVI sur chaque retrait (0 sur abonnement). Net = montant − round(montant × taux / 100)
                'commission_retrait_taux' => app(\App\Services\ModeleEconomique::class)->taux($p),
                'mouvements' => $mouvements->getCollection()->map(fn ($m) => [
                    'id' => $m->id, 'type' => Format::enum($m->type), 'montant' => (int) $m->montant, 'libelle' => $m->libelle,
                    'reservation' => $m->reservation?->reference, 'le' => Format::date($m->created_at),
                ]),
                'retraits' => $p->retraits()->latest('id')->limit(20)->get()->map(fn ($r) => self::retrait($r)),
            ],
            'meta' => ['page' => $mouvements->currentPage(), 'derniere_page' => $mouvements->lastPage(), 'total' => $mouvements->total()],
        ]);
    }

    /** POST /pro/retraits {montant} (tout le solde si absent) : la caissière VIVI valide et indique le mode de règlement. */
    public function demanderRetrait(Request $request, Caisse $caisse): JsonResponse
    {
        $p = $request->user();
        if (! $p->retrait_moyen || ! $p->retrait_numero) {
            throw ValidationException::withMessages(['compte' => 'Indiquez d’abord le numéro sur lequel recevoir vos retraits.']);
        }
        $solde = $p->solde();
        if ($solde <= 0) {
            throw ValidationException::withMessages(['montant' => 'Votre solde est vide : aucun retrait possible pour le moment.']);
        }
        $montant = (int) ($request->validate(['montant' => ['nullable', 'integer', 'min:1000', 'max:'.$solde]], [
            'montant.max' => 'Vous ne pouvez pas retirer plus que votre solde.',
            'montant.min' => 'Le retrait minimum est de 1 000 FCFA.',
        ])['montant'] ?? $solde);

        try {
            $retrait = $caisse->demanderRetrait($p, $montant);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['montant' => $e->getMessage()]);
        }

        return response()->json([
            'message' => 'Demande de retrait '.$retrait->reference.' de '.Montant::fcfa($retrait->montant).' envoyée'
                .((int) $retrait->montant_commission > 0 ? ' (commission VIVI '.Montant::fcfa($retrait->montant_commission).', vous recevrez '.Montant::fcfa($retrait->aVerser()).')' : '')
                .'. La caissière VIVI la paiera sur '.$p->retrait_moyen.' ('.$p->retrait_numero.').',
            'data' => self::retrait($retrait),
        ], 201);
    }

    public static function retrait(Retrait $r): array
    {
        return [
            'reference' => $r->reference, 'montant' => (int) $r->montant, 'statut' => Format::enum($r->statut),
            // Retenu sur le solde = montant ; versé = montant_net (commission VIVI déduite sur le modèle « commission »)
            'taux_commission' => (float) $r->taux_commission, 'montant_commission' => (int) $r->montant_commission, 'montant_net' => $r->aVerser(),
            'compte' => $r->compte_numero ? ['moyen' => $r->compte_moyen, 'numero' => $r->compte_numero, 'titulaire' => $r->compte_titulaire] : null,
            'mode_reglement' => $r->mode_reglement, 'reference_reglement' => $r->reference_reglement,
            'traite_le' => Format::date($r->traite_le), 'note' => $r->note, 'le' => Format::date($r->created_at),
        ];
    }

    /* ---------- Abonnement ---------- */

    public function abonnement(Request $request, Abonnements $abonnements): JsonResponse
    {
        $p = $request->user();
        $actif = $p->abonnementActif()->with('formule')->first();
        $suivant = $actif ? $p->abonnements()->with('formule')->where('statut', StatutAbonnement::Actif)
            ->whereKeyNot($actif->getKey())->whereDate('debut', '>', today())->first() : null;
        $abo = fn ($a) => $a ? [
            'id' => $a->id, 'formule' => $a->formule->nom, 'formule_id' => $a->formule_abonnement_id,
            'debut' => Format::jour($a->debut), 'fin' => Format::jour($a->fin), 'essai' => (bool) $a->essai,
            'prix_paye' => (int) $a->prix_paye, 'statut' => Format::enum($a->statut),
        ] : null;

        $modele = app(\App\Services\ModeleEconomique::class);

        return response()->json(['data' => [
            // « commission » : pas de formule à payer, VIVI garde commission_retrait_taux % de chaque retrait (formules vides)
            'modele' => $modele->modele($p),
            'commission_retrait_taux' => $modele->taux($p),
            'quotas_commission' => $modele->quotas($p),
            'actif' => $abo($actif),
            'suivant' => $abo($suivant),
            'logements_utilises' => $p->logements()->whereIn('statut', [StatutLogement::Publie, StatutLogement::EnValidation])->count(),
            'promotion' => $abonnements->promotionEnCours(),
            'essai' => [
                'possible' => $abonnements->essaiPossible($p),
                'jours' => (int) Parametres::get('essai_jours'),
                'formule_id' => (int) Parametres::get('essai_formule_id') ?: null,   // null : au choix
            ],
            'formules' => $modele->surCommission($p) ? [] : FormuleAbonnement::where('actif', true)->orderBy('ordre')->orderBy('prix_mensuel')->get()->map(fn ($f) => [
                'id' => $f->id, 'nom' => $f->nom, 'logements_max' => (int) $f->logements_max, 'salles_max' => (int) $f->salles_max, 'gestionnaires_max' => (int) $f->gestionnaires_max,
                'avantages' => $f->avantages, 'tarif' => $abonnements->tarif($p, $f),
            ]),
            'paiements' => Paiement::with('payable.formule')->where('payable_type', 'abonnement')->where('payeur_id', $p->getKey())
                ->latest()->limit(12)->get()->map(fn ($pa) => PaiementsController::etat($pa) + ['formule' => $pa->payable?->formule?->nom]),
        ]]);
    }

    /** POST /pro/abonnement/{formule} : paiement ouvert chez FedaPay, abonnement activé au paiement. */
    public function souscrire(Request $request, FormuleAbonnement $formule, Abonnements $abonnements, Passerelle $passerelle): JsonResponse
    {
        try {
            $paiement = $abonnements->souscrire($request->user(), $formule, $passerelle->nom());
            $url = $passerelle->demarrer($paiement, route('paiement.retour-app', $paiement));
        } catch (ReservationImpossible|EchecPaiement $e) {
            throw ValidationException::withMessages(['abonnement' => $e->getMessage()]);
        }

        return response()->json(['data' => ['url_paiement' => $url, 'paiement' => PaiementsController::etat($paiement->refresh())]]);
    }

    /** POST /pro/abonnement/{formule}/essai : essai gratuit, sans paiement (une fois par propriétaire). */
    public function essai(Request $request, FormuleAbonnement $formule, Abonnements $abonnements): JsonResponse
    {
        try {
            $a = $abonnements->commencerEssai($request->user(), $formule);
        } catch (ReservationImpossible $e) {
            throw ValidationException::withMessages(['abonnement' => $e->getMessage()]);
        }

        return response()->json([
            'message' => 'Votre essai gratuit de la formule '.$formule->nom.' a commencé : il se termine le '.$a->fin->translatedFormat('l j F').'.',
            'data' => ['id' => $a->id, 'formule' => $formule->nom, 'debut' => Format::jour($a->debut), 'fin' => Format::jour($a->fin), 'essai' => true],
        ], 201);
    }

    /* ---------- Gestionnaires ---------- */

    public function gestionnaires(Request $request, Delegations $delegations): JsonResponse
    {
        $p = $request->user();

        return response()->json(['data' => [
            'maximum' => $delegations->maximum($p),
            'gestionnaires' => $delegations->gestionnairesDe($p)->map(fn (User $g) => self::gestionnaire($g)),
            'logements' => $p->logements()->orderBy('titre')->get(['id', 'titre']),
        ]]);
    }

    /** POST /pro/gestionnaires {nom, identifiant (email ou téléphone), logements[]} : un message lui explique comment se connecter. */
    public function confier(Request $request, Delegations $delegations): JsonResponse
    {
        $d = $request->validate([
            'nom' => ['required', 'string', 'min:3', 'max:120'],
            'identifiant' => ['required', 'string', 'max:255'],
            'logements' => ['required', 'array', 'min:1'],
            'logements.*' => ['integer'],
        ], ['logements.required' => 'Choisissez au moins un logement à confier.']);

        try {
            $g = $delegations->confier($request->user(), $d['nom'], $d['identifiant'], $d['logements']);
        } catch (ReservationImpossible $e) {
            throw ValidationException::withMessages(['identifiant' => $e->getMessage()]);
        }

        return response()->json(['message' => $g->name.' est votre gestionnaire : un message lui explique comment se connecter.',
            'data' => self::gestionnaire($delegations->gestionnairesDe($request->user())->firstWhere('id', $g->id) ?? $g)], 201);
    }

    /** PUT /pro/gestionnaires/{id} {logements[]} : liste vide = accès retiré. */
    public function modifierGestionnaire(Request $request, User $gestionnaire, Delegations $delegations): JsonResponse
    {
        abort_unless($delegations->gestionnairesDe($request->user())->contains('id', $gestionnaire->id), 404);
        $logements = $request->validate(['logements' => ['array'], 'logements.*' => ['integer']])['logements'] ?? [];
        $delegations->modifier($request->user(), $gestionnaire, $logements);

        return response()->json(['message' => $logements === [] ? 'Accès de '.$gestionnaire->name.' retiré.' : 'Logements confiés à '.$gestionnaire->name.' mis à jour.']);
    }

    private static function gestionnaire(User $g): array
    {
        return [
            'id' => $g->id, 'nom' => $g->name, 'email' => $g->email, 'telephone' => $g->telephone,
            'logements' => $g->relationLoaded('logementsDelegues') ? $g->logementsDelegues->map(fn ($l) => ['id' => $l->id, 'titre' => $l->titre])->values() : [],
        ];
    }
}
