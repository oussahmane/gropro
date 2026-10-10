@extends('layouts.app')

@section('content')
<div class="row" id="app-order">
    <div class="col-12 col-xl-10 mx-auto">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h2 class="mb-0">Créer une nouvelle commande</h2>
            <small class="text-muted">{{ $productsCount }} produit(s) dans le catalogue</small>
        </div>

        <form id="orderForm">
            @csrf
            <div class="card shadow-sm mb-4">
                <div class="card-body p-4">
                    <label class="form-label fs-4">Nom du client</label>
                    <input type="text" name="customer_name" class="form-control form-control-lg touch-input" value="{{ old('customer_name', $order->customer_name) }}" required placeholder="Entrez le nom du client">
                </div>
            </div>
            
            <div class="card shadow-sm mb-4">
                <div class="card-body p-4">
                    <label class="form-label fs-4">Notes de commande (facultatif)</label>
                    <textarea name="notes" class="form-control form-control-lg touch-input" rows="2" placeholder="Observations éventuelles sur cette commande...">{{ old('notes', $order->notes) }}</textarea>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white p-4">
                    <label class="form-label fs-4 mb-2">Rechercher un produit</label>
                    <div class="d-flex gap-2 position-relative">
                        <input type="text" id="searchInput" class="form-control form-control-lg touch-input" placeholder="Tapez pour rechercher des produits...">
                        <button type="button" id="addCustomProductBtn" class="btn btn-outline-primary fw-bold" style="white-space: nowrap;">+ Personnalisé</button>
                    </div>
                    <div id="searchResults" class="list-group mt-2" style="display:none; max-height:300px; overflow-y:auto; position:absolute; z-index:1000; width:95%; box-shadow: 0 4px 6px rgba(0,0,0,0.1);"></div>
                </div>
                
                <div class="card-body p-3 p-md-4">
                    <div id="itemsList">
                        <!-- Les articles seront injectés ici via JS -->
                        <div id="emptyRow" class="text-center p-5 text-muted fs-5">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">🛒</div>
                            Aucun produit ajouté pour le moment.
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-grid mb-5">
                <button type="button" id="submitBtn" class="btn btn-success btn-lg touch-btn py-3 fs-3 fw-bold shadow">Soumettre la commande</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('styles')
