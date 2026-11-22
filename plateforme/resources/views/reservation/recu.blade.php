{{-- Reçu de paiement, imprimable (Ctrl+P ou « Enregistrer en PDF » depuis le navigateur) --}}
@php
    use App\Models\Reservation;
    use App\Support\Montant;
    use App\Support\Telephone;

    $r = $reservation;
@endphp
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Reçu {{ $r->reference }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
    <style>
        body { background: var(--vivi-sand); }
        .recu { max-width: 760px; margin: 32px auto; background: #fff; border: 1px solid var(--vivi-line); border-radius: 16px; padding: 36px; }
        .recu header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; border-bottom: 1px solid var(--vivi-line); padding-bottom: 20px; margin-bottom: 24px; }
        .recu h1 { font-size: 1.6rem; margin: 0 0 .25rem; }
        .recu table { width: 100%; border-collapse: collapse; }
        .recu td, .recu th { padding: 10px 0; border-bottom: 1px solid var(--vivi-line); text-align: left; }
        .recu td:last-child, .recu th:last-child { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .recu tfoot td { font-weight: 700; color: var(--vivi-ink); border-bottom: 0; font-size: 1.1rem; }
        .recu .mention { font-size: .85rem; color: var(--vivi-muted); margin-top: 24px; }
        @media print { body { background: #fff; } .recu { border: 0; margin: 0; padding: 0; } .no-print { display: none !important; } }
    </style>
</head>
<body>
    <main class="recu">
        <header>
            <div>
                <img src="{{ asset('images/logo/vivi-logo.svg') }}" alt="VIVI, appartements et salles" height="40">
                <p class="text-soft small mb-0 mt-2">Appartement VIVI · Nova Tech Bénin</p>
            </div>
            <div class="text-end">
                <h1>Reçu de paiement</h1>
                <p class="mb-0 tnum">Réservation <strong>{{ $r->reference }}</strong></p>
                @if ($paiement)<p class="mb-0 small text-soft">Payé le {{ $paiement->paye_le?->translatedFormat('j F Y à H:i') }}</p>@endif
            </div>
        </header>

        <div class="row g-4 mb-4">
            <div class="col-sm-6">
                <p class="text-soft small mb-1">Client</p>
                <p class="mb-0"><strong>{{ $r->client->name }}</strong><br>
                    {{ $r->client->telephone ? Telephone::lisible($r->client->telephone) : '' }}{{ $r->client->telephone && $r->client->email ? ' · ' : '' }}{{ $r->client->email }}</p>
            </div>
            <div class="col-sm-6">
                <p class="text-soft small mb-1">{{ $r->logement->estSalle() ? ucfirst($r->logement->mot()) : 'Séjour' }}</p>
                <p class="mb-0"><strong>{{ $r->logement->titre }}</strong>, {{ $r->logement->quartier }}, {{ $r->logement->ville }}<br>
                    {{ ucfirst($r->debut->translatedFormat('l j F Y')) }} · {{ $r->creneauEnClair() }}{{ $r->suffixePersonnes() }}</p>
            </div>
        </div>

        <table>
            <thead><tr><th>Désignation</th><th>Montant</th></tr></thead>
            <tbody>
                <tr><td>Location ({{ mb_strtolower($r->formule->getLabel()) }})</td><td>{{ Montant::fcfa($r->montant_location) }}</td></tr>
                @foreach ($r->services as $s)
                    <tr><td>{{ $s->nom }} · {{ $s->quantite }} × {{ Montant::fcfa($s->prix_unitaire) }}</td><td>{{ Montant::fcfa($s->montant) }}</td></tr>
                @endforeach
                <tr><td>Commission VIVI ({{ rtrim(rtrim((string) $r->taux_commission, '0'), '.') }} %)</td><td>{{ Montant::fcfa($r->montant_commission) }}</td></tr>
                @if ($r->remise_agent > 0)
                    <tr><td>Remise agent (part marketing)</td><td>− {{ Montant::fcfa($r->remise_agent) }}</td></tr>
                @endif
            </tbody>
            <tfoot>
                @if ($r->montant_bonus > 0)
                    <tr><td>Total</td><td>{{ Montant::fcfa($r->montant_total) }}</td></tr>
                    <tr><td>Réglé avec le bonus VIVI</td><td>− {{ Montant::fcfa($r->montant_bonus) }}</td></tr>
                @endif
                <tr><td>Total payé</td><td>{{ Montant::fcfa($r->montantPaye()) }}</td></tr>
            </tfoot>
        </table>

        <p class="mention">
            Paiement {{ $paiement?->moyen ? 'par '.$paiement->moyen : '' }}
            {{ $paiement?->passerelle === 'simulation' ? 'simulé (essai, aucun montant débité)' : 'traité par FedaPay' }}{{ $paiement?->reference_passerelle ? ', transaction '.$paiement->reference_passerelle : '' }}.
            @if ($r->montant_rembourse > 0) Remboursé : {{ Montant::fcfa($r->montant_rembourse) }}. @endif
            Montants en francs CFA (XOF).
        </p>

        <div class="d-flex gap-2 no-print mt-4">
            <button class="btn btn-primary" type="button" onclick="window.print()"><i class="bi bi-printer" aria-hidden="true"></i>Imprimer ou enregistrer en PDF</button>
            <a class="btn btn-ghost" href="{{ route('reservation.voir', $r->reference) }}">Retour à ma réservation</a>
        </div>
    </main>
</body>
</html>
