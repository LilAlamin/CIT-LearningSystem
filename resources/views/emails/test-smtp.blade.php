<x-mail::message>
# Halo! 👋

Ini adalah email uji coba otomatis dari **CIT Learning System**.

<x-mail::panel>
**Status Koneksi: BERHASIL TERHUBUNG (OK)**  
Server SMTP Brevo Anda di server kampus telah aktif dan siap digunakan untuk kebutuhan sistem.
</x-mail::panel>

### Rincian Pengiriman:
* **Host SMTP**: `{{ $smtpHost }}:{{ $smtpPort }}`
* **Operator Penguji**: {{ $operatorName }}
* **Waktu Pengiriman**: {{ $timeString }}
* **Nama Pengirim**: {{ $senderName }}

Sistem email ini kini siap digunakan untuk pengiriman:
1. Notifikasi pendaftaran & verifikasi akun
2. Pengumuman massal mahasiswa
3. Penerbitan & pengiriman sertifikat digital

<x-mail::button :url="$url">
Kunjungi Dashboard CIT
</x-mail::button>

Salam hangat,  
**{{ $senderName }}**
</x-mail::message>
