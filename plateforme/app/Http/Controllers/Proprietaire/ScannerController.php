<?php

namespace App\Http\Controllers\Proprietaire;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\ArriveeRefusee;
use App\Services\Arrivees;
use App\Support\Montant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** « Scanner un QR Code » : vérification à l'arrivée du client, puis démarrage du séjour. */
class ScannerController extends Controller
{
    private const SESSION = 'scanner.reference';

    public function __construct(private readonly Arrivees $arrivees) {}

    public function index(Request $request): View
    {
        return view('pro.scanner', ['attendus' => $this->arrivees->attendus($request->user())]);
    }

    /** QR Code lu par la caméra, ou code de secours saisi. */
    public function verifier(Request $request): JsonResponse
    {
        $saisie = (string) $request->validate(['saisie' => ['required', 'string', 'max:120']])['saisie'];

        try {
            $r = $this->arrivees->verifier($request->user(), $saisie);
        } catch (ArriveeRefusee $e) {
            $request->session()->forget(self::SESSION);

            return response()->json(['valide' => false, 'titre' => $e->titre, 'texte' => $e->getMessage()], 422);
        }

        // Seule la réservation vérifiée à l'instant peut être démarrée
        $request->session()->put(self::SESSION, $r->reference);

        return response()->json(['valide' => true, 'reservation' => $this->fiche($r)]);
    }

    public function demarrer(Request $request): JsonResponse
    {
        $reference = (string) $request->validate(['reference' => ['required', 'string']])['reference'];
        abort_unless($request->session()->pull(self::SESSION) === $reference, 409, 'Vérifiez de nouveau le code du client.');

        try {
            $r = $this->arrivees->demarrer($request->user(), Reservation::where('reference', $reference)->firstOrFail());
        } catch (ArriveeRefusee $e) {
            return response()->json(['valide' => false, 'titre' => $e->titre, 'texte' => $e->getMessage()], 422);
        }
        $credit = $r->creditProprietaire();

        return response()->json([
            'heure' => $r->scanne_le->format('H:i'),
            'client' => $r->client->name,
            'logement' => $r->logement->titre,
            'credit' => $r->estManuelle() ? null : Montant::fcfa($credit),   // hors plateforme : rien n'est crédité
        ]);
    }

    private function fiche(Reservation $r): array
    {
        $services = $r->services->map(fn ($s) => $s->nom.($s->quantite > 1 ? ' × '.$s->quantite : ''))->implode(', ');

        return [
            'reference' => $r->reference,
            'client' => $r->client->name.$r->suffixePersonnes(),   // une salle n'a pas de nombre de voyageurs
            'logement' => $r->logement->titre,
            'mot' => ucfirst($r->logement->mot()),
            'creneau' => ucfirst($r->debut->translatedFormat('D j M')).' · '.$r->creneauEnClair(),
            'services' => $services !== '' ? $services : 'Aucun service',
        ];
    }
}
