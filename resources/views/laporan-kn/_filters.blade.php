{{--
  Shared GET filter form for the KN reports. Inputs:
    $routeName  — current report route name (form action + reset target)
    $show       — list of filter keys to render: cawangan|bulan|tahun|kategori|subkategori
    $filters    — current filter values (assoc)
    $canChooseBranch, $cawanganList, $kategoriList, $subkategoriList — option data
  Hidden on print via @media print (class js-no-print).
--}}
@php
    $bulanNama = [1 => 'Januari', 2 => 'Februari', 3 => 'Mac', 4 => 'April', 5 => 'Mei', 6 => 'Jun', 7 => 'Julai', 8 => 'Ogos', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Disember'];
    $tahunSekarang = (int) now()->year;
@endphp

<form method="GET" action="{{ route($routeName) }}" class="tap-filters js-no-print">
    @if (in_array('cawangan', $show, true) && $canChooseBranch)
        <select name="cawangan" class="tap-chip" onchange="this.form.submit()" aria-label="Cawangan">
            <option value="">Semua Cawangan</option>
            @foreach ($cawanganList as $nama)
                <option value="{{ $nama }}" @selected(($filters['cawangan'] ?? '') === $nama)>{{ $nama }}</option>
            @endforeach
        </select>
    @endif

    @if (in_array('kategori', $show, true))
        <select name="id_kategori" class="tap-chip" onchange="this.form.submit()" aria-label="Kategori">
            <option value="">Semua Kategori</option>
            @foreach ($kategoriList as $id => $nama)
                <option value="{{ $id }}" @selected((string) ($filters['id_kategori'] ?? '') === (string) $id)>{{ $nama }}</option>
            @endforeach
        </select>
    @endif

    @if (in_array('subkategori', $show, true))
        <select name="id_subkategori" class="tap-chip" onchange="this.form.submit()" aria-label="Sub Kategori">
            <option value="">Semua Sub Kategori</option>
            @foreach ($subkategoriList as $id => $nama)
                <option value="{{ $id }}" @selected((string) ($filters['id_subkategori'] ?? '') === (string) $id)>{{ $nama }}</option>
            @endforeach
        </select>
    @endif

    @if (in_array('bulan', $show, true))
        <select name="bulan" class="tap-chip" onchange="this.form.submit()" aria-label="Bulan">
            <option value="">Semua Bulan</option>
            @foreach ($bulanNama as $num => $nama)
                <option value="{{ $num }}" @selected((string) ($filters['bulan'] ?? '') === (string) $num)>{{ $nama }}</option>
            @endforeach
        </select>
    @endif

    @if (in_array('tahun', $show, true))
        <label class="tap-chip" style="display:inline-flex; gap:6px; align-items:center;">
            Tahun
            <input type="number" name="tahun" min="2000" max="{{ $tahunSekarang + 1 }}"
                   value="{{ $filters['tahun'] ?? $tahunSekarang }}"
                   style="border:0; background:transparent; width:64px;" aria-label="Tahun"
                   onchange="this.form.submit()">
        </label>
    @endif

    <button type="submit" class="tap-chip">Tapis</button>
    @if (array_filter($filters))<a href="{{ route($routeName) }}" class="tap-chip">✕ Reset</a>@endif
    <button type="button" class="tap-chip" onclick="window.print()">🖨 Cetak</button>
</form>
