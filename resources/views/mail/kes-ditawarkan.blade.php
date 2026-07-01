<!DOCTYPE html>
<html lang="ms">
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; color:#1a1a1a; line-height:1.6;">
    <p>Salam {{ $peguam->nama_peguam }},</p>

    <p>Satu kes Bantuan Guaman telah <strong>ditawarkan</strong> kepada tuan/puan untuk pertimbangan dan penerimaan.</p>

    <table style="border-collapse:collapse; margin:12px 0;">
        <tr><td style="padding:4px 12px 4px 0; color:#555;">No. Fail</td><td style="padding:4px 0;"><strong>{{ $kes->no_fail ?: '#'.$kes->id }}</strong></td></tr>
        <tr><td style="padding:4px 12px 4px 0; color:#555;">Pemohon (OYD)</td><td style="padding:4px 0;">{{ $kes->nama ?: '-' }}</td></tr>
        <tr><td style="padding:4px 12px 4px 0; color:#555;">Kategori / Jenis</td><td style="padding:4px 0;">{{ $kes->kategori_kes ?: '-' }} · {{ $kes->jenis_kes ?: '-' }}</td></tr>
        <tr><td style="padding:4px 12px 4px 0; color:#555;">Cawangan</td><td style="padding:4px 0;">{{ $kes->cawangan ?: '-' }}</td></tr>
    </table>

    <p>Sila log masuk ke Sistem Integrated Bantuan Guaman dan ke ruang <strong>Tawaran</strong> untuk <strong>menerima</strong> atau <strong>menolak</strong> tawaran ini dalam tempoh <strong>7 hari</strong>.</p>

    <p style="color:#0d2e48;"><strong>"BERKHIDMAT UNTUK NEGARA"</strong><br>Jabatan Bantuan Guaman</p>
</body>
</html>
