{{-- Espace propriétaire : revenus, mouvements du solde et retraits --}}
@php
    use App\Enums\StatutRetrait;
    use App\Support\Montant;

    $nombre = fn (int $n) => number_format($n, 0, ',', "\u{00A0}");
    $tauxTexte = \App\Services\Caisse::taux((float) $tauxRetrait);
@endphp

<x-layouts.pro titre="Revenus" page="revenus">
    <div class="pro-head">
        <div>
            <h1>Revenus</h1>
            <p>Chaque séjour démarré (QR Code scanné) s’ajoute à votre solde. Vos retraits sont validés par la caissière VIVI.</p>
        </div>
    </div>

    @error('retrait')
        <div class="note-box note-box--orange mb-3" role="alert"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span>{{ $message }}</span></div>
    @enderror
    @error('montant')
        <div class="note-box note-box--orange mb-3" role="alert"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span>{{ $message }}</span></div>
    @enderror

    <div class="row g-3">
        <div class="col-lg-5">
            <section class="panel h-100" aria-labelledby="t-solde">
                <h2 id="t-solde" class="visually-hidden">Solde</h2>
                <p class="kpi__label">Solde disponible</p>
                <p class="kpi__value mb-1">{{ $nombre($solde) }} <small>FCFA</small></p>
                <p class="text-soft small mb-3">{{ Montant::fcfa($ceMois) }} gagnés ce mois-ci.</p>
                @php
                    $initial = (int) old('montant', $solde);
                    $commissionInitiale = (int) round($initial * $tauxRetrait / 100);
                @endphp
                <form method="POST" action="{{ route('pro.retraits.demander') }}" class="d-grid gap-2"
                      data-confirmer="{{ $tauxRetrait > 0
                          ? '{montant} FCFA seront retenus sur votre solde, dont '.$tauxTexte.' de commission VIVI. La caisse VIVI valide le retrait et vous verse le reste.'
                          : '{montant} FCFA seront retenus sur votre solde. La caisse VIVI valide le retrait et vous verse le montant.' }}"
                      data-confirmer-titre="Demander un retrait ?" data-confirmer-bouton="Demander le retrait">
                    @csrf
                    <label class="form-label mb-0" for="montant">Montant à retirer de votre solde</label>
                    <div class="input-suffix">
                        <input class="form-control" type="number" inputmode="numeric" id="montant" name="montant" min="1000" max="{{ $solde }}" step="500"
                               value="{{ $initial }}" @disabled($solde < 1000) @if ($tauxRetrait > 0) data-taux-retrait="{{ $tauxRetrait }}" aria-describedby="decompte-retrait" @endif>
                        <span class="suffix">FCFA</span>
                    </div>

                    @if ($tauxRetrait > 0)
                        {{-- Décompte : ce qui sort du solde, la part réservée à VIVI, ce que vous recevez --}}
                        <dl class="decompte-retrait" id="decompte-retrait" aria-live="polite">
                            <div><dt>Retiré de votre solde</dt><dd class="tnum" data-decompte="montant">{{ Montant::fcfa($initial) }}</dd></div>
                            <div class="decompte-retrait__vivi"><dt>Commission VIVI ({{ $tauxTexte }})</dt><dd class="tnum" data-decompte="commission">− {{ Montant::fcfa($commissionInitiale) }}</dd></div>
                            <div class="decompte-retrait__net"><dt>Vous recevez</dt><dd class="tnum" data-decompte="net">{{ Montant::fcfa($initial - $commissionInitiale) }}</dd></div>
                        </dl>
                    @endif

                    <button class="btn btn-primary" type="submit" @disabled($solde < 1000)><i class="bi bi-cash-coin" aria-hidden="true"></i>Demander un retrait</button>
                </form>
                <div class="note-box mt-3">
                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                    @if ($tauxRetrait > 0)
                        <span>Vous êtes sur <strong>commission</strong> : pas d’abonnement à payer, VIVI garde {{ $tauxTexte }} de chaque retrait. Le montant demandé est retenu sur votre solde ; la caissière VIVI vous verse le reste et indique le mode de règlement. Un retrait refusé est recrédité en entier.</span>
                    @else
                        <span>Le montant demandé est retenu sur votre solde. La caissière VIVI le valide et indique le mode de règlement ; un retrait refusé est recrédité.</span>
                    @endif
                </div>
                @if ($tauxRetrait > 0)
                    <style>
                        .decompte-retrait { margin: 0; border: 1px solid var(--vivi-line); border-radius: 12px; overflow: hidden; }
                        .decompte-retrait > div { display: flex; justify-content: space-between; gap: 12px; padding: 8px 12px; font-size: .95rem; }
                        .decompte-retrait > div + div { border-top: 1px solid var(--vivi-line); }
                        .decompte-retrait dt { font-weight: 400; color: var(--vivi-muted); }
                        .decompte-retrait dd { margin: 0; font-weight: 600; color: var(--vivi-ink); white-space: nowrap; }
                        .decompte-retrait__vivi dd { color: var(--vivi-orange-ink); }
                        .decompte-retrait__net { background: var(--vivi-teal-50); }
                        .decompte-retrait__net dt { color: var(--vivi-teal-900); font-weight: 600; }
                        .decompte-retrait__net dd { color: var(--vivi-teal-900); font-size: 1.05rem; }
                    </style>
                    @push('scripts')
                    <script>
                        (() => {
                            const champ = document.getElementById('montant');
                            const taux = parseFloat(champ?.dataset.tauxRetrait || '0');
                            if (!champ || !taux) return;
                            const fcfa = n => new Intl.NumberFormat('fr-FR', {maximumFractionDigits: 0}).format(n).replace(/\s/g, ' ') + ' FCFA';
                            const zone = document.getElementById('decompte-retrait');
                            const maj = () => {
                                const m = Math.max(0, parseInt(champ.value, 10) || 0);
                                const c = Math.round(m * taux / 100);
                                zone.querySelector('[data-decompte="montant"]').textContent = fcfa(m);
                                zone.querySelector('[data-decompte="commission"]').textContent = '− ' + fcfa(c);
                                zone.querySelector('[data-decompte="net"]').textContent = fcfa(m - c);
                            };
                            champ.addEventListener('input', maj);
                        })();
                    </script>
                    @endpush
                @endif
            </section>
        </div>

        <div class="col-lg-7">
            <section class="panel h-100" aria-labelledby="t-retraits">
                <div class="panel__head"><h2 id="t-retraits">Retraits</h2></div>
                <ul class="rows">
                    @forelse ($retraits as $v)
                        @php
                            [$classe, $icone, $texte] = match ($v->statut) {
                                StatutRetrait::Paye => ['chip--success', 'bi-check-circle', 'Payé · '.$v->mode_reglement],
                                StatutRetrait::Refuse => ['chip--line', 'bi-x-circle', 'Refusé, montant recrédité'],
                                default => ['chip--orange', 'bi-hourglass-split', 'En attente de validation'],
                            };
                        @endphp
                        <li>
                            <div class="quand">{{ $v->created_at->translatedFormat('j M') }}</div>
                            <div class="qui"><strong class="tnum">{{ $v->reference }}</strong><span class="chip {{ $classe }}"><i class="bi {{ $icone }}" aria-hidden="true"></i>{{ $texte }}</span>
                                @if ($v->statut === StatutRetrait::Refuse && $v->note)<span>{{ $v->note }}</span>@endif</div>
                            {{-- Refusé : tout le montant a été recrédité, aucune commission n'a été prise --}}
                            <div class="action"><span class="montant">{{ Montant::fcfa($v->statut === StatutRetrait::Refuse ? (int) $v->montant : $v->aVerser()) }}</span>
                                @if ((int) $v->montant_commission > 0 && $v->statut !== StatutRetrait::Refuse)
                                    <span class="d-block small text-soft text-nowrap">sur {{ Montant::fcfa($v->montant) }} · commission {{ Montant::fcfa($v->montant_commission) }}</span>
                                @endif
                            </div>
                        </li>
                    @empty
                        <li style="display:block" class="text-soft">Aucun retrait pour le moment.</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>

    <section class="panel mt-3" aria-labelledby="t-mouvements">
        <h2 id="t-mouvements" class="mb-2">Historique du solde</h2>
        @if ($mouvements->isEmpty())
            <p class="text-soft mb-0">Votre solde n’a pas encore bougé : il s’alimente au premier séjour démarré.</p>
        @else
            <div class="table-responsive">
                <table class="table-simple">
                    <thead><tr><th scope="col">Date</th><th scope="col">Opération</th><th scope="col" class="num">Montant</th></tr></thead>
                    <tbody>
                        @foreach ($mouvements as $m)
                            <tr>
                                <td><span class="text-nowrap">{{ $m->created_at->translatedFormat('j M') }}<span class="d-none d-md-inline"> {{ $m->created_at->format('Y') }}</span></span><span class="d-block small text-soft">{{ $m->created_at->format('H:i') }}</span></td>
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
</x-layouts.pro>
