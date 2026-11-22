<x-layouts.public page="connexion" titre="Code de connexion">
    <div class="auth-wrap">
        <div class="container">
            <div class="auth-split">
            <div class="auth-card">
                @if (session('code_dev'))
                    <div class="note-box note-box--orange mb-3" role="note">
                        <i class="bi bi-tools" aria-hidden="true"></i>
                        <span>Développement : le code est <strong class="tnum">{{ session('code_dev') }}</strong>. Ce cadre n’apparaît jamais en production.</span>
                    </div>
                @endif

                <form method="POST" action="{{ route('connexion.verifier') }}" novalidate>
                    @csrf
                    <h1 class="h3">Entrez le code reçu</h1>
                    <p class="text-soft">
                        Code à 6 chiffres envoyé {{ $canal === 'sms' ? 'par SMS' : 'par email' }} {{ $destination }}.
                        <a href="{{ route('connexion') }}">Modifier</a>
                    </p>

                    @if (session('statut'))
                        <p class="form-text mb-3" role="status"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>{{ session('statut') }}</p>
                    @endif

                    <div class="field">
                        <label class="form-label" for="code">Code de vérification</label>
                        <input class="form-control otp-input @error('code') is-invalid @enderror" type="text" id="code" name="code"
                               inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" autofocus required
                               @error('code') aria-describedby="code-erreur" @enderror>
                        @error('code')<div class="invalid-feedback" id="code-erreur">{{ $message }}</div>@enderror
                    </div>

                    <button class="btn btn-cta btn-lg w-100" type="submit" data-chargement="Vérification…">Valider</button>
                </form>

                <form method="POST" action="{{ route('connexion.renvoyer') }}" class="text-center small mt-3">
                    @csrf
                    {{ $canal === 'sms' ? 'Pas de SMS ?' : 'Pas d’email ? Regardez aussi dans les indésirables.' }}
                    <button class="btn btn-link btn-sm p-0 align-baseline" type="submit" id="renvoyer" style="min-height:0">Renvoyer le code</button>
                    <span class="text-soft" id="delai" aria-live="polite"></span>
                </form>
            </div>
            @include('partials.auth-visuel')
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                var champ = document.getElementById('code');
                // Garde seulement les chiffres (collage d'un SMS compris)
                champ.addEventListener('input', function () { champ.value = champ.value.replace(/\D/g, '').slice(0, 6); });
                // Bouton « Renvoyer » disponible après le délai minimal entre deux envois
                var bouton = document.getElementById('renvoyer'), texte = document.getElementById('delai'), reste = {{ $renvoiSecondes }};
                bouton.disabled = true;
                var minuterie = setInterval(function () {
                    reste--;
                    if (reste <= 0) { clearInterval(minuterie); bouton.disabled = false; texte.textContent = ''; }
                    else { texte.textContent = 'dans ' + reste + ' s'; }
                }, 1000);
                texte.textContent = 'dans ' + reste + ' s';
            })();
        </script>
    @endpush
</x-layouts.public>
