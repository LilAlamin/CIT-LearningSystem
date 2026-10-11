<x-mail::message>
# Halo, {{ $recipient->name }}! 👋

Ada pengumuman terbaru untuk Anda di **CIT Learning System**:

<x-mail::panel>
**{{ $announcement->title }}**  
*Target: {{ $pathTitle }} | Prioritas: {{ strtoupper($announcement->priority) }}*

{!! nl2br(e($announcement->content)) !!}
</x-mail::panel>

Silakan kunjungi dashboard untuk melihat jadwal, materi pembelajaran, dan status path learning Anda.

<x-mail::button :url="$url">
Buka Dashboard CIT
</x-mail::button>

Semangat belajar dan terus kembangkan kemampuan Anda!

Salam hangat,  
**Tim CIT Learning System**
</x-mail::message>
