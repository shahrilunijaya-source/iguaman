@extends('layouts.staff')

@section('title', 'Laporan Khidmat Nasihat')

@php
    $reports = [
        ['route' => 'laporan-kn.pandangan-uu',      'no' => 1, 'label' => 'Pandangan Undang-Undang', 'desc' => 'Senarai terperinci pandangan undang-undang (ulasan pegawai) bagi setiap permohonan.', 'viz' => 'Senarai · Excel · Cetak'],
        ['route' => 'laporan-kn.cara-mengetahui',   'no' => 2, 'label' => 'Cara Mengetahui JBG',      'desc' => 'Bilangan mengikut cara pelanggan mengetahui perkhidmatan JBG (maklum balas).', 'viz' => 'Carta Pai · Jadual'],
        ['route' => 'laporan-kn.mengikut-cawangan', 'no' => 3, 'label' => 'Mengikut Cawangan',        'desc' => 'Bilangan permohonan mengikut cawangan × 12 bulan.', 'viz' => 'Carta Bar · Jadual'],
        ['route' => 'laporan-kn.mengikut-kategori', 'no' => 4, 'label' => 'Mengikut Kategori Kes',    'desc' => 'Bilangan permohonan mengikut kategori × 12 bulan.', 'viz' => 'Carta Bar · Jadual'],
        ['route' => 'laporan-kn.mengikut-subkategori', 'no' => 5, 'label' => 'Mengikut Sub Kategori', 'desc' => 'Bilangan permohonan mengikut sub kategori × 12 bulan.', 'viz' => 'Jadual'],
        ['route' => 'laporan-kn.pendaftaran',        'no' => 6, 'label' => 'Pendaftaran Khidmat Nasihat', 'desc' => 'Senarai terperinci pendaftaran KN dengan tarikh temu janji.', 'viz' => 'Senarai · Excel · Cetak'],
        ['route' => 'laporan-kn.kepuasan',           'no' => 7, 'label' => 'Tahap Kepuasan Pelanggan', 'desc' => 'Bilangan mengikut tahap kepuasan perkhidmatan (maklum balas).', 'viz' => 'Carta Pai · Jadual'],
        ['route' => 'laporan-kn.kaum-jantina',       'no' => 8, 'label' => 'Mengikut Kaum / Jantina', 'desc' => 'Bilangan permohonan mengikut bangsa × jantina.', 'viz' => 'Carta Bar · Jadual'],
    ];
@endphp

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Laporan Khidmat Nasihat<span class="dot"></span></h1>
            <p class="tap-head__sub">8 laporan statistik bagi subsistem Khidmat Nasihat.</p>
        </div>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:14px;">
        @foreach ($reports as $r)
            <a href="{{ route($r['route']) }}" class="tap-card" style="display:block; text-decoration:none; color:inherit; padding:16px; transition:box-shadow .15s;">
                <div style="display:flex; align-items:baseline; gap:10px;">
                    <span style="font-size:12px; font-weight:700; color:var(--brand,#1a6fa8);">#{{ $r['no'] }}</span>
                    <h2 style="font-size:15px; margin:0; color:var(--pine-deep,#0d2e48);">{{ $r['label'] }}</h2>
                </div>
                <p style="font-size:12.5px; color:var(--mute,#667); margin:8px 0 10px; line-height:1.45;">{{ $r['desc'] }}</p>
                <span style="font-size:11px; text-transform:uppercase; letter-spacing:.4px; color:var(--mute,#889);">{{ $r['viz'] }}</span>
            </a>
        @endforeach
    </div>
@endsection
