@props([
    'url',
    'logo' => null,
])
@php
    $logoSrc = $logo ?? (isset($message) && file_exists(public_path('logo.png'))
        ? $message->embed(public_path('logo.png'))
        : asset('logo.png'));
@endphp
<tr>
<td class="header" align="center" style="padding: 36px 0 20px 0; text-align: center;">
<table cellpadding="0" cellspacing="0" border="0" align="center" style="margin: 0 auto; text-align: center;">
    @if ($logoSrc)
    <tr>
        <td align="center" style="padding-bottom: 12px;">
            <a href="{{ $url }}" style="text-decoration: none; display: inline-block;">
                <img src="{{ $logoSrc }}" width="64" height="64" alt="{{ config('app.name') }}" style="width: 64px; height: 64px; max-width: 64px; max-height: 64px; border-radius: 50%; display: block; margin: 0 auto; object-fit: cover;" />
            </a>
        </td>
    </tr>
    @endif
    <tr>
        <td align="center">
            <a href="{{ $url }}" style="color: #09090b; font-size: 20px; font-weight: 700; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; text-decoration: none; letter-spacing: -0.02em; display: inline-block;">
                {{ config('app.name', 'Learning System') }}
            </a>
        </td>
    </tr>
</table>
</td>
</tr>
