{{-- « Supprimer mon compte » : ce qui empêche la suppression, ce qui est perdu, puis code de confirmation --}}
<x-layouts.espace titre="Supprimer mon compte" page="profil">
    <div class="container py-3 py-lg-4" style="max-width:720px">
        <p class="mb-2"><a href="{{ route('profil') }}"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Mon profil</a></p>
        <h1 class="page-title mb-3">Supprimer mon compte</h1>

        @if (session('code_dev'))
            <div class="note-box note-box--orange mb-3" role="note"><i class="bi bi-tools" aria-hidden="true"></i>
                <span>Développement : le code est <strong class="tnum">{{ session('code_dev') }}</strong>. Ce cadre n’apparaît jamais en production.</span></div>
        @endif
        @error('compte')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror

        @if ($obstacles)
            <section class="panel m-0" aria-labelledby="t-obstacles">
                <h2 id="t-obstacles" class="profil-titre">Pas encore possible</h2>
                <ul class="aide-liste">
                    @foreach ($obstacles as $o)<li>{{ $o['message'] }}</li>@endforeach
                </ul>
                @if ($whatsapp)<p class="form-text mt-3 mb-0">Besoin d’aide ? <a href="{{ $whatsapp }}" target="_blank" rel="noopener">Écrivez-nous sur WhatsApp</a>.</p>@endif
            </section>
        @else
            <section class="panel m-0" aria-labelledby="t-pertes">
                <h2 id="t-pertes" class="profil-titre">La suppression est définitive</h2>
                <ul class="aide-liste">
                    @foreach ($pertes as $p)<li>{{ $p }}</li>@endforeach
                </ul>
                <p class="form-text mt-3 mb-0">Nous gardons les réservations et les paiements sans votre nom, pour la comptabilité. Vos avis restent, sans votre nom. <a href="{{ route('aide.suppression') }}">En savoir plus</a></p>
            </section>

            <section class="panel mb-0 mt-3" aria-labelledby="t-confirmer">
                <h2 id="t-confirmer" class="profil-titre">Confirmer</h2>
                @if ($enAttente)
                    <form method="POST" action="{{ route('profil.suppression.confirmer') }}" novalidate>
                        @csrf
                        <label class="form-label" for="sc-code">Code envoyé {{ $enAttente['canal'] === 'sms' ? 'par SMS au' : 'à' }} {{ $enAttente['lisible'] }}</label>
                        <input class="form-control otp-input @error('code') is-invalid @enderror" style="max-width:200px" id="sc-code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required autofocus>
                        @error('code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <div class="form-check my-3">
                            <input class="form-check-input @error('accord') is-invalid @enderror" type="checkbox" id="sc-accord" name="accord" value="1" required>
                            <label class="form-check-label" for="sc-accord">Je veux supprimer définitivement mon compte.</label>
                            @error('accord')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button class="btn btn-danger" type="submit" data-chargement="Suppression…">Supprimer mon compte</button>
                    </form>
                    <div class="d-flex flex-wrap gap-3 mt-3">
                        <form method="POST" action="{{ route('profil.suppression.code') }}">@csrf<button class="btn btn-link btn-sm p-0" type="submit">Renvoyer un code</button></form>
                        <form method="POST" action="{{ route('profil.suppression.annuler') }}">@csrf<button class="btn btn-link btn-sm p-0" type="submit">Garder mon compte</button></form>
                    </div>
                @else
                    <form method="POST" action="{{ route('profil.suppression.code') }}">
                        @csrf
                        <p>Un code sera envoyé {{ $destination['canal'] === 'sms' ? 'par SMS au' : 'à' }} <strong>{{ $destination['lisible'] }}</strong>.</p>
                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-outline-danger" type="submit" data-chargement="Envoi du code…">Recevoir un code</button>
                            <a class="btn btn-ghost" href="{{ route('profil') }}">Garder mon compte</a>
                        </div>
                    </form>
                @endif
            </section>
        @endif
    </div>
</x-layouts.espace>
