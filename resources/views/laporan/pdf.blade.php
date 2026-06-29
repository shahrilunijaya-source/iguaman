<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 70px 28px 48px; }
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { color: #1a1a1a; font-size: 9.5px; margin: 0; }
        header { position: fixed; top: -52px; left: 0; right: 0; height: 44px; border-bottom: 2px solid #003D3A; }
        .brand { font-size: 14px; font-weight: bold; color: #003D3A; }
        .brand .teal { color: #00B8A9; }
        .sub { font-size: 8px; color: #666; text-transform: uppercase; letter-spacing: 1px; }
        footer { position: fixed; bottom: -34px; left: 0; right: 0; height: 24px; border-top: 1px solid #ccc; padding-top: 5px; font-size: 7.5px; color: #777; }
        .pg:after { content: "Halaman " counter(page); }
        h1 { font-size: 13px; color: #003D3A; margin: 0 0 2px; }
        .meta { color: #666; font-size: 8.5px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #003D3A; color: #fff; text-align: left; padding: 5px 6px; font-size: 8px; text-transform: uppercase; letter-spacing: .3px; }
        td { padding: 4px 6px; border-bottom: 1px solid #e6ecea; font-size: 9px; }
        tr:nth-child(even) td { background: #f5f8f7; }
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
        <h1>{{ $report['label'] }}</h1>
        <div class="meta">{{ number_format($rows->count()) }} rekod</div>

        <table>
            <thead>
                <tr>@foreach ($report['columns'] as $label)<th>{{ $label }}</th>@endforeach</tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        @foreach (array_keys($report['columns']) as $field)
                            @php $v = $row->$field; @endphp
                            <td>{{ $v instanceof \Illuminate\Support\Carbon ? $v->format('d/m/Y') : (($v === null || $v === '') ? '' : $v) }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($report['columns']) }}" style="text-align:center; color:#999;">Tiada rekod.</td></tr>
                @endforelse
            </tbody>
        </table>
    </main>
</body>
</html>
