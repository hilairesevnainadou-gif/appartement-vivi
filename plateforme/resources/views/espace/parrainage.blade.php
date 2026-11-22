{{-- Parrainage et bonus : code à partager, amis invités, bonus disponible et historique --}}
@php
    use App\Models\Bonus;
    use App\Support\Montant;
@endphp

<x-layouts.espace titre="Parrainage et bonus" page="parrainage">
    <div class="container py-3 py-lg-4" style="max-width:960px">
        <h1 class="page-title mb-1">Parrainage et bonus</h1>
        <p class="text-soft mb-4">
            @if ($actif)
                Invitez vos amis sur VIVI. @if ($bonusParrain > 0)Après le premier séjour de chacun, vous recevez <strong>{{ Montant::fcfa($bonusParrain) }}</strong> de bonus.@endif
                Votre bonus se déduit de vos prochaines réservations.
            @else
                Votre bonus se déduit de vos prochaines réservations.
            @endif
        </p>

        <div class="row g-3 mb-3">
            {{-- Bonus disponible --}}
            <div class="col-md-5">
                <section class="panel h-100 bonus-solde" aria-labelledby="t-bonus">
                    <h2 id="t-bonus" class="h6 text-soft fw-semibold mb-1">Bonus disponible</h2>
                    <p class="bonus-solde__montant tnum mb-1">{{ Montant::fcfa($disponible) }}</p>
                    @if ($prochaineExpiration)
                        <p class="form-text mb-3">{{ Montant::fcfa($prochaineExpiration->restant) }} à utiliser avant le {{ $prochaineExpiration->expire_le->translatedFormat('j F Y') }}.</p>
                    @else
                        <p class="form-text mb-3">Aucun bonus pour le moment.</p>
                    @endif
                    <p class="small mb-3">Au moment de payer une réservation, activez « Utiliser mon bonus » : il couvre jusqu’à {{ $plafond }} % du total.</p>
                    <a class="btn btn-cta w-100 mt-auto" href="{{ route('recherche') }}"><i class="bi bi-search" aria-hidden="true"></i>Réserver un logement</a>
                </section>
            </div>

            {{-- Code d'invitation --}}
            <div class="col-md-7">
                <section class="panel h-100" aria-labelledby="t-code">
                    <h2 id="t-code" class="h6 text-soft fw-semibold mb-2">Votre code d’invitation</h2>
                    @if ($actif && $code)
                        <div class="code-parrain">
                            <span class="code-parrain__code tnum" id="code-parrain">{{ $code }}</span>
                            <button class="btn btn-ghost btn-sm" type="button" data-copier="{{ $code }}"><i class="bi bi-copy" aria-hidden="true"></i>Copier</button>
                        </div>
                        <label class="form-label mt-3 mb-1 small" for="lien-parrain">Ou partagez ce lien</label>
                        <div class="d-flex gap-2 lien-parrain">
                            <input class="form-control" id="lien-parrain" type="text" value="{{ $lien }}" readonly>
                            <button class="btn btn-ghost flex-shrink-0" type="button" data-copier="{{ $lien }}" aria-label="Copier le lien"><i class="bi bi-link-45deg" aria-hidden="true"></i></button>
                        </div>
                        <a class="btn btn-whatsapp w-100 mt-3" href="{{ $partageWhatsapp }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp" aria-hidden="true"></i>Inviter sur WhatsApp</a>
                        <ol class="etapes-parrain mt-3 mb-0">
                            <li>Votre ami crée son compte avec votre code{{ $bonusFilleul > 0 ? ' et reçoit '.Montant::fcfa($bonusFilleul).' de bonus' : '' }}.</li>
                            <li>Il réserve et arrive au logement (QR Code scanné).</li>
                            <li>Vous recevez {{ Montant::fcfa($bonusParrain) }} de bonus, valable {{ \App\Support\Parametres::get('parrainage_validite_jours') }} jours.</li>
                        </ol>
                    @else
                        <p class="text-soft mb-0">Le parrainage n’est pas ouvert pour le moment. Revenez bientôt !</p>
                    @endif
                </section>
            </div>
        </div>

        {{-- Amis invités --}}
        @if ($filleuls->isNotEmpty())
            <section class="panel mb-3" aria-labelledby="t-filleuls">
                <h2 id="t-filleuls" class="mb-2">Amis invités <span class="text-soft fw-normal">({{ $filleuls->count() }})</span></h2>
                <ul class="liste-filleuls">
                    @foreach ($filleuls as $f)
                        <li>
                            <span class="avatar-initiales" aria-hidden="true">{{ $f->initiales() }}</span>
                            <span class="flex-grow-1"><strong>{{ $f->prenom() }}</strong><span class="d-block small text-soft">Inscrit le {{ $f->created_at->translatedFormat('j M Y') }}</span></span>
                            @if ($f->parrainage_recompense_le)
                                <span class="chip chip--success"><i class="bi bi-gift" aria-hidden="true"></i>Bonus reçu</span>
                            @else
                                <span class="chip chip--line"><i class="bi bi-hourglass-split" aria-hidden="true"></i>Premier séjour attendu</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- Historique --}}
        <section class="panel" aria-labelledby="t-historique">
            <h2 id="t-historique" class="mb-2">Historique de vos bonus</h2>
            @if ($historique->isEmpty())
                <p class="text-soft mb-0">Vos bonus apparaîtront ici.</p>
            @else
                <div class="table-responsive">
                    <table class="table-simple">
                        <thead><tr><th scope="col">Reçu le</th><th scope="col">Motif</th><th scope="col" class="num">Montant</th><th scope="col" class="num d-none d-sm-table-cell">Reste</th><th scope="col" class="d-none d-md-table-cell">Valable jusqu’au</th></tr></thead>
                        <tbody>
                            @foreach ($historique as $b)
                                @php $expire = $b->expire_le->isPast() && ! $b->expire_le->isToday(); @endphp
                                <tr @class(['text-soft' => $expire || $b->restant === 0])>
                                    <td class="text-nowrap">{{ $b->created_at->translatedFormat('j M Y') }}</td>
                                    <td>{{ Bonus::libelleMotif($b->motif) }}<span class="d-block small text-soft">{{ $b->libelle }}</span><span class="d-block d-md-none small text-soft">{{ $expire ? "Expiré" : "Jusqu’au ".$b->expire_le->translatedFormat("j M Y") }}{{ $b->restant !== $b->montant ? " · reste ".Montant::fcfa($b->restant) : "" }}</span></td>
                                    <td class="num text-nowrap">{{ Montant::fcfa($b->montant) }}</td>
                                    <td class="num text-nowrap d-none d-sm-table-cell">{{ Montant::fcfa($b->restant) }}</td>
                                    <td class="text-nowrap d-none d-md-table-cell">{{ $expire ? 'Expiré' : $b->expire_le->translatedFormat('j M Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>

    @push('scripts')
        <script>
            // Boutons « Copier » : code ou lien d'invitation
            document.querySelectorAll('[data-copier]').forEach(function (b) {
                b.addEventListener('click', function () {
                    var texte = b.dataset.copier;
                    var fini = function () { if (window.viviMessage) { window.viviMessage('Copié : ' + texte); } };
                    if (navigator.clipboard) { navigator.clipboard.writeText(texte).then(fini, fini); }
                    else { var c = document.getElementById('lien-parrain'); c.select(); document.execCommand('copy'); fini(); }
                });
            });
        </script>
    @endpush
</x-layouts.espace>
