@extends('layouts.back')

@section('page-title', $pageTitle)

@section('content')
<div class="module">
    @if(session('success'))
        <div class="panel" style="margin-bottom:16px;padding:14px 18px;background:var(--green-50);border:1px solid var(--green);color:var(--green-dark)">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="panel" style="margin-bottom:16px;padding:14px 18px;background:#fff3e0;border:1px solid #ffb74d;color:#7a3e0c">
            <ul style="margin:0;padding-left:18px">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="module-toolbar">
        <p class="muted" style="margin:0">Les comptes atelier et association inscrits en ligne restent <b>en attente</b> jusqu'à ce que vous cochiez « Actif ». La fiche partenaire (visibilité citoyens) se valide ensuite dans Partenaires.</p>
    </div>

    <div class="panel" style="margin-bottom:24px;padding:20px">
        <h2 style="margin:0 0 16px;font-size:1.1rem">Ajouter un utilisateur</h2>
        <form method="post" action="{{ route('back.users.store') }}" class="auth-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;align-items:end">
            @csrf
            <div class="field">
                <label for="new_name">Nom</label>
                <input type="text" id="new_name" name="name" value="{{ old('name') }}" required>
            </div>
            <div class="field">
                <label for="new_email">E-mail</label>
                <input type="email" id="new_email" name="email" value="{{ old('email') }}" required>
            </div>
            <div class="field">
                <label for="new_password">Mot de passe</label>
                <input type="password" id="new_password" name="password" required minlength="8">
            </div>
            <div class="field">
                <label for="new_role">Rôle</label>
                <select id="new_role" name="role" required>
                    @foreach($roles as $role)
                        <option value="{{ $role }}" @selected(old('role') === $role)>{{ ucfirst($role) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="new_phone">Téléphone</label>
                <input type="text" id="new_phone" name="phone" value="{{ old('phone') }}">
            </div>
            <div class="field">
                <label><input type="checkbox" name="is_active" value="1" checked> Compte actif</label>
            </div>
            <button type="submit" class="btn btn-primary"><i data-lucide="user-plus"></i> Créer</button>
        </form>
    </div>

    <div class="panel table-panel">
        <table>
            <caption class="sr-only">Comptes utilisateurs</caption>
            <thead>
                <tr>
                    <th scope="col">Nom</th>
                    <th scope="col">E-mail</th>
                    <th scope="col">Rôle</th>
                    <th scope="col">Statut</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr>
                        <td><b>{{ $user->name }}</b></td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->roleLabel() }}</td>
                        <td>
                            <x-status-badge :tone="$user->isActive() ? 'green' : 'orange'">
                                @if($user->isPendingValidation())
                                    En attente
                                @elseif($user->isActive())
                                    Actif
                                @else
                                    Inactif
                                @endif
                            </x-status-badge>
                        </td>
                        <td>
                            <details>
                                <summary class="link-button" style="cursor:pointer">Modifier</summary>
                                <form method="post" action="{{ route('back.users.update', $user->getKey()) }}" style="margin-top:12px;display:grid;gap:8px;min-width:260px">
                                    @csrf
                                    @method('PUT')
                                    <input type="text" name="name" value="{{ $user->name }}" required autocomplete="name" aria-label="Nom de {{ $user->name }}">
                                    <input type="email" name="email" value="{{ $user->email }}" required autocomplete="email" aria-label="E-mail de {{ $user->name }}">
                                    <input type="password" name="password" placeholder="Nouveau mot de passe (optionnel)" minlength="8" autocomplete="new-password" aria-label="Nouveau mot de passe de {{ $user->name }}">
                                    <select name="role" required aria-label="Rôle de {{ $user->name }}">
                                        @foreach($roles as $role)
                                            <option value="{{ $role }}" @selected($user->role === $role)>{{ ucfirst($role) }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" name="phone" value="{{ $user->phone }}" placeholder="Téléphone" autocomplete="tel" aria-label="Téléphone de {{ $user->name }}">
                                    <label><input type="checkbox" name="is_active" value="1" @checked($user->isActive())> Actif (validation administrative)</label>
                                    <button type="submit" class="btn btn-secondary small">Enregistrer</button>
                                </form>
                                <form method="post" action="{{ route('back.users.destroy', $user->getKey()) }}" style="margin-top:8px" onsubmit="return confirm('Supprimer cet utilisateur ?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-secondary small" style="color:#c62828;border-color:#ef9a9a">Supprimer</button>
                                </form>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align:center;color:var(--muted);padding:40px">Aucun utilisateur — lancez <code>php artisan db:seed</code> ou créez un compte depuis l'inscription.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if($users->hasPages())
            <div style="padding:16px">{{ $users->links() }}</div>
        @endif
    </div>
</div>
@endsection
