@extends('layouts.awam')

@section('title', 'Permohonan Saya')

@section('content')
    <div style="max-width:900px;margin:0 auto;padding:24px 16px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
            <h1 style="font-size:22px;font-weight:700;color:var(--pine-deep);">Permohonan Saya</h1>
            <a href="{{ route('awam.permohonan.saringan') }}"
               style="padding:10px 20px;background:var(--teal);color:#fff;border-radius:8px;text-decoration:none;font-size:14px;font-weight:600;">
                + Mohon Baharu
            </a>
        </div>

        @if ($khidmat->isEmpty())
            <div style="padding:40px;text-align:center;color:#888;border:1px dashed #ccc;border-radius:10px;">
                Tiada permohonan lagi. Klik <strong>Mohon Baharu</strong> untuk memulakan permohonan.
            </div>
        @else
            <table style="width:100%;border-collapse:collapse;font-size:14px;">
                <thead>
                    <tr style="border-bottom:2px solid var(--line);text-align:left;">
                        <th style="padding:10px 12px;">No. Permohonan</th>
                        <th style="padding:10px 12px;">Status</th>
                        <th style="padding:10px 12px;">Tarikh Janji Temu</th>
                        <th style="padding:10px 12px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($khidmat as $k)
                        <tr style="border-bottom:1px solid var(--line);">
                            <td style="padding:10px 12px;">{{ $k->no_permohonan ?: '—' }}</td>
                            <td style="padding:10px 12px;">
                                <span style="font-size:12px;padding:3px 10px;border-radius:999px;background:#f0f9f8;color:var(--pine-deep);font-weight:600;">
                                    {{ $k->status_kn }}
                                </span>
                            </td>
                            <td style="padding:10px 12px;">
                                {{ $k->temuJanji?->tarikh_temu_janji?->format('d/m/Y') ?? '—' }}
                            </td>
                            <td style="padding:10px 12px;text-align:right;">
                                <a href="{{ route('awam.permohonan.show', $k) }}"
                                   style="color:var(--teal);font-size:13px;text-decoration:none;">Lihat</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div style="margin-top:16px;">
                {{ $khidmat->links() }}
            </div>
        @endif
    </div>
@endsection
