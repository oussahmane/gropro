@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-12 col-md-8 mx-auto">
        <h2 class="mb-4">Edit Worker</h2>
        
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form action="{{ route('admin.workers.update', $worker) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $worker->name) }}" required>
                        @error('name')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" value="{{ old('username', $worker->username) }}" required>
                        @error('username')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password <small class="text-muted">(leave blank to keep current)</small></label>
                        <input type="password" name="password" class="form-control" minlength="4">
                        @error('password')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-4 form-check">
                        <input type="checkbox" name="active" class="form-check-input" id="activeCheck" value="1" {{ $worker->active ? 'checked' : '' }}>
                        <label class="form-check-label" for="activeCheck">Active Account</label>
                    </div>
                    <button type="submit" class="btn btn-primary">Update Worker</button>
                    <a href="{{ route('admin.workers.index') }}" class="btn btn-secondary ms-2">Cancel</a>
                </form>
            </div>
        </div>
        
        <div class="card shadow-sm border-danger">
            <div class="card-header bg-danger text-white">Danger Zone</div>
            <div class="card-body">
                <form action="{{ route('admin.workers.destroy', $worker) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this worker?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger">Delete Worker</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
