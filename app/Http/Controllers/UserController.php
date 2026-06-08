<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    private static array $roleOptions = [
        ['value' => 'admin',         'label' => 'Administrator',   'color' => '#E0923A'],
        ['value' => 'admisi',        'label' => 'Petugas Admisi',  'color' => '#4A90D9'],
        ['value' => 'petugas_icu',   'label' => 'Petugas ICU',     'color' => '#2DD9A4'],
        ['value' => 'petugas_ruang', 'label' => 'Petugas Ruang',   'color' => '#D9517A'],
    ];

    public function index(): Response
    {
        $users = User::orderBy('role')->orderBy('name')->get()
            ->map(fn($u) => [
                'id'         => $u->id,
                'name'       => $u->name,
                'username'   => $u->username,
                'email'      => $u->email,
                'role'       => $u->role,
                'role_label' => $u->roleLabel(),
                'role_color' => $u->roleColor(),
                'unit_kerja' => $u->unit_kerja,
                'is_active'  => $u->is_active,
                'created_at' => $u->created_at?->format('d/m/Y'),
            ]);

        return Inertia::render('Settings/Users', [
            'users'   => $users,
            'roles'   => self::$roleOptions,
            'flash'   => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:100',
            'username'   => 'required|string|max:50|unique:users,username|alpha_dash',
            'email'      => 'nullable|email|unique:users,email',
            'password'   => 'required|string|min:6',
            'role'       => 'required|in:admin,admisi,petugas_icu,petugas_ruang',
            'unit_kerja' => 'nullable|string|max:100',
        ]);

        User::create([
            'name'       => $validated['name'],
            'username'   => $validated['username'],
            'email'      => $validated['email'] ?? null,
            'password'   => Hash::make($validated['password']),
            'role'       => $validated['role'],
            'unit_kerja' => $validated['unit_kerja'] ?? null,
            'is_active'  => true,
        ]);

        return back()->with('success', "User {$validated['name']} (username: {$validated['username']}) berhasil ditambahkan.");
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name'       => 'required|string|max:100',
            'role'       => 'required|in:admin,admisi,petugas_icu,petugas_ruang',
            'unit_kerja' => 'nullable|string|max:100',
            'is_active'  => 'required|boolean',
        ]);

        $user->update($validated);

        return back()->with('success', "User {$user->name} berhasil diperbarui.");
    }

    public function resetPassword(Request $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'password' => 'required|string|min:6',
        ]);

        $user->update(['password' => Hash::make($validated['password'])]);

        return back()->with('success', "Password {$user->name} berhasil direset.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak dapat menghapus akun sendiri.');
        }

        $nama = $user->name;
        $user->delete();

        return back()->with('success', "User {$nama} berhasil dihapus.");
    }
}
