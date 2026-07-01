@extends('layouts.awam')

@section('title', 'Saringan Kelayakan')

@section('content')
    <div style="max-width:640px;margin:0 auto;padding:24px 16px;">
        <h1 style="font-size:22px;font-weight:700;color:var(--pine-deep);margin-bottom:8px;">Saringan Kelayakan</h1>
        <p style="color:#666;font-size:14px;margin-bottom:24px;">
            Sila jawab soalan berikut untuk menentukan kelayakan anda memohon Khidmat Nasihat JBG.
        </p>

        @if (session('saringan_gagal'))
            <div style="padding:12px 16px;background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;color:#b91c1c;margin-bottom:20px;font-size:14px;">
                {{ session('saringan_gagal') }}
            </div>
        @endif

        @if ($errors->any())
            <div style="padding:12px 16px;background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;color:#b91c1c;margin-bottom:20px;font-size:14px;">
                Sila lengkapkan semua medan yang diperlukan.
            </div>
        @endif

        <form method="POST" action="{{ route('awam.permohonan.saringan.semak') }}">
            @csrf

            <div style="background:#fff;border:1px solid var(--line);border-radius:10px;padding:20px;margin-bottom:16px;">
                <label style="display:block;font-weight:600;font-size:14px;margin-bottom:12px;color:var(--pine-deep);">
                    1. Jenis Khidmat Nasihat *
                </label>
                <label style="display:flex;align-items:center;gap:10px;margin-bottom:10px;font-size:14px;">
                    <input type="radio" name="saringan_jenis" value="sivil_syariah" required
                           @checked(old('saringan_jenis') === 'sivil_syariah')>
                    Sivil / Syariah
                </label>
                <label style="display:flex;align-items:center;gap:10px;font-size:14px;">
                    <input type="radio" name="saringan_jenis" value="pendamping_jenayah"
                           @checked(old('saringan_jenis') === 'pendamping_jenayah')>
                    Pendamping Jenayah
                </label>
                @error('saringan_jenis')
                    <p style="color:#dc2626;font-size:12px;margin-top:6px;">{{ $message }}</p>
                @enderror
            </div>

            <div style="background:#fff;border:1px solid var(--line);border-radius:10px;padding:20px;margin-bottom:16px;">
                <label style="display:block;font-weight:600;font-size:14px;margin-bottom:12px;color:var(--pine-deep);">
                    2. Adakah anda <strong>tiada</strong> nasihat guaman terdahulu daripada JBG? *
                </label>
                <label style="display:flex;align-items:center;gap:10px;margin-bottom:10px;font-size:14px;">
                    <input type="radio" name="tiada_nasihat_terdahulu" value="Ya" required
                           @checked(old('tiada_nasihat_terdahulu') === 'Ya')>
                    Ya
                </label>
                <label style="display:flex;align-items:center;gap:10px;font-size:14px;">
                    <input type="radio" name="tiada_nasihat_terdahulu" value="Tidak"
                           @checked(old('tiada_nasihat_terdahulu') === 'Tidak')>
                    Tidak
                </label>
                @error('tiada_nasihat_terdahulu')
                    <p style="color:#dc2626;font-size:12px;margin-top:6px;">{{ $message }}</p>
                @enderror
            </div>

            <div style="background:#fff;border:1px solid var(--line);border-radius:10px;padding:20px;margin-bottom:16px;">
                <label style="display:block;font-weight:600;font-size:14px;margin-bottom:12px;color:var(--pine-deep);">
                    3. Adakah kes anda <strong>tiada</strong> dalam senarai perkara dikecualikan? *
                </label>
                <label style="display:flex;align-items:center;gap:10px;margin-bottom:10px;font-size:14px;">
                    <input type="radio" name="tiada_perkara_dikecualikan" value="Ya" required
                           @checked(old('tiada_perkara_dikecualikan') === 'Ya')>
                    Ya
                </label>
                <label style="display:flex;align-items:center;gap:10px;font-size:14px;">
                    <input type="radio" name="tiada_perkara_dikecualikan" value="Tidak"
                           @checked(old('tiada_perkara_dikecualikan') === 'Tidak')>
                    Tidak
                </label>
                @error('tiada_perkara_dikecualikan')
                    <p style="color:#dc2626;font-size:12px;margin-top:6px;">{{ $message }}</p>
                @enderror
            </div>

            <div style="background:#fff;border:1px solid var(--line);border-radius:10px;padding:20px;margin-bottom:16px;">
                <label style="display:block;font-weight:600;font-size:14px;margin-bottom:12px;color:var(--pine-deep);">
                    4. Adakah pendapatan anda <strong>bawah</strong> had kelayakan? (hanya untuk Sivil/Syariah)
                </label>
                <label style="display:flex;align-items:center;gap:10px;margin-bottom:10px;font-size:14px;">
                    <input type="radio" name="pendapatan_bawah_had" value="Ya"
                           @checked(old('pendapatan_bawah_had', 'Ya') === 'Ya')>
                    Ya (RM 50,000 ke bawah setahun)
                </label>
                <label style="display:flex;align-items:center;gap:10px;font-size:14px;">
                    <input type="radio" name="pendapatan_bawah_had" value="Tidak"
                           @checked(old('pendapatan_bawah_had') === 'Tidak')>
                    Tidak (melebihi had - sumbangan RM 260 dikenakan)
                </label>
            </div>

            <div style="background:#fff;border:1px solid var(--line);border-radius:10px;padding:20px;margin-bottom:20px;">
                <label style="display:flex;align-items:flex-start;gap:12px;font-size:14px;cursor:pointer;">
                    <input type="checkbox" name="terima_terma" value="1" required
                           @checked(old('terima_terma'))
                           style="margin-top:2px;">
                    <span>Saya mengakui bahawa maklumat yang diberikan adalah benar dan saya faham syarat-syarat kelayakan Khidmat Nasihat JBG.</span>
                </label>
                @error('terima_terma')
                    <p style="color:#dc2626;font-size:12px;margin-top:6px;">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                    style="width:100%;padding:14px;background:var(--teal);color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer;">
                Semak Kelayakan
            </button>
        </form>
    </div>
@endsection
