@extends('layouts.awam')
@section('title', 'Log Masuk')
@section('content')
<form method="POST" action="{{ route('awam.login.attempt') }}" class="awam-card">
    @csrf
    <h1>Log Masuk</h1>
    @error('nokp') <p class="form-error">{{ $message }}</p> @enderror
    <label>No. Kad Pengenalan
        <input name="nokp" value="{{ old('nokp') }}" required>
    </label>
    <label>Kata Laluan
        <input type="password" name="password" required>
    </label>
    <label>Pengesahan: {{ $captchaA }} + {{ $captchaB }} = ?
        <input type="number" name="captcha" required>
    </label>
    @error('captcha') <p class="form-error">{{ $message }}</p> @enderror
    <button type="submit">Log Masuk</button>
    <a href="{{ route('awam.daftar') }}">Belum berdaftar? Daftar di sini</a>
</form>
@endsection
