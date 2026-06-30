@extends('layouts.awam')
@section('title', 'Daftar')
@section('content')
<form method="POST" action="{{ route('awam.daftar.store') }}" class="awam-card">
    @csrf
    <h1>Daftar Akaun</h1>
    <label>Nama Penuh <input name="name" value="{{ old('name') }}" required></label>
    @error('name') <p class="form-error">{{ $message }}</p> @enderror
    <label>No. Kad Pengenalan <input name="nokp" value="{{ old('nokp') }}" required></label>
    @error('nokp') <p class="form-error">{{ $message }}</p> @enderror
    <label>Emel (pilihan) <input type="email" name="email" value="{{ old('email') }}"></label>
    @error('email') <p class="form-error">{{ $message }}</p> @enderror
    <label>Kata Laluan <input type="password" name="password" required></label>
    @error('password') <p class="form-error">{{ $message }}</p> @enderror
    <label>Sahkan Kata Laluan <input type="password" name="password_confirmation" required></label>
    <label>Pengesahan: {{ $captchaA }} + {{ $captchaB }} = ? <input type="number" name="captcha" required></label>
    @error('captcha') <p class="form-error">{{ $message }}</p> @enderror
    <input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
    @error('website') <p class="form-error">{{ $message }}</p> @enderror
    <button type="submit">Daftar</button>
</form>
@endsection
