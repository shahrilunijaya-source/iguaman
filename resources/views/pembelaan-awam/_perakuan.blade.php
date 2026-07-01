@php
    $status = $kes->status_perakuan;
@endphp

<div class="card" style="padding:18px;">
    <h2 style="margin:0 0 12px; font-size:15px;">Perakuan Bantuan Guaman</h2>

    <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:10px 24px; margin-bottom:14px;">
        <div><span style="color:var(--muted,#64748b); font-size:12px;">Status Perakuan</span><br>
            @if ($status)
                <span class="pill pill--received">{{ $status }}</span>
            @else
                <span style="color:var(--muted,#64748b);">Belum dikeluarkan</span>
            @endif
        </div>
        <div><span style="color:var(--muted,#64748b); font-size:12px;">No. Perakuan</span><br>{{ $kes->no_perakuan ?? '-' }}</div>
    </div>

    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        @can('kes.perakuan')
            @if (blank($status))
                <form method="POST" action="{{ route('pembelaan.perakuan.interim', $kes) }}">
                    @csrf
                    @unless ($kes->is_segera)
                        @can('pembelaan.manage')
                            <input type="hidden" name="override" value="1">
                        @endcan
                    @endunless
                    <button class="btn btn--primary"
                        @if (! $kes->is_segera && ! auth()->user()->can('pembelaan.manage')) disabled title="Hanya kes segera" @endif>
                        Keluar Perakuan Interim
                    </button>
                </form>
                @unless ($kes->is_segera)
                    <span style="align-self:center; color:var(--muted,#64748b); font-size:12px;">Kes bukan segera - perlu kebenaran pengurus untuk keluar interim.</span>
                @endunless
            @elseif ($status === \App\Support\PerakuanService::STATUS_INTERIM)
                <form method="POST" action="{{ route('pembelaan.perakuan.muktamad', $kes) }}">
                    @csrf
                    <button class="btn btn--primary">Muktamadkan Perakuan</button>
                </form>
            @endif
        @endcan

        @if (filled($status))
            <a href="{{ route('cetak.perakuan', $kes) }}" class="btn" target="_blank">Cetak Perakuan</a>
        @endif
    </div>
</div>
