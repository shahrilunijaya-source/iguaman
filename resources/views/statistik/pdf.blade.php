<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1a1a1a; font-size: 11px; margin: 0; }
        h1 { font-size: 18px; margin: 0 0 2px; color: #0d2e48; }
        .meta { color: #666; font-size: 10px; margin-bottom: 16px; }
        .kpis { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .kpis td { width: 25%; border: 1px solid #ddd; padding: 8px 10px; }
        .kpis .lbl { font-size: 9px; text-transform: uppercase; color: #888; letter-spacing: .06em; }
        .kpis .val { font-size: 18px; font-weight: bold; color: #0d2e48; }
        h2 { font-size: 12px; color: #0d2e48; border-bottom: 2px solid #1a6fa8; padding-bottom: 4px; margin: 16px 0 8px; }
        table.brk { width: 100%; border-collapse: collapse; }
        table.brk td { padding: 4px 8px; border-bottom: 1px solid #eee; font-size: 11px; vertical-align: middle; }
        table.brk td.lab { width: 30%; }
        table.brk td.barcell { width: 55%; }
        table.brk td.n { text-align: right; font-weight: bold; width: 15%; }
        .barbg { background: #eef4f3; height: 8px; border-radius: 4px; }
        .barfg { background: #1a6fa8; height: 8px; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>Sistem Integrated Bantuan Guaman — Laporan Statistik</h1>
    <div class="meta">Dijana {{ $dijana }} oleh {{ $oleh }}</div>

    <table class="kpis">
        <tr>
            <td><div class="lbl">Jumlah Kes</div><div class="val">{{ number_format($kpi['jumlah']) }}</div></td>
            <td><div class="lbl">Aktif</div><div class="val">{{ number_format($kpi['aktif']) }}</div></td>
            <td><div class="lbl">Ditutup</div><div class="val">{{ number_format($kpi['tutup']) }}</div></td>
            <td><div class="lbl">Pengantaraan</div><div class="val">{{ number_format($kpi['pengantaraan']) }}</div></td>
        </tr>
        <tr>
            <td><div class="lbl">Diagih</div><div class="val">{{ number_format($kpi['diagih']) }}</div></td>
            <td><div class="lbl">Belum Diagih</div><div class="val">{{ number_format($kpi['belum_agih']) }}</div></td>
            <td><div class="lbl">Rekod OYD</div><div class="val">{{ number_format($kpi['oyd']) }}</div></td>
            <td><div class="lbl">Peguam Panel</div><div class="val">{{ number_format($kpi['peguam']) }}</div></td>
        </tr>
    </table>

    @php
        $sections = [
            'Mengikut Cawangan' => $byCawangan,
            'Mengikut Kategori' => $byKategori,
            'Mengikut Jenis Kes' => $byJenis,
            'Mengikut Status' => $byStatus,
            'Mengikut Keputusan' => $byKeputusan,
            'Cara Selesai (Pengantaraan)' => $byCaraSelesai,
            'Mengikut Bulan' => $byBulan,
        ];
    @endphp

    @foreach ($sections as $title => $data)
        <h2>{{ $title }}</h2>
        <table class="brk">
            @php $max = count($data) ? max($data) : 0; @endphp
            @forelse ($data as $label => $value)
                <tr>
                    <td class="lab">{{ $label }}</td>
                    <td class="barcell">
                        <div class="barbg"><div class="barfg" style="width: {{ $max > 0 ? max(2, round($value / $max * 100)) : 0 }}%;"></div></div>
                    </td>
                    <td class="n">{{ number_format($value) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" style="color:#999">Tiada data.</td></tr>
            @endforelse
        </table>
    @endforeach
</body>
</html>
