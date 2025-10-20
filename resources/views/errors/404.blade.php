@extends('errors.layout')

@section('title', 'Halaman Tidak Ditemukan')

@section('content')
    <div class="empty-state">
        <div class="empty-state__icon">
            <i class="fas fa-compass"></i>
        </div>
        <h1 class="empty-state__title">Halaman Tidak Ditemukan</h1>
        <p class="empty-state__subtitle">Kami tidak menemukan halaman yang Anda cari.</p>
        <div class="empty-state__actions">
            <a href="{{ url()->previous() }}" class="btn btn-outline-primary">Kembali</a>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-primary">Ke Dashboard</a>
        </div>
    </div>
@endsection
