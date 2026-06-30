<!DOCTYPE html>
<html lang="ms">
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; color:#1a1a1a; line-height:1.6;">
    <p>Salam,</p>

    <p>Satu rekod telah <strong>dipindahkan</strong> ke cawangan tuan/puan dan sedang
       menunggu <strong>pengesahan penerimaan</strong>.</p>

    <table style="border-collapse:collapse; margin:12px 0;">
        <tr><td style="padding:4px 12px 4px 0; color:#555;">Jenis Rekod</td><td style="padding:4px 0;"><strong>{{ $pindah->jenis_rekod === \App\Models\PemindahanCawangan::JENIS_KES ? 'Kes' : 'Khidmat Nasihat' }}</strong></td></tr>
        <tr><td style="padding:4px 12px 4px 0; color:#555;">No. Rekod</td><td style="padding:4px 0;">#{{ $pindah->id_rekod }}</td></tr>
        <tr><td style="padding:4px 12px 4px 0; color:#555;">Dari Cawangan</td><td style="padding:4px 0;">{{ $pindah->cawangan_asal ?: '—' }}</td></tr>
        <tr><td style="padding:4px 12px 4px 0; color:#555;">Ke Cawangan</td><td style="padding:4px 0;"><strong>{{ $pindah->cawangan_tujuan ?: '—' }}</strong></td></tr>
        <tr><td style="padding:4px 12px 4px 0; color:#555;">Sebab</td><td style="padding:4px 0;">{{ $pindah->sebab ?: '—' }}</td></tr>
    </table>

    <p>Sila log masuk ke sistem iGuaman 2in1 untuk menerima atau menolak pemindahan ini.</p>

    <p style="color:#003D3A;"><strong>"BERKHIDMAT UNTUK NEGARA"</strong><br>Jabatan Bantuan Guaman</p>
</body>
</html>
