@forelse($orders as $order)
<tr>
    <td>{{ $order->order_number }}</td>
    <td>{{ $order->customer_name }}</td>
    <td>{{ $order->worker->name }}</td>
    <td>
        @php
            $statusLabels = [
                'received' => 'Reçue',
                'processing' => 'En cours',
                'completed' => 'Terminée',
                'draft' => 'Brouillon',
                'cancelled' => 'Annulée'
            ];
            $statusLabel = $statusLabels[$order->status] ?? ucfirst($order->status);
        @endphp
        <span class="badge 
            @if($order->status == 'received') bg-secondary 
            @elseif($order->status == 'processing') bg-warning text-dark
            @elseif($order->status == 'completed') bg-success 
            @elseif($order->status == 'draft') bg-info text-dark
            @else bg-danger @endif">
            {{ $statusLabel }}
        </span>
    </td>
    <td>{{ $order->created_at->format('d/m/Y H:i') }}</td>
    <td>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-sm btn-primary">Voir</a>
            <form action="{{ route('admin.orders.destroy', $order) }}" method="POST" onsubmit="return confirm('Supprimer cette commande ?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-danger">Supprimer</button>
            </form>
        </div>
    </td>
</tr>
@empty
<tr>
    <td colspan="6" class="text-center py-4">Aucune commande trouvée.</td>
</tr>
@endforelse
