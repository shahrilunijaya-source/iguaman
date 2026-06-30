@extends('layouts.staff')
@section('content')
<h1 class="ws-title">Akses Peranan: {{ ucfirst($role->name) }}</h1>
<form method="POST" action="{{ route('peranan.akses.update', $role) }}">
    @csrf @method('PUT')
    @foreach($grouped as $module => $perms)
        <fieldset class="ws-card">
            <legend>{{ strtoupper($module) }}</legend>
            @foreach($perms as $perm)
                <label>
                    <input type="checkbox" name="permissions[]" value="{{ $perm->name }}"
                        @checked(in_array($perm->name, $assigned, true))>
                    {{ $perm->name }}
                </label>
            @endforeach
        </fieldset>
    @endforeach
    <button type="submit" class="ws-btn ws-btn--primary">Simpan Akses</button>
</form>
@endsection
