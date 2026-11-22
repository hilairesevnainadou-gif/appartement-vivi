{{-- Espace propriétaire : agents marketing (classement, logements promus, désignation) --}}
@php
    use App\Support\Montant;
    use App\Support\Telephone;
@endphp

<x-layouts.pro titre="Agents marketing" page="agents">
    <div class="pro-head">
        <div>
            <h1>Agents marketing</h1>
            <p>Vos agents partagent le lien de vos logements. Pour chaque séjour réservé par leur lien, la part marketing du logement leur est versée. Sans agent, elle revient à VIVI. Ils ne voient ni votre calendrier, ni vos clients, ni votre solde.</p>
        </div>
    </div>

    {{-- Part marketing de chaque logement --}}
    <section class="panel mb-3" aria-labelledby="t-parts">
        <div class="panel__head"><h2 id="t-parts">Part marketing de vos logements</h2></div>
        <ul class="rows">
            @forelse ($logements as $l)
                <li class="d-flex flex-wrap align-items-center gap-2 justify-content-between">
                    <strong class="text-ink">{{ $l->titre }}</strong>
                    <span class="d-flex align-items-center gap-2">
                        @if ($l->libelleMarketing())
                            <span class="chip chip--teal"><i class="bi bi-megaphone" aria-hidden="true"></i>{{ $l->libelleMarketing() }}</span>
                        @else
                            <span class="chip chip--line">Aucune part fixée</span>
                        @endif
                        <a class="btn btn-ghost btn-sm" href="{{ route('pro.logements.edit', ['logement' => $l, 'onglet' => 'tarifs']) }}">Modifier</a>
                    </span>
                </li>
            @empty
                <li class="text-soft">Ajoutez d’abord un logement.</li>
            @endforelse
        </ul>
    </section>

    <div class="row g-3">
        <div class="col-lg-7">
            {{-- Classement --}}
            <section class="panel h-100" aria-labelledby="t-classement">
                <div class="panel__head d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <h2 id="t-classement" class="mb-0">Classement des agents</h2>
                    <div class="d-flex gap-1" role="group" aria-label="Période">
                        @foreach ($periodes as $cle => $libelle)
                            <a class="btn btn-sm {{ $periode === $cle ? 'btn-primary' : 'btn-ghost' }}" href="{{ route('pro.agents', ['periode' => $cle]) }}" @if ($periode === $cle) aria-current="page" @endif>{{ $libelle }}</a>
                        @endforeach
                    </div>
                </div>
                @forelse ($classement as $ligne)
                    @php $a = $ligne['agent']; @endphp
                    <form method="POST" action="{{ route('pro.agents.update', $a) }}" class="border-top pt-3 mt-3">
                        @csrf
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="fw-bold text-ink tnum" style="min-width:2ch" aria-label="Rang {{ $ligne['rang'] }}">{{ $ligne['rang'] }}</span>
                            <span class="avatar" aria-hidden="true">{{ $a->initiales() }}</span>
                            <div class="flex-grow-1" style="min-width:0">
                                <strong class="text-ink d-block">{{ $a->name }}</strong>
                                <span class="small text-soft">{{ $a->telephone ? Telephone::lisible($a->telephone) : $a->email }}</span>
                            </div>
                            <div class="text-end">
                                <strong class="text-ink d-block tnum">{{ Montant::fcfa($ligne['gains']) }}</strong>
                                <span class="small text-soft">{{ $ligne['sejours'] }} séjour{{ $ligne['sejours'] > 1 ? 's' : '' }} · {{ Montant::fcfa($ligne['chiffre']) }} de location</span>
                                @if ($ligne['a_venir'])<span class="small text-soft d-block">{{ $ligne['a_venir'] }} à venir</span>@endif
                            </div>
                        </div>
                        @if ($a->logementsAgent->isEmpty())
                            <p class="small text-soft mb-0">Ne fait plus la promotion de vos logements.</p>
                        @else
                            <div class="row g-1">
                                @foreach ($logements as $l)
                                    <div class="col-sm-6"><div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="logements[]" value="{{ $l->id }}" id="a{{ $a->id }}-l{{ $l->id }}" @checked($a->logementsAgent->contains('id', $l->id))>
                                        <label class="form-check-label" for="a{{ $a->id }}-l{{ $l->id }}">{{ $l->titre }}</label>
                                    </div></div>
                                @endforeach
                            </div>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <button class="btn btn-primary btn-sm" type="submit">Enregistrer</button>
                                <button class="btn btn-ghost btn-sm" type="submit" data-confirmer-decocher
                                        data-confirmer="{{ $a->name }} ne fera plus la promotion de vos logements. Les commissions déjà gagnées lui restent." data-confirmer-titre="Retirer cet agent ?" data-confirmer-bouton="Retirer" data-confirmer-style="danger">Retirer</button>
                            </div>
                        @endif
                    </form>
                @empty
                    <p class="text-soft mb-0">Aucun agent pour le moment. Ajoutez-en un : il recevra le lien de chaque logement à partager.</p>
                @endforelse
            </section>
        </div>

        <div class="col-lg-5">
            <section class="panel h-100" aria-labelledby="t-ajout">
                <h2 id="t-ajout">Ajouter un agent</h2>
                <form method="POST" action="{{ route('pro.agents.store') }}" class="d-grid gap-3" novalidate
                      data-confirmer="{nom} recevra le lien d’affiliation des logements cochés et touchera leur part marketing sur chaque séjour réservé par son lien." data-confirmer-titre="Ajouter cet agent ?" data-confirmer-bouton="Ajouter">
                    @csrf
                    <div>
                        <label class="form-label" for="a-nom">Nom et prénom</label>
                        <input class="form-control @error('nom') is-invalid @enderror" type="text" id="a-nom" name="nom" value="{{ old('nom') }}" required>
                        @error('nom')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="form-label" for="a-identifiant">Téléphone ou email</label>
                        <input class="form-control @error('identifiant') is-invalid @enderror" type="text" id="a-identifiant" name="identifiant" value="{{ old('identifiant') }}" placeholder="01 97 00 00 00" required>
                        @error('identifiant')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <p class="form-text mb-0">Un client VIVI peut être agent : il garde son compte et ses réservations.</p>
                    </div>
                    <fieldset>
                        <legend class="form-label">Logements à promouvoir</legend>
                        @foreach ($logements as $l)
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="logements[]" value="{{ $l->id }}" id="n-l{{ $l->id }}" @checked(in_array($l->id, array_map('intval', old('logements', [])), true))>
                                <label class="form-check-label" for="n-l{{ $l->id }}">{{ $l->titre }}</label></div>
                        @endforeach
                        @error('logements')<p class="small mb-0" style="color:var(--vivi-danger)">{{ $message }}</p>@enderror
                    </fieldset>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-person-plus" aria-hidden="true"></i>Ajouter l’agent</button>
                </form>
            </section>
        </div>
    </div>
</x-layouts.pro>
