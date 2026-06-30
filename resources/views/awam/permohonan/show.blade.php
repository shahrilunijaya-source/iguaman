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
                    <td style="padding:10px 0; font-weight:600;">{{ $khidmat->no_permohonan ?: '—' }}</td>
                </tr>
                <tr style="border-bottom:1px solid var(--line);">
                    <td style="padding:10px 0; color:var(--mute);">Status</td>
                    <td style="padding:10px 0;">
                        <span style="font-size:11px; font-weight:700; padding:3px 8px; border-radius:999px; background:rgba(0,184,169,0.1); color:var(--teal);">
                            {{ $khidmat->status_kn }}
                        </span>
                    </td>
                </tr>
                <tr style="border-bottom:1px solid var(--line);">
                    <td style="padding:10px 0; color:var(--mute);">Cawangan</td>
                    <td style="padding:10px 0;">{{ $khidmat->cawangan?->nama ?? '—' }}</td>
                </tr>
                <tr style="border-bottom:1px solid var(--line);">
                    <td style="padding:10px 0; color:var(--mute);">Kategori Kes</td>
                    <td style="padding:10px 0;">{{ $khidmat->kategori?->jenis_kategori ?? '—' }}</td>
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
@endsection
