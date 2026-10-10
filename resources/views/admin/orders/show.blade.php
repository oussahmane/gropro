@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-12 col-lg-8 mx-auto">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Commande {{ $order->order_number }}</h2>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">Retour au tableau de bord</a>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-sm-4 text-muted">Nom du client</div>
                    <div class="col-sm-8 fw-bold">{{ $order->customer_name }}</div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-4 text-muted">Employé</div>
                    <div class="col-sm-8 fw-bold">{{ $order->worker->name }}</div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-4 text-muted">Date de soumission</div>
                    <div class="col-sm-8 fw-bold">{{ $order->created_at->format('d/m/Y H:i:s') }}</div>
                </div>
                @if($order->notes)
                <div class="row mb-3">
                    <div class="col-sm-4 text-muted">Notes</div>
                    <div class="col-sm-8 text-break">{{ $order->notes }}</div>
                </div>
                @endif
                <div class="row mb-3 align-items-center">
                    <div class="col-sm-4 text-muted">Statut</div>
                    <div class="col-sm-8 d-flex justify-content-between align-items-center">
                        <form action="{{ route('admin.orders.update-status', $order) }}" method="POST" class="d-flex align-items-center mb-0">
                            @csrf
                            <select name="status" class="form-select w-auto me-2">
                                @if($order->status == 'draft')
                                    <option value="draft" selected>Brouillon (Création...)</option>
                                @endif
                                <option value="received" @if($order->status == 'received') selected @endif>Reçue</option>
                                <option value="processing" @if($order->status == 'processing') selected @endif>En cours</option>
                                <option value="completed" @if($order->status == 'completed') selected @endif>Terminée</option>
                                <option value="cancelled" @if($order->status == 'cancelled') selected @endif>Annulée</option>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm">Mettre à jour</button>
                        </form>

                        <form action="{{ route('admin.orders.destroy', $order) }}" method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer définitivement cette commande ?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger btn-sm">Supprimer la commande</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">Articles de la commande</h5>
                <span id="order-items-count" class="badge bg-primary fs-6 px-3 py-2" role="status">
                    {{ $order->items->count() }} produit(s) dans cette commande
                </span>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Produit</th>
                            <th class="text-center">Qté/Caisse</th>
                            <th class="text-center">Caisse</th>
                            <th class="text-center">Unités</th>
                            <th class="text-end">Total Unités Individuelles</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($order->items as $item)
                        <tr>
                            <td>{{ $item->product_designation }}</td>
                            <td class="text-center text-muted">{{ $item->qtu }}</td>
                            <td class="text-center fw-bold">{{ $item->box_quantity }}</td>
                            <td class="text-center fw-bold">{{ $item->units_quantity }}</td>
                            <td class="text-end fw-bold text-primary">
                                {{ $item->box_quantity * $item->units_quantity }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">Aucun article pour le moment.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
@if($order->status == 'draft')
<script>
    setTimeout(() => {
        window.location.reload();
    }, 5000);
</script>
@endif
@endsection
