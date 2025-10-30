@extends('errors.layout')

@section('title', 'Page Not Found')

@section('content')
    <div class="empty-state">
        <div class="empty-state__icon">
            <i class="fas fa-compass"></i>
        </div>
        <h1 class="empty-state__title">Page Not Found</h1>
        <p class="empty-state__subtitle">We could not find the page you are looking for.</p>
        <div class="empty-state__actions">
            <a href="{{ url()->previous() }}" class="btn btn-outline-primary">Back</a>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-primary">Go to Dashboard</a>
        </div>
    </div>
@endsection
