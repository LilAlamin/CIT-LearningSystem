@props([
    'message' => null,
])
@php
    $logoSrc = isset($message) && file_exists(public_path('logo.png'))
        ? $message->embed(public_path('logo.png'))
        : (file_exists(public_path('logo.png')) ? asset('logo.png') : null);
@endphp

<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')" :logo="$logoSrc">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} CIT Learning Systems. {{ __('All rights reserved.') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