<style>
    .touch-input { font-size: 1.2rem; padding: 15px; border-radius: 10px; }
    .qty-input { font-size: 1.5rem; text-align: center; border-radius: 8px; width: 100%; height: 50px; }
    .search-item { padding: 15px; font-size: 1.2rem; cursor: pointer; }
    .search-item:hover { background-color: #f8f9fa; }
    .item-card { border: 1px solid #e9ecef; border-radius: 12px; margin-bottom: 1rem; padding: 1rem; background: #fafafa; }
    .qty-label { font-weight: bold; color: #6c757d; margin-bottom: 0.5rem; display: block; }
</style>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const searchResults = document.getElementById('searchResults');
    const itemsList = document.getElementById('itemsList');
    const emptyRow = document.getElementById('emptyRow');
    const submitBtn = document.getElementById('submitBtn');
    const orderForm = document.getElementById('orderForm');
    const customerNameInput = document.querySelector('input[name="customer_name"]');
    const notesInput = document.querySelector('textarea[name="notes"]');
    const addCustomProductBtn = document.getElementById('addCustomProductBtn');
    
    @php
        $mappedItems = $order->items->map(function($item) {
            return [
                'id' => $item->product_id,
                'designation' => $item->product_designation,
                'qtu' => $item->qtu,
                'box' => $item->box_quantity,
                'units' => $item->units_quantity
            ];
        });
    @endphp
    let items = @json($mappedItems);
    let searchTimeout;
    let saveTimeout;

    function autoSave() {
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(() => {
            const formData = new FormData(orderForm);
            fetch(`{{ route('worker.order.update', $order) }}`, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).catch(console.error);
        }, 1000);
    }

    customerNameInput.addEventListener('input', autoSave);
    notesInput.addEventListener('input', autoSave);

    addCustomProductBtn.addEventListener('click', function() {
        const name = prompt("Saisissez un nom de produit personnalisé :");
        if (!name || name.trim() === '') return;
        
        let qtu = prompt("Saisissez le nombre d'unités par boîte (QTU) :", "1");
        qtu = parseInt(qtu);
        if (isNaN(qtu) || qtu < 1) qtu = 1;

        items.push({
            id: null,
            designation: name.trim(),
            qtu: qtu,
            box: 1,
            units: qtu
        });
        
        renderItems();
        autoSave();
    });

    // Search functionality
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        if (query.length < 2) {
            searchResults.style.display = 'none';
            return;
        }

        searchTimeout = setTimeout(() => {
            fetch(`{{ route('worker.products.search') }}?q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    searchResults.innerHTML = '';
                    if (data.length === 0) {
                        searchResults.innerHTML = '<div class="list-group-item text-muted p-3">Aucun produit trouvé</div>';
                    } else {
                        data.forEach(product => {
                            const div = document.createElement('div');
                            div.className = 'list-group-item list-group-item-action search-item d-flex justify-content-between align-items-center';
                            div.innerHTML = `
                                <div><strong style="font-size: 1.1rem;">${product.designation}</strong></div>
                                <span class="badge bg-primary rounded-pill fs-6">${product.qtu} / boîte</span>
                            `;
                            div.addEventListener('click', () => addProduct(product));
                            searchResults.appendChild(div);
                        });
                    }
                    searchResults.style.display = 'block';
                });
        }, 300);
    });

    // Close search results when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target !== searchInput && !searchResults.contains(e.target)) {
            searchResults.style.display = 'none';
        }
    });

    function addProduct(product) {
        // Hide search
        searchResults.style.display = 'none';
        searchInput.value = '';
        
        items.push({
            id: product.id,
            designation: product.designation,
            qtu: product.qtu,
            box: 1,
            units: product.qtu
        });
        
        renderItems();
        autoSave();
    }

    function renderItems() {
        if (items.length === 0) {
            emptyRow.style.display = 'block';
        } else {
            emptyRow.style.display = 'none';
        }

        // Remove old dynamic rows
        document.querySelectorAll('.item-row').forEach(row => row.remove());

        items.forEach((item, index) => {
            const div = document.createElement('div');
            div.className = 'item-row item-card shadow-sm';
            div.innerHTML = `
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        ${item.id ? `<input type="hidden" name="items[${index}][product_id]" value="${item.id}">` : ''}
                        <input type="hidden" name="items[${index}][designation]" value="${item.designation.replace(/"/g, '&quot;')}">
                        <input type="hidden" name="items[${index}][qtu]" value="${item.qtu}">
                        <div class="fs-4 fw-bold text-dark">${item.designation} ${!item.id ? '<span class="badge bg-warning text-dark ms-2 fs-6">Personnalisé</span>' : ''}</div>
                        <div class="badge bg-secondary mt-1 fs-6">${item.qtu} unités par boîte</div>
                    </div>
                    <button type="button" class="btn btn-outline-danger btn-lg remove-btn" data-index="${index}">&times;</button>
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="qty-label fs-5">Boîtes</label>
                        <input type="number" name="items[${index}][box]" class="form-control qty-input box-input border-primary" value="${item.box}" min="0" data-index="${index}">
                    </div>
                    <div class="col-6">
                        <label class="qty-label fs-5">Unités</label>
                        <input type="number" name="items[${index}][units]" class="form-control qty-input units-input border-primary" value="${item.units}" min="0" data-index="${index}">
                    </div>
                </div>
            `;
            itemsList.appendChild(div);
        });

        // Attach listeners
        document.querySelectorAll('.remove-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const idx = parseInt(this.getAttribute('data-index'));
                items.splice(idx, 1);
                renderItems();
                autoSave();
            });
        });
        
        document.querySelectorAll('.box-input').forEach(input => {
            input.addEventListener('change', function() {
                const idx = parseInt(this.getAttribute('data-index'));
                items[idx].box = parseInt(this.value) || 0;
                autoSave();
            });
        });
        
        document.querySelectorAll('.units-input').forEach(input => {
            input.addEventListener('change', function() {
                const idx = parseInt(this.getAttribute('data-index'));
                items[idx].units = parseInt(this.value) || 0;
                autoSave();
            });
        });
    }

    submitBtn.addEventListener('click', function() {
        if (items.length === 0) {
            alert('Veuillez ajouter au moins un produit à la commande.');
            return;
        }
        
        const customerName = document.querySelector('input[name="customer_name"]').value.trim();
        if (!customerName) {
            alert('Veuillez saisir le nom du client.');
            document.querySelector('input[name="customer_name"]').focus();
            return;
        }

        // Validate quantities
        let hasZero = false;
        items.forEach(item => {
            if (item.box == 0 && item.units == 0) {
                hasZero = true;
            }
        });
        
        if (hasZero) {
            alert('La quantité ne peut pas être nulle. Les boîtes et les unités ne peuvent pas être à 0.');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Envoi en cours...';
        
        // Final submit
        const formData = new FormData(orderForm);
        fetch(`{{ route('worker.order.submit', $order) }}`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        }).then(res => res.json()).then(data => {
            if (data.success) {
                window.location.href = data.redirect;
            } else {
                alert('Une erreur est survenue.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Soumettre la commande';
            }
        }).catch(err => {
            console.error(err);
            alert('Une erreur est survenue.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Soumettre la commande';
        });
    });

    renderItems(); // Render initial items
});
</script>
@endsection