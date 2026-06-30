{{-- Print-friendly CSS for KN reports: hide app chrome + filter form on print. --}}
<style>
    @media print {
        .ws-topbar, .ws-sidebar, .js-no-print { display: none !important; }
        .ws-content, .ws-page, .ws-page__main { margin: 0 !important; padding: 0 !important; }
        .tap-card { box-shadow: none !important; border: 1px solid #ccc; }
        body { background: #fff !important; }
        table { font-size: 11px !important; }
        a[href]::after { content: ""; }
    }
    .lkn-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .lkn-table th { text-align: left; padding: 8px 10px; border-bottom: 2px solid var(--line, #dde5e3); color: var(--pine-deep, #0d2e48); font-size: 11px; text-transform: uppercase; letter-spacing: .4px; white-space: nowrap; }
    .lkn-table td { padding: 7px 10px; border-bottom: 1px solid #eef2f1; }
    .lkn-table tfoot td { font-weight: 700; border-top: 2px solid var(--line, #dde5e3); }
    .lkn-num { text-align: right; font-variant-numeric: tabular-nums; }
</style>
