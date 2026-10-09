@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-12 col-md-10 mx-auto">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="page-title mb-0">Mes Commandes</h2>
                <p class="page-subtitle mb-0">Gérez et suivez vos commandes</p>
            </div>
            <a href="{{ route('worker.order.create') }}" class="btn btn-primary touch-btn d-flex align-items-center gap-2">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Nouvelle Commande
            </a>
        </div>
        
        <div class="row">
            @forelse($orders as $order)
                @php
                    $statusLabels = ['received' => 'Reçue', 'processing' => 'En cours', 'completed' => 'Terminée', 'draft' => 'Brouillon', 'cancelled' => 'Annulée'];
                    $statusClasses = ['received' => 'badge-received', 'processing' => 'badge-processing', 'completed' => 'badge-completed', 'draft' => 'badge-draft', 'cancelled' => 'badge-cancelled'];
                @endphp
                <div class="col-md-6 col-xl-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h5 class="fw-bold mb-0">{{ $order->customer_name ?: '(Sans nom)' }}</h5>
                                <span class="badge {{ $statusClasses[$order->status] ?? 'badge-received' }}">{{ $statusLabels[$order->status] ?? ucfirst($order->status) }}</span>
                            </div>
                            <div class="d-flex flex-column gap-1 mb-3">
                                <small class="text-muted">N° {{ $order->order_number }}</small>
                                <small class="text-muted">{{ $order->created_at->format('d/m/Y à H:i') }}</small>
                            </div>
                            @if($order->status !== 'completed' && $order->status !== 'cancelled')
                                <a href="{{ route('worker.order.edit', $order) }}" class="btn btn-sm btn-outline-primary fw-medium">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="me-1"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    Modifier
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5" class="mb-3"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
                            <h5 class="text-muted fw-medium">Vous n'avez pas encore de commandes</h5>
                            <p class="text-muted mb-3">Commencez par créer votre première commande</p>
                            <a href="{{ route('worker.order.create') }}" class="btn btn-primary">Créer une commande</a>
                        </div>
                    </div>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
