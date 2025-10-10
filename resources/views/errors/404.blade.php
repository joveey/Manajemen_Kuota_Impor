@extends('layouts.app')

@section('content')
    <div class="max-w-3xl mx-auto py-12">
        <h1 class="text-2xl font-semibold mb-4">Halaman Tidak Ditemukan</h1>
        <p class="mb-6">Maaf, halaman yang Anda cari tidak tersedia.</p>
        <a href="{{ route('dashboard') }}" class="text-blue-600 underline">Kembali ke Dashboard</a>
    </div>
@endsection

