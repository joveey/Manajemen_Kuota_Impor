@extends('layouts.auth')

@section('title','Register')

@section('content')
    <div class="card shadow-sm" style="width: 420px; max-width: 92vw;">
        <div class="card-body p-4 p-md-5">
            <h5 class="fw-bold text-center mb-4" style="letter-spacing:-.01em;">Create New Account</h5>

            @if ($errors->any())
                <div class="alert alert-danger small" role="alert">
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('register') }}" novalidate>
                @csrf

                <div class="mb-3">
                    <label for="name" class="form-label">Full Name</label>
                    <input id="name" type="text" name="name" value="{{ old('name') }}" class="form-control" required autofocus autocomplete="name">
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control" required autocomplete="username">
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input id="password" type="password" name="password" class="form-control" required autocomplete="new-password">
                </div>

                <div class="mb-3">
                    <label for="password_confirmation" class="form-label">Confirm Password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
                </div>

                <button type="submit" class="btn btn-primary w-100">Register</button>
            </form>

            <p class="text-center mt-4 mb-0 small text-muted">Already have an account?
                <a href="{{ route('login') }}" class="link-primary fw-semibold">Login</a>
            </p>
        </div>
    </div>
@endsection
