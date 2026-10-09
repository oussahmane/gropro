@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-12 col-md-8 mx-auto">
        <h2 class="mb-4">Catalogue des Produits</h2>

        <div class="card shadow-sm mb-4 border-warning">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">Importer le Catalogue Quotidien</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    Téléchargez le fichier d'export quotidien depuis Progilic (format CSV/TSV ou Texte). <br>
                    <strong>Remarque :</strong> Le remplacement du catalogue effacera toutes les commandes existantes et les articles de commande.
                </p>
                <form action="{{ route('admin.catalog.preview') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label for="catalog_file" class="form-label">Sélectionner un fichier</label>
                        <input class="form-control" type="file" id="catalog_file" name="catalog_file" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Prévisualiser & Valider</button>
                </form>
            </div>
        </div>
        
        <div class="card shadow-sm">
            <div class="card-body">
                <p class="mb-0">Nombre total actuel de produits dans la base de données : <strong>{{ \App\Models\Product::count() }}</strong></p>
            </div>
        </div>
    </div>
</div>
@endsection
