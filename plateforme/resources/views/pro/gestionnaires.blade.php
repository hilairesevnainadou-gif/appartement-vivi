{{-- Espace propriétaire : gestionnaires délégués --}}
@php use App\Support\Telephone; @endphp

<x-layouts.pro titre="Gestionnaires" page="gestionnaires">
    <div class="pro-head">
        <div>
            <h1>Gestionnaires</h1>
            <p>Confiez l’accueil de vos clients à une personne de confiance : elle voit les arrivées et le calendrier des logements confiés, et scanne les QR Codes. Elle ne voit ni votre solde, ni vos tarifs.</p>
        </div>
    </div>

    <section class="panel mb-3" aria-label="Gestionnaires inclus dans l’abonnement">
        <div class="quota">
            <div style="min-width:0">
                <strong class="text-ink">{{ $gestionnaires->count() }} gestionnaire{{ $gestionnaires->count() > 1 ? 's' : '' }} sur {{ $maximum >= 99 ? 'illimité' : $maximum }}</strong>
                <div class="small text-soft">{{ $maximum === 0 ? 'Votre formule n’inclut pas de gestionnaire délégué.' : 'Inclus dans votre formule d’abonnement.' }}</div>
            </div>
            <div class="meter" aria-hidden="true"><i style="width:{{ $maximum ? min(100, $gestionnaires->count() / $maximum * 100) : 0 }}%"></i></div>
            <a class="btn btn-ghost btn-sm" href="{{ route('pro.abonnement') }}">Abonnement</a>
        </div>
    </section>

    <div class="row g-3">
        <div class="col-lg-7">
            <section class="panel h-100" aria-labelledby="t-liste">
                <div class="panel__head"><h2 id="t-liste">Vos gestionnaires</h2></div>
                @forelse ($gestionnaires as $g)
                    <form method="POST" action="{{ route('pro.gestionnaires.update', $g) }}" class="border-top pt-3 mt-3 first-of-type-sans-bordure">
                        @csrf
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="avatar" aria-hidden="true">{{ $g->initiales() }}</span>
                            <div><strong class="text-ink d-block">{{ $g->name }}</strong>
                                <span class="small text-soft">{{ $g->telephone ? Telephone::lisible($g->telephone) : $g->email }}</span></div>
                        </div>
                        <div class="row g-1">
                            @foreach ($logements as $l)
                                <div class="col-sm-6"><div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="logements[]" value="{{ $l->id }}" id="g{{ $g->id }}-l{{ $l->id }}" @checked($g->logementsDelegues->contains('id', $l->id))>
                                    <label class="form-check-label" for="g{{ $g->id }}-l{{ $l->id }}">{{ $l->titre }}</label>
                                </div></div>
                            @endforeach
                        </div>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <button class="btn btn-primary btn-sm" type="submit" data-confirmer="{{ $g->name }} aura accès seulement aux logements cochés (arrivées, calendrier, scanner)." data-confirmer-titre="Mettre à jour les logements confiés ?" data-confirmer-bouton="Enregistrer">Enregistrer</button>
                            <button class="btn btn-ghost btn-sm" type="submit" data-confirmer-decocher
                                    data-confirmer="{{ $g->name }} ne pourra plus scanner les QR Codes ni voir le calendrier de vos logements." data-confirmer-titre="Retirer l’accès ?" data-confirmer-bouton="Retirer l’accès" data-confirmer-style="danger">Retirer l’accès</button>
                        </div>
                    </form>
                @empty
                    <p class="text-soft mb-0">Aucun gestionnaire pour le moment.</p>
                @endforelse
            </section>
        </div>

        <div class="col-lg-5">
            <section class="panel h-100" aria-labelledby="t-ajout">
                <h2 id="t-ajout">Ajouter un gestionnaire</h2>
                @if ($maximum === 0 || $gestionnaires->count() >= $maximum)
                    <p class="text-soft">{{ $maximum === 0 ? 'Votre formule n’inclut pas de gestionnaire.' : 'Vous avez atteint le nombre de gestionnaires de votre formule.' }}</p>
                    <a class="btn btn-outline-primary" href="{{ route('pro.abonnement') }}">Voir les formules</a>
                @else
                    <form method="POST" action="{{ route('pro.gestionnaires.store') }}" class="d-grid gap-3" novalidate
                          data-confirmer="{nom} pourra scanner les QR Codes et voir le calendrier et les réservations des logements cochés. Il ne voit ni votre solde ni vos tarifs." data-confirmer-titre="Confier l’accueil ?" data-confirmer-bouton="Confier">
                        @csrf
                        <div>
                            <label class="form-label" for="g-nom">Nom et prénom</label>
                            <input class="form-control @error('nom') is-invalid @enderror" type="text" id="g-nom" name="nom" value="{{ old('nom') }}" required>
                            @error('nom')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="form-label" for="g-identifiant">Téléphone ou email</label>
                            <input class="form-control @error('identifiant') is-invalid @enderror" type="text" id="g-identifiant" name="identifiant" value="{{ old('identifiant') }}" placeholder="01 97 00 00 00" required>
                            @error('identifiant')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <p class="form-text mb-0">La personne se connecte avec ce numéro ou cette adresse, par un code.</p>
                        </div>
                        <fieldset>
                            <legend class="form-label">Logements confiés</legend>
                            @foreach ($logements as $l)
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="logements[]" value="{{ $l->id }}" id="n-l{{ $l->id }}" @checked(in_array($l->id, array_map('intval', old('logements', [])), true))>
                                    <label class="form-check-label" for="n-l{{ $l->id }}">{{ $l->titre }}</label></div>
                            @endforeach
                            @error('logements')<p class="small mb-0" style="color:var(--vivi-danger)">{{ $message }}</p>@enderror
                        </fieldset>
                        <button class="btn btn-primary" type="submit"><i class="bi bi-person-plus" aria-hidden="true"></i>Confier l’accueil</button>
                    </form>
                @endif
            </section>
        </div>
    </div>
</x-layouts.pro>
