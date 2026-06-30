@extends('maklum-balas.layout')

@section('title', 'Borang Maklum Balas')
@section('heading', 'Maklum Balas Perkhidmatan')
@section('ref', 'No. Permohonan: ' . $kn->no_permohonan)

@section('content')
    @if (session('maklum_balas_notis'))
        <div class="mb-notis" role="status">{{ session('maklum_balas_notis') }}</div>
    @endif

    @if ($errors->any())
        <div class="mb-errors" role="alert">
            <strong>Sila betulkan ralat berikut:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <p>Terima kasih kerana menggunakan perkhidmatan Khidmat Nasihat JBG. Maklum balas anda membantu kami menambah baik perkhidmatan.</p>

    <form method="POST" action="{{ route('maklum-balas.store', $kn->no_permohonan) }}">
        @csrf

        <fieldset>
            <legend>1. Bagaimana anda mengetahui tentang JBG? <span class="req">*</span></legend>

            <div class="mb-check">
                <input type="checkbox" id="soalan_1a" name="soalan_1a" value="1" @checked(old('soalan_1a'))>
                <label for="soalan_1a">Portal / Laman web JBG</label>
            </div>
            <div class="mb-check">
                <input type="checkbox" id="soalan_1b" name="soalan_1b" value="1" @checked(old('soalan_1b'))>
                <label for="soalan_1b">Media sosial</label>
            </div>
            <div class="mb-check">
                <input type="checkbox" id="soalan_1c" name="soalan_1c" value="1" @checked(old('soalan_1c'))>
                <label for="soalan_1c">Rujukan keluarga / rakan</label>
            </div>
            <div class="mb-check">
                <input type="checkbox" id="soalan_1d" name="soalan_1d" value="1" @checked(old('soalan_1d'))>
                <label for="soalan_1d">Jabatan / agensi lain</label>
            </div>
            <div class="mb-check">
                <input type="checkbox" id="soalan_1e" name="soalan_1e" value="1" @checked(old('soalan_1e'))>
                <label for="soalan_1e">Lain-lain</label>
            </div>

            <div class="mb-field">
                <label class="mb-label" for="soalan_1_lain_lain">Jika "Lain-lain", sila nyatakan</label>
                <input type="text" id="soalan_1_lain_lain" name="soalan_1_lain_lain" maxlength="255" value="{{ old('soalan_1_lain_lain') }}">
            </div>
        </fieldset>

        <fieldset>
            <legend>2. Tahap kepuasan terhadap perkhidmatan <span class="req">*</span></legend>

            <div class="mb-radio">
                <input type="radio" id="soalan_2a_cemerlang" name="soalan_2a" value="CEMERLANG" @checked(old('soalan_2a') === 'CEMERLANG')>
                <label for="soalan_2a_cemerlang">Cemerlang</label>
            </div>
            <div class="mb-radio">
                <input type="radio" id="soalan_2a_baik" name="soalan_2a" value="BAIK" @checked(old('soalan_2a') === 'BAIK')>
                <label for="soalan_2a_baik">Baik</label>
            </div>
            <div class="mb-radio">
                <input type="radio" id="soalan_2a_kurang" name="soalan_2a" value="KURANG_MEMUASKAN" @checked(old('soalan_2a') === 'KURANG_MEMUASKAN')>
                <label for="soalan_2a_kurang">Kurang memuaskan</label>
            </div>
        </fieldset>

        <fieldset>
            <legend>3. Cadangan penambahbaikan</legend>
            <div class="mb-field">
                <label class="mb-label" for="soalan_cadangan">Cadangan anda (pilihan)</label>
                <textarea id="soalan_cadangan" name="soalan_cadangan" maxlength="2000">{{ old('soalan_cadangan') }}</textarea>
            </div>
        </fieldset>

        <button type="submit" class="mb-btn">Hantar Maklum Balas</button>
    </form>
@endsection
