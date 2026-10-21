@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="page-title mb-0">Tableau de bord</h2>
                <p class="page-subtitle mb-0">Vue d'ensemble des commandes en temps réel</p>
            </div>
            <span class="badge bg-light text-muted border px-3 py-2" style="font-size:.8rem;">
                <span class="d-inline-block rounded-circle bg-success me-1" style="width:8px;height:8px;"></span>
                Mise à jour auto.
            </span>
        </div>
        
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">Commandes Récentes</h6>
                <small class="text-muted">{{ $orders->count() }} commande(s)</small>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>N° Commande</th>
                            <th>Client</th>
                            <th>Employé</th>
                            <th>Statut</th>
                            <th>Date</th>
                            <th>Caissier</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="ordersTableBody">
                        @include('admin.partials.orders_table', ['orders' => $orders])
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    setInterval(() => {
        fetch('{{ route("admin.orders.partial") }}')
            .then(response => response.text())
            .then(html => {
                document.getElementById('ordersTableBody').innerHTML = html;
            })
            .catch(error => console.error('Erreur:', error));
    }, 5000);
});
</script>
@endsection
