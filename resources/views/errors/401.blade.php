@extends('layouts.admin')

@section('title', 'Not Allowed')

@section('content')
<div class="page-shell">
  <div class="page-header">
    <h1 class="page-header__title">Not Allowed</h1>
  </div>

  <div class="d-flex align-items-center justify-content-center" style="min-height: 50vh;">
    <div class="card shadow-sm" style="max-width:560px; width:100%;">
      <div class="card-body text-center p-4 p-md-5">
        <div class="mb-3" style="display:inline-flex; width:64px; height:64px; border-radius:16px; align-items:center; justify-content:center; background:rgba(245,158,11,.12); color:#b45309; font-size:24px;">
          <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3 class="mb-2">Not Allowed</h3>
        <p class="text-muted mb-4">Your session may have expired or you are not authenticated.</p>
        <div class="d-inline-flex gap-2">
          <a href="{{ route('login') }}" class="btn btn-primary">Sign In Again</a>
          <a href="{{ url()->previous() }}" class="btn btn-outline-primary">Back</a>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
