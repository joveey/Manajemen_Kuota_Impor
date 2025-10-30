@extends('errors.layout')

@section('title', 'Access Denied')

@section('content')
    <div class="empty-state">
        <div class="empty-state__icon">
            <i class="fas fa-ban"></i>
        </div>
        <h1 class="empty-state__title">Access Denied</h1>
        <p class="empty-state__subtitle">You do not have permission to access this page.</p>
        <div class="empty-state__actions">
            <a href="{{ url()->previous() }}" class="btn btn-outline-primary">Back</a>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-primary">Go to Dashboard</a>
        </div>
    </div>
@endsection
