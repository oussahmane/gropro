@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-12 col-lg-8 mx-auto">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Workers</h2>
            <a href="{{ route('admin.workers.create') }}" class="btn btn-primary">Add Worker</a>
        </div>
        
        <div class="card shadow-sm">
            <div class="card-body p-0 table-responsive">
                <table class="table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($workers as $worker)
                        <tr>
                            <td>{{ $worker->name }}</td>
                            <td>{{ $worker->username }}</td>
                            <td>
                                @if($worker->active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-danger">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.workers.edit', $worker) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center py-3">No workers found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
