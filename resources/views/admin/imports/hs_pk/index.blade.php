@extends('layouts.admin')

@section('content')
<div class="container-fluid">
  <div class="row">
    <div class="col-md-6">
      <div class="card">
        <div class="card-header">Upload HS→PK</div>
        <div class="card-body">
          @if ($errors->any())
            <div class="alert alert-danger">
              <ul class="mb-0">
                @foreach ($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif
          @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
          @endif
          <form action="{{ route('admin.imports.hs_pk.upload.form') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="mb-3">
              <label class="form-label">Periode</label>
              <input type="text" name="period_key" class="form-control" placeholder="YYYY atau YYYY-MM" value="{{ old('period_key') }}" required>
            </div>
            <div class="mb-3">
              <label class="form-label">File Excel (sheet: HS code master)</label>
              <input type="file" name="file" class="form-control" accept=".xlsx,.xls" required>
            </div>
            <button class="btn btn-primary" type="submit">Upload</button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card">
        <div class="card-header">Recent Imports (HS→PK)</div>
        <div class="card-body p-0">
          <table class="table mb-0">
            <thead>
              <tr>
                <th>ID</th>
                <th>Period</th>
                <th>Status</th>
                <th>Counts</th>
                <th>Created</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              @forelse($recent as $imp)
                <tr>
                  <td>{{ $imp->id }}</td>
                  <td>{{ $imp->period_key }}</td>
                  <td>{{ $imp->status }}</td>
                  <td>{{ (int)($imp->valid_rows ?? 0) }} / {{ (int)($imp->total_rows ?? 0) }} (err {{ (int)($imp->error_rows ?? 0) }})</td>
                  <td>{{ optional($imp->created_at)->format('Y-m-d H:i') }}</td>
                  <td>
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.imports.hs_pk.preview', $imp) }}">Preview</a>
                  </td>
                </tr>
              @empty
                <tr><td colspan="6" class="text-center text-muted">No data</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

