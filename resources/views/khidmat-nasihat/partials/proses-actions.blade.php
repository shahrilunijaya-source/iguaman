@php
    /** @var \App\Models\KhidmatNasihat $row */
    $temuStatus = optional($row->temuJanji)->status;
@endphp

{{-- Assign PKN: only while BAHARU. --}}
@if ($row->status_kn === \App\Models\KhidmatNasihat::STATUS_BAHARU)
    <form method="POST" action="{{ route('khidmat.proses.assign', $row) }}" style="display:flex; gap:4px; align-items:center;">
        @csrf
        <select name="id_pegawai_kn" class="tap-chip" required style="max-width:140px;">
            <option value="">Pilih pegawai…</option>
            @foreach ($pegawaiList as $id => $nama)
                <option value="{{ $id }}">{{ $nama }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn--primary" style="padding:4px 10px;">Agih</button>
    </form>
@endif

{{-- Pengesahan janji temu: contextual on the linked appointment status. --}}
@if ($temuStatus === 'MENUNGGU')
    <form method="POST" action="{{ route('khidmat.proses.temu.terima', $row) }}">
        @csrf
        <button type="submit" class="btn" style="padding:4px 10px; color:var(--success);">Sahkan</button>
    </form>
    <form method="POST" action="{{ route('khidmat.proses.temu.tolak', $row) }}" onsubmit="this.ulasan_pegawai.value = prompt('Sebab penolakan/pembatalan:') || ''; return this.ulasan_pegawai.value !== '';">
        @csrf
        <input type="hidden" name="ulasan_pegawai">
        <button type="submit" class="btn" style="padding:4px 10px;">Tolak</button>
    </form>
@elseif ($temuStatus === 'DISAHKAN')
    <form method="POST" action="{{ route('khidmat.proses.temu.kehadiran', $row) }}">
        @csrf
        <input type="hidden" name="hadir" value="1">
        <button type="submit" class="btn" style="padding:4px 10px;">Hadir</button>
    </form>
    <form method="POST" action="{{ route('khidmat.proses.temu.kehadiran', $row) }}">
        @csrf
        <input type="hidden" name="hadir" value="0">
        <button type="submit" class="btn" style="padding:4px 10px;">Tidak Hadir</button>
    </form>
@elseif ($temuStatus === 'HADIR')
    <form method="POST" action="{{ route('khidmat.proses.temu.selesai', $row) }}">
        @csrf
        <button type="submit" class="btn btn--primary" style="padding:4px 10px;">Selesai</button>
    </form>
@endif
