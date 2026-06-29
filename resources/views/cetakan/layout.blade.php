<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <title>@yield('tajuk', 'Cetakan') · iGuaman 2in1</title>
    <style>
        @page { margin: 120px 42px 70px; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1a1a1a; line-height: 1.45; }

        header { position: fixed; top: -92px; left: 0; right: 0; height: 84px; }
        .kepala { width: 100%; border-collapse: collapse; border-bottom: 2px solid #003D3A; }
        .kepala td { vertical-align: middle; padding-bottom: 8px; }
        .brand { font-size: 18px; font-weight: bold; color: #003D3A; letter-spacing: .3px; }
        .brand .teal { color: #00B8A9; }
        .kepala .org { font-size: 9px; color: #555; text-transform: uppercase; letter-spacing: 1.2px; }
        .kepala .right { text-align: right; font-size: 8.5px; color: #777; }

        footer { position: fixed; bottom: -46px; left: 0; right: 0; height: 36px;
                 border-top: 1px solid #ccc; padding-top: 6px; font-size: 8px; color: #777; }
        footer .l { float: left; } footer .r { float: right; }
        .pg:after { content: "Halaman " counter(page); }

        .doc-title { text-align: center; font-size: 13px; font-weight: bold; text-transform: uppercase;
                     letter-spacing: 1.2px; color: #003D3A; margin: 0 0 4px; }
        .doc-sub { text-align: center; font-size: 9.5px; color: #666; margin: 0 0 16px; }

        .sec { margin: 16px 0 6px; font-weight: bold; color: #003D3A; font-size: 10.5px;
               text-transform: uppercase; letter-spacing: .6px; border-bottom: 1px solid #003D3A; padding-bottom: 3px; }

        table.kv { width: 100%; border-collapse: collapse; }
        table.kv td { padding: 4px 6px; vertical-align: top; border-bottom: 1px solid #eee; }
        table.kv td.k { width: 36%; color: #555; }
        table.kv td.v { font-weight: 600; }

        table.grid { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.grid th, table.grid td { border: 1px solid #cfd8d8; padding: 5px 6px; text-align: left; vertical-align: top; }
        table.grid th { background: #eef4f3; color: #003D3A; font-size: 9.5px; text-transform: uppercase; letter-spacing: .4px; }

        p.body { margin: 6px 0; text-align: justify; }
        .sign { margin-top: 46px; width: 100%; }
        .sign td { width: 50%; vertical-align: top; font-size: 10px; }
        .sign .line { border-top: 1px solid #333; width: 200px; margin-top: 40px; padding-top: 4px; }
        .muted { color: #999; }
    </style>
</head>
<body>
    <header>
        <table class="kepala">
            <tr>
                <td>
                    <div class="brand"><span class="teal">i</span>Guaman <span style="font-weight:400; color:#666; font-size:12px;">2in1</span></div>
                    <div class="org">Jabatan Bantuan Guaman · Bantuan Guaman</div>
                </td>
                <td class="right">
                    @yield('kepala_kanan')
                </td>
            </tr>
        </table>
    </header>

    <footer>
        <span class="l">Dijana oleh {{ $oleh ?? '—' }} · {{ $dijana ?? '' }}</span>
        <span class="r pg"></span>
    </footer>

    <main>
        <div class="doc-title">@yield('tajuk')</div>
        @hasSection('subtajuk')<div class="doc-sub">@yield('subtajuk')</div>@endif
        @yield('isi')
    </main>
</body>
</html>
