@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="https://webmonitor.pa2lo.net/apple-touch-icon.png" class="logo" alt="Webmonitor Logo">
<span class="logo-text">{!! $slot !!}</span>
</a>
</td>
</tr>
