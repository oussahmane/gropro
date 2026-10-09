@extends('layouts.app')

@section('styles')
<style>
    body { background: linear-gradient(135deg, var(--gp-primary) 0%, var(--gp-primary-light) 50%, #4a90d9 100%); min-height: 100vh; }
    .gp-navbar { display: none; }
    .login-card { border: none; border-radius: 16px; box-shadow: var(--gp-shadow-lg); max-width: 420px; }
    .login-logo { width: 56px; height: 56px; background: linear-gradient(135deg, var(--gp-accent), var(--gp-primary-light)); border-radius: 14px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; }
</style>
@endsection

@section('content')
<div class="row justify-content-center align-items-center" style="min-height: 80vh;">
    <div class="col-11 col-sm-8 col-md-6 col-lg-4">
        <div class="card login-card mx-auto">
            <div class="card-body p-4 p-md-5">
                <div class="login-logo">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
                </div>
                <h3 class="text-center fw-bold mb-1">{{ config('app.name', 'GroPro') }}</h3>
                <p class="text-center text-muted mb-4">Connectez-vous à votre compte</p>
                <form method="POST" action="{{ route('login') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="username" class="form-label fw-medium">Nom d'utilisateur</label>
                        <input type="text" class="form-control form-control-lg @error('username') is-invalid @enderror" id="username" name="username" value="{{ old('username') }}" required autofocus placeholder="Entrez votre identifiant">
                        @error('username')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label fw-medium">Mot de passe</label>
                        <input type="password" class="form-control form-control-lg" id="password" name="password" required placeholder="••••••••">
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg fw-semibold">Se connecter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
