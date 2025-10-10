@extends('layouts.app')

@section('content')
    <div class="max-w-3xl mx-auto py-12">
        <h1 class="text-2xl font-semibold mb-4">Unauthorized</h1>
        <p class="mb-6">Anda harus login untuk mengakses halaman ini.</p>
        <a href="{{ route('login') }}" class="text-blue-600 underline">Masuk</a>
    </div>
@endsection

