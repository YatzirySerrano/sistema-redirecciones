@props(['url'])
{{-- Identidad textual de DASTI: no se usa ningún logotipo (no hay uno oficial). --}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
<span class="marca">DASTI</span><br>
<span class="marca-sub">{{ trim($slot) !== '' && trim($slot) !== 'Laravel' ? $slot : 'Redirecciones QR · NFC' }}</span>
</a>
</td>
</tr>
