{{-- Entrée du menu propriétaire : lien réel, ou message « bientôt » pour un écran des lots suivants --}}
@if (isset($m['route']))
    <li><a href="{{ route($m['route']) }}" @if($m['id'] === $page) aria-current="page" @endif><i class="bi {{ $m['icone'] }}" aria-hidden="true"></i>{{ $m['label'] }}</a></li>
@else
    <li><a href="#" data-bientot="{{ $m['bientot'] }}"><i class="bi {{ $m['icone'] }}" aria-hidden="true"></i>{{ $m['label'] }}@if($m['pastille'] ?? true)<span class="soon">bientôt</span>@endif</a></li>
@endif
