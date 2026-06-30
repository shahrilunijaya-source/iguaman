@extends('maklum-balas.layout')

@section('title', 'Terima Kasih')
@section('heading', 'Terima Kasih')
@section('ref', 'No. Permohonan: ' . $kn->no_permohonan)

@section('content')
    @if (session('maklum_balas_berjaya'))
        <div class="mb-notis" role="status">Maklum balas anda telah berjaya dihantar.</div>
    @endif

    <p class="mb-state-icon" aria-hidden="true">✅</p>
    <p>Maklum balas bagi permohonan ini telah <strong>dihantar</strong>.</p>
    <p>Terima kasih kerana meluangkan masa memberi maklum balas. Pandangan anda amat dihargai dalam usaha kami menambah baik perkhidmatan.</p>
@endsection
