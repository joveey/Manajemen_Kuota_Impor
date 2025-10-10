@extends('layouts.app')

@section('content')
    <div class="max-w-3xl mx-auto py-12">
        <h1 class="text-2xl font-semibold mb-4">Akses Ditolak</h1>
        <p class="mb-6">Anda tidak memiliki izin untuk mengakses halaman ini.</p>
        <a href="{{ url()->previous() }}" class="text-blue-600 underline">Kembali</a>
    </div>
@endsection

