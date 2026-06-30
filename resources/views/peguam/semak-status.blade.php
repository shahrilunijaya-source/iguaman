<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Semak Status Permohonan Peguam Panel — JBG</title>
    @vite(['resources/css/system.css'])
    <style>
        .ss-wrap { max-width: 520px; margin: 48px auto; padding: 0 16px; }
        .ss-card { background: var(--surface, #fff); border: 1px solid var(--line, #e5e7eb); border-radius: 14px; padding: 28px; }
        .ss-title { font-size: 20px; font-weight: 700; margin: 0 0 4px; }
        .ss-sub { color: var(--mute, #6b7280); font-size: 13px; margin: 0 0 20px; }
        .ss-field { margin-bottom: 14px; }
        .ss-field label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        .ss-field input { width: 100%; padding: 10px 12px; border: 1px solid var(--line, #e5e7eb); border-radius: 8px; font-size: 14px; }
        .ss-btn { display: inline-block; padding: 10px 18px; border: 0; border-radius: 8px; background: var(--brand, #00B8A9); color: #fff; font-weight: 600; cursor: pointer; }
        .ss-link { color: var(--brand, #00B8A9); font-size: 13px; text-decoration: none; }
        .ss-result { margin-top: 18px; padding: 16px; border-radius: 10px; border: 1px solid var(--line, #e5e7eb); }
        .ss-badge { display: inline-block; padding: 4px 12px; border-radius: 999px; font-weight: 600; font-size: 12px; }
        .ss-badge--baharu { background: rgba(201,138,0,.12); color: #C98A00; }
        .ss-badge--lulus { background: rgba(16,185,129,.12); color: #059669; }
        .ss-badge--tolak { background: rgba(220,38,38,.10); color: #dc2626; }
        .ss-row { display: flex; justify-content: space-between; gap: 12px; padding: 6px 0; font-size: 13px; }
        .ss-row .k { color: var(--mute, #6b7280); }
        .ss-err { color: #dc2626; font-size: 13px; margin-bottom: 12px; }
        .honey { position: absolute; left: -9999px; }
    </style>
</head>
<body>
    <div class="ss-wrap">
        <div class="ss-card">
            <h1 class="ss-title">Semak Status Permohonan</h1>
            <p class="ss-sub">Masukkan No. Kad Pengenalan untuk menyemak status permohonan Peguam Panel anda.</p>

            @if ($errors->any())
                <div class="ss-err">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('peguam.semak-status.check') }}">
                @csrf
                <div class="ss-field">
                    <label for="kpBaru">No. Kad Pengenalan</label>
                    <input id="kpBaru" name="kpBaru" value="{{ old('kpBaru', $nokp) }}" maxlength="20" required autofocus placeholder="cth: 880101015523">
                </div>
                <label class="honey">Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                <button type="submit" class="ss-btn">Semak Status</button>
            </form>

            @isset($result)
                @if ($result === null)
                    {{-- first load, no lookup yet --}}
                @elseif (! $result['found'])
                    <div class="ss-result">
                        <p style="margin:0;font-size:13px;">Tiada permohonan ditemui untuk No. K/P <strong>{{ $nokp }}</strong>.</p>
                    </div>
                @else
                    <div class="ss-result">
                        @php
                            $cls = match ($result['status']) {
                                '1' => 'ss-badge--lulus',
                                '2' => 'ss-badge--tolak',
                                default => 'ss-badge--baharu',
                            };
                        @endphp
                        <div style="margin-bottom:10px;"><span class="ss-badge {{ $cls }}">{{ $result['label'] }}</span></div>
                        <div class="ss-row"><span class="k">No. K/P</span><span>{{ $nokp }}</span></div>
                        <div class="ss-row"><span class="k">Tarikh Mohon</span><span>{{ optional($result['tarikhMohon'])->format('d/m/Y') ?: '—' }}</span></div>
                        @if ($result['status'] === '1')
                            <p style="margin:10px 0 0;font-size:13px;">Permohonan anda telah <strong>diluluskan</strong>. Sila hubungi JBG atau semak emel anda untuk butiran akaun log masuk.</p>
                        @elseif ($result['status'] === '2')
                            <div class="ss-row"><span class="k">Sebab</span><span>{{ $result['sebabTolak'] ?: '—' }}</span></div>
                        @elseif ($result['status'] === '0')
                            <p style="margin:10px 0 0;font-size:13px;">Permohonan anda sedang diproses. Sila semak semula kemudian.</p>
                        @endif
                    </div>
                @endif
            @endisset

            <p style="margin:18px 0 0;"><a href="{{ route('peguam.daftar') }}" class="ss-link">← Permohonan Baharu</a></p>
        </div>
    </div>
</body>
</html>
