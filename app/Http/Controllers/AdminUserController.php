<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\AccountStarter;
use App\Support\Ownership;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function index(): Response
    {
        $people = User::query()
            ->when(Ownership::hasRole(), fn ($query) => $query->orderByDesc('role'))
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->isSuperAdmin()
                    ? User::ROLE_SUPERADMIN
                    : ($user->isAdmin() ? User::ROLE_ADMIN : User::ROLE_MEMBER),
                'is_active' => $user->isActive(),
                'living_plan' => (float) ($user->living_plan ?: 17000),
                'created_at' => $user->created_at?->toDateString(),
            ]);

        return Inertia::render('Admin/Users', [
            'people' => $people,
        ]);
    }

    public function store(StoreUserRequest $request, AccountStarter $starter): RedirectResponse
    {
        $data = $request->validated();

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ];

        if (Ownership::hasRole()) {
            $payload['role'] = $data['role'];
            $payload['is_active'] = true;
        }

        if (Ownership::hasLivingPlan()) {
            $payload['living_plan'] = $data['living_plan'] ?? 17000;
        }

        $user = User::query()->create($payload);

        $starter->provision($user);

        return back()->with('success', 'Account created. They can sign in now.');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        if ($user->isSuperAdmin()) {
            $data['role'] = User::ROLE_SUPERADMIN;
            $data['is_active'] = true;
        }

        if ($user->id === $request->user()->id && ! in_array($data['role'], [User::ROLE_ADMIN, User::ROLE_SUPERADMIN], true)) {
            return back()->withErrors(['role' => 'You cannot remove admin from your own account.']);
        }

        if ($user->id === $request->user()->id && ! $data['is_active']) {
            return back()->withErrors(['is_active' => 'You cannot turn off your own account.']);
        }

        if ($user->isAdmin() && ! in_array($data['role'], [User::ROLE_ADMIN, User::ROLE_SUPERADMIN], true) && $this->adminCount() <= 1) {
            return back()->withErrors(['role' => 'Keep at least one admin.']);
        }

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
        ];

        if (Ownership::hasRole()) {
            $payload['role'] = $data['role'];
            $payload['is_active'] = $data['is_active'];
        }

        if (Ownership::hasLivingPlan()) {
            $payload['living_plan'] = $data['living_plan'] ?? $user->living_plan;
        }

        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
        }

        $user->update($payload);

        return back()->with('success', 'Account updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_if($user->isSuperAdmin(), 403, 'The superadmin cannot be deleted.');
        abort_if($user->id === request()->user()->id, 403);

        if ($user->isAdmin() && $this->adminCount() <= 1) {
            return back()->withErrors(['email' => 'Keep at least one admin.']);
        }

        abort_unless($user->delete(), 403, 'The superadmin cannot be deleted.');

        return back()->with('success', 'Account removed.');
    }

    private function adminCount(): int
    {
        if (! Ownership::hasRole()) {
            return User::query()->count();
        }

        return User::query()
            ->where(function ($query): void {
                $query->whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPERADMIN])
                    ->orWhere('email', User::OWNER_EMAIL);
            })
            ->count();
    }
}
