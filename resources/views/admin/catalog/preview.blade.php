@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-12 mx-auto">
        <h2 class="mb-4">Preview Catalog Import</h2>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h4 class="card-title">Valid Rows</h4>
                        <h2>{{ count($validRows) }}</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h4 class="card-title">Invalid / Skipped Rows</h4>
                        <h2>{{ count($invalidRows) }}</h2>
                    </div>
                </div>
            </div>
        </div>

        @if(count($invalidRows) > 0)
        <div class="card shadow-sm mb-4 border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">Invalid Rows Detail</h5>
            </div>
            <div class="card-body p-0" style="max-height: 300px; overflow-y: auto;">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Designation</th>
                            <th>Qtu</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invalidRows as $row)
                        <tr>
                            <td>{{ $row['designation'] }}</td>
                            <td>{{ $row['qtu'] }}</td>
                            <td class="text-danger">{{ $row['reason'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        <div class="card shadow-sm mb-4 border-warning">
            <div class="card-body text-center">
                <h4 class="text-warning">Warning: Destructive Action</h4>
                <p>Importing this catalog will <strong>delete all current products</strong> and <strong>clear all existing orders</strong>.</p>
                <form action="{{ route('admin.catalog.import') }}" method="POST">
                    @csrf
                    <input type="hidden" name="import_id" value="{{ $importId }}">
                    <a href="{{ route('admin.catalog.index') }}" class="btn btn-secondary me-3">Cancel</a>
                    <button type="submit" class="btn btn-danger btn-lg" @if(count($validRows) === 0) disabled @endif>
                        Confirm & Replace Catalog
                    </button>
                </form>
            </div>
        </div>
        
    </div>
</div>
@endsection
