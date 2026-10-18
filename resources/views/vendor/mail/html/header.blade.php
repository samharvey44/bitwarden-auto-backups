@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
<img src="{{ asset('icon-192.png') }}" width="40" height="40" alt="" style="border: 0; border-radius: 10px; vertical-align: middle;">
<span style="margin-left: 8px; vertical-align: middle;">{!! $slot !!}</span>
</a>
</td>
</tr>
