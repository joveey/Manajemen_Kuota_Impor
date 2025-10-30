@extends('errors.layout')

@section('title', 'Not Allowed')

@section('content')
    <div class="empty-state">
        <div class="empty-state__icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h1 class="empty-state__title">Not Allowed</h1>
        <p class="empty-state__subtitle">Your session may have expired or you are not authenticated.</p>
        <div class="empty-state__actions">
            <a href="{{ route('login') }}" class="btn btn-primary">Sign In Again</a>
            <a href="{{ url()->previous() }}" class="btn btn-outline-primary">Back</a>
        </div>
    </div>
@endsection
