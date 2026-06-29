{{-- Horizontal bar breakdown. Params: $title (string), $data (label => count). --}}
<div class="tap-card">
    <div class="tap-card__eyebrow">{{ $title }}</div>
    @php $max = count($data) ? max($data) : 0; @endphp
    @forelse ($data as $label => $value)
        <div style="margin:8px 0;">
            <div style="display:flex; justify-content:space-between; font-size:12.5px; margin-bottom:4px;">
                <span style="color:var(--ink); font-weight:500;">{{ $label }}</span>
                <span class="tabular" style="color:var(--mute); font-weight:600;">{{ number_format($value) }}</span>
            </div>
            <div style="height:6px; background:var(--paper-2); border-radius:3px; overflow:hidden;">
                <div style="height:100%; width:{{ $max > 0 ? max(2, round($value / $max * 100)) : 0 }}%; background:var(--teal); border-radius:3px;"></div>
            </div>
        </div>
    @empty
        <div class="dash-empty__sub" style="padding:6px 0;">Tiada data.</div>
    @endforelse
</div>
