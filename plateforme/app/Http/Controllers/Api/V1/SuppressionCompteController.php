<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Inscriptions;
use App\Services\SuppressionCompte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Suppression du compte depuis l'application (exigée par Google Play). */
class SuppressionCompteController extends Controller
{
    public function __construct(private readonly SuppressionCompte $suppression) {}

    public function show(Request $request): JsonResponse
    {
        $u = $request->user();
        $obstacles = $this->suppression->obstacles($u);
        $destination = $this->suppression->destination($u);

        return response()->json(['data' => [
            'possible' => $obstacles === [],
            'obstacles' => $obstacles,
            'pertes' => $this->suppression->pertes($u),
            'canal' => $destination['canal'] ?? null,
            'destination' => $destination['lisible'] ?? null,
        ]]);
    }

    public function code(Request $request, Inscriptions $inscriptions): JsonResponse
    {
        $envoi = $this->suppression->envoyerCode($request->user(), $request->ip());

        return response()->json([
            'message' => 'Un code vient d’être envoyé '.($envoi['destination']['canal'] === 'sms' ? 'par SMS au ' : 'à ').$envoi['destination']['lisible'].'.',
            'code_dev' => $inscriptions->codeAAfficher($envoi['code']),
        ]);
    }

    public function confirmer(Request $request): JsonResponse
    {
        $code = (string) $request->validate(['code' => ['required', 'string']], ['code.required' => 'Saisissez le code reçu.'])['code'];
        $this->suppression->confirmer($request->user(), $code, $request->ip());

        return response()->json(['message' => 'Votre compte est supprimé.']);
    }
}
