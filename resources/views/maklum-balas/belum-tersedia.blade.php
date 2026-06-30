@extends('maklum-balas.layout')

@section('title', 'Belum Tersedia')
@section('heading', 'Maklum Balas Belum Tersedia')
@section('ref', 'No. Permohonan: ' . $kn->no_permohonan)

@section('content')
    <p class="mb-state-icon" aria-hidden="true">⏳</p>
    <p>Borang maklum balas hanya tersedia selepas sesi Khidmat Nasihat anda <strong>selesai</strong>.</p>
    <p>Sila kembali ke pautan ini setelah temu janji anda selesai. Terima kasih.</p>
@endsection
