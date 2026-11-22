{{-- Espace agent marketing : liens d'affiliation, commissions, solde et retraits --}}
@php
    use App\Enums\StatutRetrait;
    use App\Support\Montant;
@endphp

<x-layouts.espace titre="Espace agent" page="agent">
    <div class="container py-3 py-lg-4" style="max-width:1040px">
        <h1 class="page-title mb-1">Espace agent marketing</h1>
        <p class="text-soft mb-3">Partagez le lien de chaque logement. Quand un client réserve par votre lien, votre commission est versée sur votre solde au début de son séjour (QR Code scanné à l’arrivée).</p>

        @error('retrait')
            <div class="note-box note-box--orange mb-3" role="alert"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span>{{ $message }}</span></div>
        @enderror
        @error('montant')
            <div class="note-box note-box--orange mb-3" role="alert"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span>{{ $message }}</span></div>
        @enderror

        <div class="row g-3 mb-3">
            <div class="col-md-5">
                <section class="panel h-100" aria-labelledby="t-solde">
                    <h2 id="t-solde" class="h6 text-soft fw-semibold mb-1">Solde disponible</h2>
                    <p class="bonus-solde__montant tnum mb-1">{{ Montant::fcfa($solde) }}</p>
                    <p class="small text-soft mb-3">{{ Montant::fcfa($ce_mois) }} gagnés ce mois-ci · {{ Montant::fcfa($total) }} depuis le début.</p>
                    <form method="POST" action="{{ route('agent.retraits') }}" class="d-grid gap-2"
                          data-confirmer="{montant} FCFA seront retenus sur votre solde. La caisse VIVI valide le retrait et vous verse le montant." data-confirmer-titre="Demander un retrait ?" data-confirmer-bouton="Demander le retrait">
                        @csrf
                        <label class="form-label mb-0" for="montant">Montant à retirer</label>
                        <div class="input-suffix">
                            <input class="form-control" type="number" inputmode="numeric" id="montant" name="montant" min="1000" max="{{ $solde }}" step="500"
                                   value="{{ old('montant', $solde) }}" @disabled($solde < 1000)>
                            <span class="suffix">FCFA</span>
                        </div>
                        <button class="btn btn-primary" type="submit" @disabled($solde < 1000)><i class="bi bi-cash-coin" aria-hidden="true"></i>Demander un retrait</button>
                    </form>
                </section>
            </div>
            <div class="col-md-7">
                <section class="panel h-100" aria-labelledby="t-resume">
                    <h2 id="t-resume" class="h6 text-soft fw-semibold mb-2">Vos résultats</h2>
                    <div class="row g-2 text-center">
                        <div class="col-4"><p class="kpi__value mb-0">{{ $logements->count() }}</p><p class="small text-soft mb-0">logement{{ $logements->count() > 1 ? 's' : '' }} promu{{ $logements->count() > 1 ? 's' : '' }}</p></div>
                        <div class="col-4"><p class="kpi__value mb-0">{{ $sejours }}</p><p class="small text-soft mb-0">séjour{{ $sejours > 1 ? 's' : '' }} réalisé{{ $sejours > 1 ? 's' : '' }}</p></div>
                        <div class="col-4"><p class="kpi__value mb-0">{{ $a_venir }}</p><p class="small text-soft mb-0">à venir</p></div>
                    </div>
                    <p class="small text-soft mt-3 mb-0">Votre code agent : <strong class="tnum text-ink">{{ $code }}</strong>. Il est déjà inclus dans vos liens. Si vous réservez vous-même par votre lien, aucune commission n’est versée.</p>
                </section>
            </div>
        </div>

        {{-- Logements promus --}}
        <section class="panel mb-3" aria-labelledby="t-logements">
            <h2 id="t-logements" class="mb-2">Vos logements</h2>
            @forelse ($logements as $i => $ligne)
                @php
                    $l = $ligne['logement'];
                    $message = 'Réservez « '.$l->titre.' » ('.$l->quartier.', '.$l->ville.') sur VIVI, à l’heure ou à la journée : '.$ligne['lien'];
                @endphp
                <div class="border-top pt-3 mt-3">
                    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-start mb-2">
                        <div style="min-width:0">
                            <strong class="text-ink d-block">{{ $l->titre }}</strong>
                            <span class="small text-soft">{{ $l->quartier }}, {{ $l->ville }} · propriétaire {{ $l->proprietaire?->name }}</span>
                        </div>
                        <div class="text-end">
                            @if ($ligne['part'])
                                <span class="chip chip--teal"><i class="bi bi-megaphone" aria-hidden="true"></i>{{ $ligne['part'] }}</span>
                            @else
                                <span class="chip chip--line">Aucune part fixée pour le moment</span>
                            @endif
                            @unless ($ligne['visible'])<span class="chip chip--orange ms-1">Pas en ligne</span>@endunless
                            <span class="small text-soft d-block mt-1">{{ $ligne['sejours'] }} séjour{{ $ligne['sejours'] > 1 ? 's' : '' }} · {{ Montant::fcfa($ligne['gains']) }} gagnés{{ $ligne['a_venir'] ? ' · '.$ligne['a_venir'].' à venir' : '' }}</span>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <label class="visually-hidden" for="lien-{{ $i }}">Lien de {{ $l->titre }}</label>
                        <input class="form-control" id="lien-{{ $i }}" type="text" value="{{ $ligne['lien'] }}" readonly>
                        <button class="btn btn-ghost flex-shrink-0" type="button" data-copier="{{ $ligne['lien'] }}" aria-label="Copier le lien"><i class="bi bi-copy" aria-hidden="true"></i></button>
                        <a class="btn btn-whatsapp flex-shrink-0" href="https://wa.me/?text={{ rawurlencode($message) }}" target="_blank" rel="noopener" aria-label="Partager sur WhatsApp"><i class="bi bi-whatsapp" aria-hidden="true"></i></a>
                    </div>
                </div>
            @empty
                <p class="text-soft mb-0">Aucun logement à promouvoir pour le moment : un propriétaire doit vous choisir comme agent.</p>
            @endforelse
        </section>

        <div class="row g-3">
            <div class="col-lg-7">
                <section class="panel h-100" aria-labelledby="t-mouvements">
                    <h2 id="t-mouvements" class="mb-2">Historique du solde</h2>
                    @if ($mouvements->isEmpty())
                        <p class="text-soft mb-0">Votre première commission apparaîtra ici.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table-simple">
                                <thead><tr><th scope="col">Date</th><th scope="col">Opération</th><th scope="col" class="num">Montant</th></tr></thead>
                                <tbody>
                                    @foreach ($mouvements as $m)
                                        <tr>
                                            <td class="text-nowrap">{{ $m->created_at->translatedFormat('j M Y') }}</td>
                                            <td>{{ $m->type->getLabel() }}<span class="d-block small text-soft">{{ $m->libelle }}</span></td>
                                            <td class="num" style="color:{{ $m->montant < 0 ? 'var(--vivi-danger)' : 'var(--vivi-success)' }}"><span class="text-nowrap">{{ $m->montant > 0 ? '+' : '−' }}&nbsp;{{ Montant::fcfa(abs($m->montant)) }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if ($mouvements->hasPages())<div class="mt-3">{{ $mouvements->links() }}</div>@endif
                    @endif
                </section>
            </div>
            <div class="col-lg-5">
                <section class="panel h-100" aria-labelledby="t-retraits">
                    <h2 id="t-retraits" class="mb-2">Retraits</h2>
                    <ul class="rows">
                        @forelse ($retraits as $v)
                            @php
                                [$classe, $texte] = match ($v->statut) {
                                    StatutRetrait::Paye => ['chip--success', 'Payé · '.$v->mode_reglement],
                                    StatutRetrait::Refuse => ['chip--line', 'Refusé, montant recrédité'],
                                    default => ['chip--orange', 'En attente de validation'],
                                };
                            @endphp
                            <li>
                                <div class="quand">{{ $v->created_at->translatedFormat('j M') }}</div>
                                <div class="qui"><strong class="tnum">{{ $v->reference }}</strong><span class="chip {{ $classe }}">{{ $texte }}</span></div>
                                <div class="action"><span class="montant">{{ Montant::fcfa($v->montant) }}</span></div>
                            </li>
                        @empty
                            <li style="display:block" class="text-soft">Aucun retrait pour le moment.</li>
                        @endforelse
                    </ul>
                </section>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            // Boutons « Copier » : lien d'affiliation
            document.querySelectorAll('[data-copier]').forEach(function (b) {
                b.addEventListener('click', function () {
                    var texte = b.dataset.copier;
                    var fini = function () { if (window.viviMessage) { window.viviMessage('Lien copié'); } };
                    if (navigator.clipboard) { navigator.clipboard.writeText(texte).then(fini, fini); } else { fini(); }
                });
            });
        </script>
    @endpush
</x-layouts.espace>
