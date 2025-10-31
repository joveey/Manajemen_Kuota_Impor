@extends('layouts.auth')

@section('title','Login')

@section('content')
    <div class="card shadow-sm" style="width: 420px; max-width: 92vw;">
        <div class="card-body p-4 p-md-5">
            <h5 class="fw-bold text-center mb-4" style="letter-spacing:-.01em;">Sign in to Your Account</h5>

            @if (session('status'))
                <div class="alert alert-success" role="alert">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger small" role="alert">
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" novalidate>
                @csrf

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control" required autofocus autocomplete="username">
                </div>

                <div class="mb-2">
                    <label for="password" class="form-label">Password</label>
                    <input id="password" type="password" name="password" class="form-control" required autocomplete="current-password">
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="remember_me" name="remember">
                        <label class="form-check-label" for="remember_me">Remember me</label>
                    </div>
                    @if (Route::has('password.request'))
                        <a class="link-secondary small" href="{{ route('password.request') }}">Forgot password?</a>
                    @endif
                </div>

                <button type="submit" class="btn btn-primary w-100">Log In</button>
            </form>

            @if (Route::has('register'))
                <p class="text-center mt-4 mb-0 small text-muted">Don't have an account?
                    <a href="{{ route('register') }}" class="link-primary fw-semibold">Register</a>
                </p>
            @endif
        </div>
    </div>
@endsection
