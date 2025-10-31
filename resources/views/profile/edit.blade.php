@extends('layouts.admin')

@section('title', 'Account Settings')

@section('content')
<div class="page-shell">
  <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <h1 class="page-header__title">Account Settings</h1>
      <p class="page-header__subtitle">Manage your profile information and password.</p>
    </div>
  </div>

  @if (session('status') && session('status') !== 'profile-updated' && session('status') !== 'password-updated')
    <div class="alert alert-success">{{ session('status') }}</div>
  @endif

  <div class="row g-3">
    <div class="col-12 col-lg-6">
      <div class="card">
        <div class="card-header fw-semibold">Profile Information</div>
        <div class="card-body">
          <form id="send-verification" method="post" action="{{ route('verification.send') }}">@csrf</form>
          <form method="post" action="{{ route('profile.update') }}">
            @csrf
            @method('patch')

            <div class="mb-3">
              <label for="name" class="form-label">Name</label>
              <input id="name" name="name" type="text" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" required autocomplete="name">
              @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
              <label for="email" class="form-label">Email</label>
              <input id="email" name="email" type="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" required autocomplete="username">
              @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror

              @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="form-text mt-2">
                  Your email address is unverified.
                  <button form="send-verification" class="btn btn-link p-0 align-baseline">Resend verification email</button>
                  @if (session('status') === 'verification-link-sent')
                    <span class="text-success ms-1">A new verification link has been sent.</span>
                  @endif
                </div>
              @endif
            </div>

            <div class="d-flex align-items-center gap-2">
              <button class="btn btn-primary" type="submit">Save Changes</button>
              @if (session('status') === 'profile-updated')
                <span class="text-muted small">Saved.</span>
              @endif
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-6">
      <div class="card">
        <div class="card-header fw-semibold">Update Password</div>
        <div class="card-body">
          <form method="post" action="{{ route('password.update') }}">
            @csrf
            @method('put')

            <div class="mb-3">
              <label for="update_password_current_password" class="form-label">Current Password</label>
              <input id="update_password_current_password" name="current_password" type="password" class="form-control @if($errors->updatePassword->has('current_password')) is-invalid @endif" autocomplete="current-password">
              @if($errors->updatePassword->has('current_password'))<div class="invalid-feedback">{{ $errors->updatePassword->first('current_password') }}</div>@endif
            </div>

            <div class="mb-3">
              <label for="update_password_password" class="form-label">New Password</label>
              <input id="update_password_password" name="password" type="password" class="form-control @if($errors->updatePassword->has('password')) is-invalid @endif" autocomplete="new-password">
              @if($errors->updatePassword->has('password'))<div class="invalid-feedback">{{ $errors->updatePassword->first('password') }}</div>@endif
            </div>

            <div class="mb-3">
              <label for="update_password_password_confirmation" class="form-label">Confirm Password</label>
              <input id="update_password_password_confirmation" name="password_confirmation" type="password" class="form-control @if($errors->updatePassword->has('password_confirmation')) is-invalid @endif" autocomplete="new-password">
              @if($errors->updatePassword->has('password_confirmation'))<div class="invalid-feedback">{{ $errors->updatePassword->first('password_confirmation') }}</div>@endif
            </div>

            <div class="d-flex align-items-center gap-2">
              <button class="btn btn-primary" type="submit">Save Password</button>
              @if (session('status') === 'password-updated')
                <span class="text-muted small">Saved.</span>
              @endif
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card border-danger">
        <div class="card-header fw-semibold text-danger">Danger Zone</div>
        <div class="card-body">
          <p class="text-muted mb-3">Deleting your account will permanently remove your data. This action cannot be undone.</p>
          <form method="post" action="{{ route('profile.destroy') }}" onsubmit="return confirm('Are you sure you want to delete your account?');" class="d-flex gap-2 align-items-center">
            @csrf
            @method('delete')
            <input type="password" name="password" class="form-control w-auto @if($errors->userDeletion->has('password')) is-invalid @endif" placeholder="Password" autocomplete="current-password">
            @if($errors->userDeletion->has('password'))<div class="invalid-feedback d-block">{{ $errors->userDeletion->first('password') }}</div>@endif
            <button type="submit" class="btn btn-outline-danger">Delete Account</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
