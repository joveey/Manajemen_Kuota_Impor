@extends('errors.layout')

@section('title', 'Tidak Diizinkan')

@section('content')
    <div class="empty-state">
        <div class="empty-state__icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h1 class="empty-state__title">Tidak Diizinkan</h1>
        <p class="empty-state__subtitle">Sesi Anda mungkin kedaluwarsa atau Anda belum terotentikasi.</p>
        <div class="empty-state__actions">
            <a href="{{ route('login') }}" class="btn btn-primary">Masuk Kembali</a>
            <a href="{{ url()->previous() }}" class="btn btn-outline-primary">Kembali</a>
        </div>
    </div>
@endsection
