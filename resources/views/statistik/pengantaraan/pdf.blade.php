<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 70px 22px 48px; }
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { color: #1a1a1a; font-size: 9px; margin: 0; }
        header { position: fixed; top: -52px; left: 0; right: 0; height: 44px; border-bottom: 2px solid #003D3A; }
        .brand { font-size: 14px; font-weight: bold; color: #003D3A; }
        .brand .teal { color: #00B8A9; }
        .sub { font-size: 8px; color: #666; text-transform: uppercase; letter-spacing: 1px; }
        footer { position: fixed; bottom: -34px; left: 0; right: 0; height: 24px; border-top: 1px solid #ccc; padding-top: 5px; font-size: 7.5px; color: #777; }
        .pg:after { content: "Halaman " counter(page); }
        h1 { font-size: 12px; color: #003D3A; margin: 0 0 2px; }
        .meta { color: #666; font-size: 8.5px; margin-bottom: 10px; }
    </style>
</head>
<body>
    <header>
        <div class="brand"><span class="teal">i</span>Guaman 2in1</div>
        <div class="sub">Jabatan Bantuan Guaman</div>
    </header>
    <footer>
        <span style="float:left;">Dijana oleh {{ $oleh }} · {{ $dijana }}</span>
        <span style="float:right;" class="pg"></span>
    </footer>

    <main>
        @if ($jenis === 'bulanan')
            <h1>STATISTIK PENUGASAN BULANAN PENGANTARAAN</h1>
            <div class="meta">{{ $year ?: 'Semua tahun' }}{{ $kategori ? ' · '.$kategori : '' }} · Keseluruhan {{ number_format($data['grand']) }} penugasan</div>
            @include('statistik.pengantaraan._bulanan_table', ['data' => $data, 'branches' => $branches, 'bulan' => $bulan])
        @else
            <h1>STATISTIK PENUGASAN PENGANTARAAN</h1>
            <div class="meta">{{ $year ?: 'Semua tahun' }} · Keseluruhan {{ number_format($data['total']['jumlah']) }} penugasan</div>
            @include('statistik.pengantaraan._kategori_table', ['data' => $data, 'branches' => $branches])
        @endif
    </main>
</body>
</html>
