@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <strong>DB Schema Check</strong>
        </div>
        <div class="card-body">
            <div class="mb-3 text-muted">Driver: {{ $driver }}</div>
            @foreach($columns as $table => $cols)
                <div class="mb-4">
                    <div class="fw-semibold mb-2">{{ $table }}</div>
                    @if(!empty($errors[$table]))
                        <div class="text-danger">{{ $errors[$table] }}</div>
                    @elseif(empty($cols))
                        <div class="text-muted">No columns found.</div>
                    @else
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($cols as $col)
                                <span class="badge bg-secondary">{{ $col }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
