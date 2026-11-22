<?php

namespace App\Http\Controllers\Api\V1\Finances;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Parametres;
use App\Support\Telephone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Compte sur lequel la caissière VIVI paie les retraits du propriétaire ou de l'agent marketing.
 * GET /compte-retrait · PUT /compte-retrait {moyen, numero, titulaire}
 */
class CompteRetraitController extends Controller
{
    /** Moyens proposés : ceux réglés par l'administration, sauf les espèces (pas de compte à renseigner). */
    public static function moyens(): array
    {
        return array_values(array_filter((array) Parametres::get('modes_reglement'), fn ($m) => mb_strtolower($m) !== 'espèces'));
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => self::compte($request->user())]);
    }

    public function update(Request $request): JsonResponse
    {
        $d = $request->validate([
            'moyen' => ['required', Rule::in(self::moyens())],
            'numero' => ['required', 'string', 'max:40'],
            'titulaire' => ['required', 'string', 'min:3', 'max:120'],
        ], [
            'moyen.in' => 'Choisissez un moyen de retrait proposé.',
            'titulaire.required' => 'Indiquez le nom du titulaire du compte.',
        ]);

        $virement = str_contains(mb_strtolower($d['moyen']), 'virement');
        if ($virement) {
            $numero = strtoupper(preg_replace('/\s+/', '', $d['numero']));
            if (strlen($numero) < 10) {
                throw ValidationException::withMessages(['numero' => 'Indiquez le RIB ou l’IBAN complet du compte bancaire.']);
            }
        } else {
            $numero = Telephone::normaliser($d['numero']);
            if (! $numero) {
                throw ValidationException::withMessages(['numero' => 'Numéro Mobile Money béninois à 10 chiffres, par exemple 01 97 00 00 00.']);
            }
        }

        $u = $request->user();
        $u->forceFill([
            'retrait_moyen' => $d['moyen'],
            'retrait_numero' => $numero,
            'retrait_titulaire' => trim($d['titulaire']),
            'retrait_modifie_le' => now(),
        ])->save();

        return response()->json([
            'message' => 'Compte de retrait enregistré : vos prochains retraits seront payés sur '.$d['moyen'].'.',
            'data' => self::compte($u),
        ]);
    }

    public static function compte(User $u): array
    {
        return [
            'moyens' => self::moyens(),
            'configure' => $u->retrait_moyen !== null && $u->retrait_numero !== null,
            'moyen' => $u->retrait_moyen,
            'numero' => $u->retrait_numero,
            'titulaire' => $u->retrait_titulaire,
            'modifie_le' => $u->retrait_modifie_le?->toIso8601String(),
        ];
    }
}