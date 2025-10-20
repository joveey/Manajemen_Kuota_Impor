@extends('errors.layout')

@section('title', 'Akses Ditolak')

@section('content')
    <div class="empty-state">
        <div class="empty-state__icon">
            <i class="fas fa-ban"></i>
        </div>
        <h1 class="empty-state__title">Akses Ditolak</h1>
        <p class="empty-state__subtitle">Anda tidak memiliki izin untuk mengakses halaman ini.</p>
        <div class="empty-state__actions">
            <a href="{{ url()->previous() }}" class="btn btn-outline-primary">Kembali</a>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-primary">Ke Dashboard</a>
        </div>
    </div>
@endsection
