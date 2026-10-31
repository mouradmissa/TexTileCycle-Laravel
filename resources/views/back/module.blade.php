@extends('layouts.back')

@section('page-title', $pageTitle)

@section('content')
<div class="module">
    <div class="module-toolbar">
        <div class="filter-row">
            <button type="button" class="select-btn" disabled>Tous les statuts <i data-lucide="chevron-down" aria-hidden="true"></i></button>
            <button type="button" class="select-btn" disabled>Ce mois-ci <i data-lucide="chevron-down" aria-hidden="true"></i></button>
        </div>
        <button type="button" class="btn btn-primary" disabled><i data-lucide="plus" aria-hidden="true"></i> Ajouter</button>
    </div>

    <div class="panel table-panel">
        <table>
            <caption class="sr-only">Éléments du module</caption>
            <thead>
                <tr>
                    <th scope="col">Élément</th>
                    <th scope="col">Statut</th>
                    <th scope="col">Date</th>
                    <th scope="col">Propriétaire</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows ?? [] as $row)
                    <tr>
                        <td>
                            <div class="table-item">
                                <div class="table-thumb"><i data-lucide="shirt"></i></div>
                                <b>{{ $row['name'] }}</b>
                            </div>
                        </td>
                        <td><x-status-badge :tone="$row['tone']">{{ $row['status'] }}</x-status-badge></td>
                        <td>{{ $row['date'] }}</td>
                        <td>{{ $row['owner'] }}</td>
                        <td><button type="button" class="icon-btn" aria-label="Actions"><i data-lucide="more-horizontal"></i></button></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align:center;color:var(--muted);padding:40px">Aucun vêtement enregistré pour le moment.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
