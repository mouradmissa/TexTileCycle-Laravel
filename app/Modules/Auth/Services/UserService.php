<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return User::query()
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function create(array $data): User
    {
        $role = $data['role'] ?? User::ROLE_CITOYEN;

        return User::create([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'password' => $data['password'],
            'role' => $role,
            'phone' => $data['phone'] ?? null,
            'is_active' => array_key_exists('is_active', $data)
                ? (bool) $data['is_active']
                : User::isActiveOnRegistration($role),
        ]);
    }

    public function update(User $user, array $data): User
    {
        $user->name = $data['name'];
        $user->email = mb_strtolower($data['email']);
        $user->role = $data['role'];
        $user->phone = $data['phone'] ?? null;
        $user->is_active = (bool) ($data['is_active'] ?? true);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();

        return $user;
    }

    /**
     * Le citoyen met à jour son nom, son e-mail, son téléphone et, s'il le souhaite, son mot de passe.
     */
    public function mettreAJourProfil(User $user, array $data): User
    {
        $user->name = $data['name'];
        $user->email = mb_strtolower($data['email']);
        $user->phone = $data['phone'];

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();

        return $user;
    }

    public function delete(User $user): void
    {
        $user->delete();
    }

    /**
     * Compte dont le mot de passe est correct, y compris s'il est encore inactif.
     */
    public function findAuthenticatable(string $email, string $password): ?User
    {
        $user = User::where('email', mb_strtolower($email))->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return null;
        }

        return $user;
    }

    public function verifyCredentials(string $email, string $password): ?User
    {
        $user = $this->findAuthenticatable($email, $password);

        if (! $user || ! $user->isActive()) {
            return null;
        }

        return $user;
    }

    /**
     * Aligne le compte propriétaire sur le statut partenaire (actif / en attente / suspendu).
     */
    public function syncActiveFromPartnerStatut(?string $userId, string $statut): void
    {
        if ($userId === null || $userId === '') {
            return;
        }

        $user = User::find($userId);

        if (! $user || $user->isAdmin()) {
            return;
        }

        $user->is_active = $statut === 'actif';
        $user->save();
    }
}
