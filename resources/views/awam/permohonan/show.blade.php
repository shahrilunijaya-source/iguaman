@extends('layouts.awam')

@section('title', 'Butiran Permohonan')

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">{{ $khidmat->no_permohonan ?: 'Draf Permohonan' }}</h1>
            <p class="tap-head__sub">Butiran permohonan Khidmat Nasihat anda.</p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ route('awam.dashboard') }}" class="tap-head__btn">&#8592; Kembali</a>
        </div>
    </div>

    <div class="tap-card" style="margin-bottom:16px;">
        <div class="tap-card__eyebrow">Maklumat Permohonan</div>
        <table style="width:100%; border-collapse:collapse; font-size:13px; margin-top:12px;">
            <tbody>
                <tr style="border-bottom:1px solid var(--line);">
                    <td style="padding:10px 0; color:var(--mute); width:40%;">No. Permohonan</td>
                    <td style="padding:10px 0; font-weight:600;">{{ $khidmat->no_permohonan ?: '-' }}</td>
                </tr>
                <tr style="border-bottom:1px solid var(--line);">
                    <td style="padding:10px 0; color:var(--mute);">Status</td>
                    <td style="padding:10px 0;">
                        <span style="font-size:11px; font-weight:700; padding:3px 8px; border-radius:999px; background:rgba(26,111,168,0.1); color:var(--teal);">
                            {{ $khidmat->status_kn }}
                        </span>
                    </td>
                </tr>
                <tr style="border-bottom:1px solid var(--line);">
                    <td style="padding:10px 0; color:var(--mute);">Cawangan</td>
                    <td style="padding:10px 0;">{{ $khidmat->cawangan?->nama ?? '-' }}</td>
                </tr>
                <tr style="border-bottom:1px solid var(--line);">
                    <td style="padding:10px 0; color:var(--mute);">Kategori Kes</td>
                    <td style="padding:10px 0;">{{ $khidmat->kategori?->jenis_kategori ?? '-' }}</td>
                </tr>
                <tr style="border-bottom:1px solid var(--line);">
                    <td style="padding:10px 0; color:var(--mute);">Jumlah Bayaran</td>
                    <td style="padding:10px 0; font-weight:600;">
                        RM {{ number_format((float) $khidmat->jumlah_bayaran, 2) }}
                    </td>
                </tr>
                <tr style="border-bottom:1px solid var(--line);">
                    <td style="padding:10px 0; color:var(--mute);">Tarikh Temu Janji</td>
                    <td style="padding:10px 0;">
                        @if ($khidmat->temuJanji)
                            {{ $khidmat->temuJanji->tarikh_temu_janji?->format('d/m/Y') }}
                            @if ($khidmat->temuJanji->masa_mula)
                                &middot; {{ \Carbon\Carbon::parse($khidmat->temuJanji->masa_mula)->format('H:i') }}
                            @endif
                        @else
                            <span style="color:var(--mute);">Tiada temu janji</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding:10px 0; color:var(--mute);">Tarikh Mohon</td>
                    <td style="padding:10px 0;">{{ $khidmat->created_at?->format('d/m/Y H:i') }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    @php
        $temu = $khidmat->temuJanji;
        $bolehUbah = $temu
            && in_array($temu->status, ['MENUNGGU', 'DISAHKAN'])
            && \Illuminate\Support\Carbon::parse($temu->tarikh_temu_janji)->isFuture();
    @endphp

    @if ($bolehUbah)
        <div class="tap-card" style="margin-bottom:16px;">
            <div class="tap-card__eyebrow">Urus Temu Janji</div>

            {{-- Cancel --}}
            <form method="POST" action="{{ route('awam.permohonan.batal', $khidmat) }}" style="margin-top:16px;">
                @csrf
                <button type="submit"
                    onclick="return confirm('Adakah anda pasti untuk membatalkan temu janji ini?')"
                    style="background:#e53e3e; color:#fff; border:none; padding:8px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600;">
                    Batal Temu Janji
                </button>
            </form>

            {{-- Reschedule --}}
            <form method="POST" action="{{ route('awam.permohonan.reschedule', $khidmat) }}" style="margin-top:20px;">
                @csrf
                <div style="font-size:13px; font-weight:600; margin-bottom:10px; color:var(--fg);">Jadual Semula Temu Janji</div>
                <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
                    <div>
                        <label style="display:block; font-size:12px; color:var(--mute); margin-bottom:4px;">Tarikh Baru</label>
                        <input type="date" name="tarikh_temu_janji" required
                            style="border:1px solid var(--line); border-radius:6px; padding:6px 10px; font-size:13px;">
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; color:var(--mute); margin-bottom:4px;">Masa Baru</label>
                        <input type="time" name="masa_temu_janji" required step="1800"
                            style="border:1px solid var(--line); border-radius:6px; padding:6px 10px; font-size:13px;">
                    </div>
                    <div>
                        <button type="submit"
                            style="background:var(--teal); color:#fff; border:none; padding:8px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600;">
                            Jadual Semula
                        </button>
                    </div>
                </div>
            </form>
        </div>
    @endif

    {{-- Dokumen Sokongan --}}
    <div class="tap-card" style="margin-bottom:16px;">
        <div class="tap-card__eyebrow">Dokumen Sokongan</div>

        @if (session('status') && str_contains(session('status'), 'Dokumen'))
            <p style="font-size:13px; color:var(--teal); margin:10px 0 0;">{{ session('status') }}</p>
        @endif

        @if ($lampiranList->isNotEmpty())
            <ul style="margin:14px 0 16px; padding:0; list-style:none;">
                @foreach ($lampiranList as $file)
                    <li style="display:flex; align-items:center; gap:10px; padding:8px 0; border-bottom:1px solid var(--line); font-size:13px;">
                        <span style="flex:1; color:var(--fg);">{{ $file->nama }}</span>
                        <a href="{{ route('awam.lampiran.download', [$khidmat, $file->id]) }}"
                           style="color:var(--teal); font-weight:600; text-decoration:none;">Muat Turun</a>
                    </li>
                @endforeach
            </ul>
        @else
            <p style="font-size:13px; color:var(--mute); margin:12px 0 16px;">Tiada dokumen dimuat naik.</p>
        @endif

        <form method="POST" action="{{ route('awam.lampiran.store', $khidmat) }}" enctype="multipart/form-data">
            @csrf
            <div style="font-size:12px; font-weight:600; color:var(--fg); margin-bottom:8px;">Muat Naik Dokumen Baharu</div>
            @error('fail')
                <p style="font-size:12px; color:#e53e3e; margin:0 0 8px;">{{ $message }}</p>
            @enderror
            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                <input type="file" name="fail" accept=".pdf,.jpg,.jpeg,.png"
                    style="font-size:13px; border:1px solid var(--line); border-radius:6px; padding:5px 8px;">
                <button type="submit"
                    style="background:var(--teal); color:#fff; border:none; padding:7px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600;">
                    Muat Naik
                </button>
            </div>
            <p style="font-size:11px; color:var(--mute); margin:6px 0 0;">Format: PDF, JPG, PNG. Saiz maksimum: 5 MB.</p>
        </form>
    </div>

    @if ($khidmat->status_kn === \App\Models\KhidmatNasihat::STATUS_SELESAI)
        <div class="tap-card" style="margin-bottom:16px;">
            <div class="tap-card__eyebrow">Maklum Balas</div>
            <p style="font-size:13px; color:var(--mute); margin:12px 0;">Temu janji anda telah selesai. Sila kongsikan maklum balas anda untuk membantu kami menambah baik perkhidmatan.</p>
            <a href="{{ route('maklum-balas.show', $khidmat->no_permohonan) }}"
               style="display:inline-block; background:var(--teal); color:#fff; padding:8px 16px; border-radius:6px; font-size:13px; font-weight:600; text-decoration:none;">
                Beri Maklum Balas
            </a>
        </div>
    @endif
@endsection
