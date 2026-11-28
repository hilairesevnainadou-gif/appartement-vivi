{{-- Cloche des notifications (en-tête du site et barre haute de l'espace propriétaire) : compteur et 5 dernières --}}
@php
    $u = auth()->user();
    $nonLues = $u->notificationsSite()->whereNull('read_at')->count();
    $dernieres = $u->notificationsSite()->latest()->limit(5)->get();
@endphp
<div class="dropdown cloche">
    <button class="cloche__bouton" type="button" data-bs-toggle="dropdown" data-bs-offset="0,10" aria-expanded="false"
            aria-label="Notifications{{ $nonLues ? ' : '.$nonLues.' non lue'.($nonLues > 1 ? 's' : '') : '' }}">
        <i class="bi bi-bell" aria-hidden="true"></i>
        @if ($nonLues)<span class="cloche__compteur" aria-hidden="true">{{ $nonLues > 9 ? '9+' : $nonLues }}</span>@endif
    </button>
    <div class="dropdown-menu dropdown-menu-end cloche__menu">
        <div class="cloche__tete">
            <strong>Notifications</strong>
            @if ($nonLues)
                <form method="POST" action="{{ route('notifications.tout-lu') }}" data-sans-chargement>
                    @csrf
                    <button class="btn btn-link btn-sm p-0" type="submit">Tout marquer comme lu</button>
                </form>
            @endif
        </div>
        @forelse ($dernieres as $n)
            <a @class(['cloche__item', 'is-nonlue' => ! $n->read_at]) href="{{ route('notifications.ouvrir', $n->id) }}">
                <span class="cloche__icone"><i class="bi {{ $n->data['icone'] ?? 'bi-bell' }}" aria-hidden="true"></i></span>
                <span class="cloche__texte">
                    <strong>{{ $n->data['titre'] ?? 'Notification' }}</strong>
                    <small>{{ \Illuminate\Support\Str::limit($n->data['texte'] ?? '', 90) }}</small>
                    <time datetime="{{ $n->created_at->toIso8601String() }}">{{ $n->created_at->diffForHumans() }}</time>
                </span>
            </a>
        @empty
            <p class="cloche__vide">Aucune notification pour le moment.</p>
        @endforelse
        <a class="cloche__tout" href="{{ route('notifications') }}">Toutes les notifications</a>
    </div>
</div>
