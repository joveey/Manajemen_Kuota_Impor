@extends('layouts.admin')

@section('title', 'Access Denied')

@section('content')
<div class="page-shell">
  <div class="page-header">
    <h1 class="page-header__title">Access Denied</h1>
  </div>

  <div class="d-flex align-items-center justify-content-center" style="min-height: 50vh;">
    <div class="card shadow-sm" style="max-width:560px; width:100%;">
      <div class="card-body text-center p-4 p-md-5">
        <div class="mb-3" style="display:inline-flex; width:64px; height:64px; border-radius:16px; align-items:center; justify-content:center; background:rgba(37,99,235,.12); color:#2563eb; font-size:24px;">
          <i class="fas fa-ban"></i>
        </div>
        <h3 class="mb-2">Access Denied</h3>
        <p class="text-muted mb-4">You do not have permission to access this page.</p>
        <div class="d-inline-flex gap-2">
          <a href="{{ url()->previous() }}" class="btn btn-outline-primary">Back</a>
          <a href="{{ route('admin.dashboard') }}" class="btn btn-primary">Go to Dashboard</a>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
