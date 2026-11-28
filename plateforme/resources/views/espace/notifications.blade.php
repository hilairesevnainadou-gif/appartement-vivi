{{-- Espace « Notifications » : tout ce qui a été envoyé par email ou SMS reste consultable ici --}}
<x-layouts.espace titre="Notifications" page="notifications">
    <div class="container py-3 py-lg-4" style="max-width:820px">
        <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
            <div>
                <h1 class="page-title mb-1">Notifications</h1>
                <p class="text-soft mb-0">
                    {{ $nonLues ? $nonLues.' non lue'.($nonLues > 1 ? 's' : '').'. ' : '' }}Confirmations, rappels, remboursements, bonus : tout ce que VIVI vous envoie, au même endroit.
                </p>
            </div>
            @if ($nonLues)
                <form method="POST" action="{{ route('notifications.tout-lu') }}">
                    @csrf
                    <button class="btn btn-ghost btn-sm" type="submit"><i class="bi bi-check2-all" aria-hidden="true"></i>Tout marquer comme lu</button>
                </form>
            @endif
        </div>

        @if ($notifications->isEmpty())
            <div class="empty-state" style="background:#fff">
                <i class="bi bi-bell" aria-hidden="true"></i>
                <h2 class="h4 mt-2">Aucune notification</h2>
                <p class="text-soft mb-0">Vos confirmations de réservation, rappels et messages de VIVI apparaîtront ici.</p>
            </div>
        @else
            <ul class="liste-notifications">
                @foreach ($notifications as $n)
                    <li @class(['is-nonlue' => ! $n->read_at])>
                        <span class="liste-notifications__icone"><i class="bi {{ $n->data['icone'] ?? 'bi-bell' }}" aria-hidden="true"></i></span>
                        <div class="liste-notifications__corps">
                            <div class="d-flex flex-wrap justify-content-between gap-2">
                                <strong>{{ $n->data['titre'] ?? 'Notification' }}</strong>
                                <time class="small text-soft" datetime="{{ $n->created_at->toIso8601String() }}" title="{{ $n->created_at->translatedFormat('j F Y à H:i') }}">{{ $n->created_at->diffForHumans() }}</time>
                            </div>
                            <p class="mb-2">{{ $n->data['texte'] ?? '' }}</p>
                            @if (! empty($n->data['lien']))
                                <a class="btn btn-ghost btn-sm" href="{{ route('notifications.ouvrir', $n->id) }}">{{ $n->data['action'] ?? 'Voir' }}</a>
                            @endif
                        </div>
                        @unless ($n->read_at)<span class="liste-notifications__point" aria-label="Non lue"></span>@endunless
                    </li>
                @endforeach
            </ul>
            <div class="mt-3">{{ $notifications->links() }}</div>
        @endif
    </div>
</x-layouts.espace>
