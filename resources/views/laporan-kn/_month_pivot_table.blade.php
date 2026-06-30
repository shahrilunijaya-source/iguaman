{{-- Month-pivot table: $pivot (list of {label, months[1..12], total}), $rowHeading. --}}
@php
    $bulanPendek = [1 => 'Jan', 2 => 'Feb', 3 => 'Mac', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Ogo', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Dis'];
    $colTotals = array_fill(1, 12, 0);
    $grand = 0;
    foreach ($pivot as $r) {
        foreach ($r['months'] as $m => $v) { $colTotals[$m] += $v; }
        $grand += $r['total'];
    }
@endphp

<div class="tap-card" style="overflow-x:auto;">
    <table class="lkn-table">
        <thead>
            <tr>
                <th>{{ $rowHeading }}</th>
                @foreach ($bulanPendek as $nama)
                    <th class="lkn-num">{{ $nama }}</th>
                @endforeach
                <th class="lkn-num">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($pivot as $r)
                <tr>
                    <td>{{ $r['label'] }}</td>
                    @for ($m = 1; $m <= 12; $m++)
                        <td class="lkn-num">{{ $r['months'][$m] ?: '' }}</td>
                    @endfor
                    <td class="lkn-num">{{ number_format($r['total']) }}</td>
                </tr>
            @empty
                <tr><td colspan="14" style="padding:16px; color:var(--mute); text-align:center;">Tiada rekod.</td></tr>
            @endforelse
        </tbody>
        @if (count($pivot))
            <tfoot>
                <tr>
                    <td>JUMLAH</td>
                    @for ($m = 1; $m <= 12; $m++)
                        <td class="lkn-num">{{ $colTotals[$m] ?: '' }}</td>
                    @endfor
                    <td class="lkn-num">{{ number_format($grand) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>
