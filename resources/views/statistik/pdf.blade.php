<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1a1a1a; font-size: 11px; margin: 0; }
        h1 { font-size: 18px; margin: 0 0 2px; color: #003D3A; }
        .meta { color: #666; font-size: 10px; margin-bottom: 16px; }
        .kpis { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .kpis td { width: 25%; border: 1px solid #ddd; padding: 8px 10px; }
        .kpis .lbl { font-size: 9px; text-transform: uppercase; color: #888; letter-spacing: .08em; }
        .kpis .val { font-size: 20px; font-weight: bold; color: #003D3A; }
        h2 { font-size: 12px; color: #003D3A; border-bottom: 2px solid #00B8A9; padding-bottom: 4px; margin: 18px 0 8px; }
        table.brk { width: 100%; border-collapse: collapse; }
        table.brk td { padding: 5px 8px; border-bottom: 1px solid #eee; font-size: 11px; }
        table.brk td.n { text-align: right; font-weight: bold; width: 80px; }
    </style>
</head>
<body>
    <h1>iGuaman 2in1 — Laporan Statistik</h1>
    <div class="meta">Dijana {{ $dijana }} oleh {{ $oleh }}</div>

    <table class="kpis">
        <tr>
            <td><div class="lbl">Jumlah Kes</div><div class="val">{{ number_format($kpi['jumlah']) }}</div></td>
            <td><div class="lbl">Aktif</div><div class="val">{{ number_format($kpi['aktif']) }}</div></td>
            <td><div class="lbl">Ditutup</div><div class="val">{{ number_format($kpi['tutup']) }}</div></td>
            <td><div class="lbl">Pengantaraan</div><div class="val">{{ number_format($kpi['pengantaraan']) }}</div></td>
        </tr>
    </table>

    @foreach (['Mengikut Cawangan' => $byCawangan, 'Mengikut Kategori' => $byKategori, 'Mengikut Status' => $byStatus, 'Mengikut Bulan' => $byBulan] as $title => $data)
        <h2>{{ $title }}</h2>
        <table class="brk">
            @forelse ($data as $label => $n)
                <tr><td>{{ $label }}</td><td class="n">{{ number_format($n) }}</td></tr>
            @empty
                <tr><td colspan="2" style="color:#999">Tiada data.</td></tr>
            @endforelse
        </table>
    @endforeach
</body>
</html>
