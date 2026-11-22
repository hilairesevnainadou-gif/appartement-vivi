@props(['url'])
{{-- En-tête des emails : bande aux couleurs du logo, puis le logo VIVI (PNG : les messageries bloquent le SVG) --}}
<tr>
<td class="bande">&nbsp;</td>
</tr>
<tr>
<td class="header">
<a href="{{ $url }}">
<img src="{{ asset('images/logo/vivi-logo-1200.png') }}" class="logo" alt="VIVI · Appartements et salles" height="38" style="height:38px;width:auto">
</a>
</td>
</tr>
