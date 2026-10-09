@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-12 col-md-8 mx-auto">
        <h2 class="mb-4">Add Worker</h2>
        
        <div class="card shadow-sm">
            <div class="card-body">
                <form action="{{ route('admin.workers.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                        @error('name')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" value="{{ old('username') }}" required>
                        @error('username')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required minlength="4">
                        @error('password')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary">Save Worker</button>
                    <a href="{{ route('admin.workers.index') }}" class="btn btn-secondary ms-2">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
