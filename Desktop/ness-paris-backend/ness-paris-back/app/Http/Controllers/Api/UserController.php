<?php

// =============================================================================
// app/Http/Controllers/Api/UserController.php
//
// RESPONSABILITÉ :
//   Gestion des utilisateurs internes (admin + employee) via le backoffice.
//
// ROUTES :
//   GET    /api/user                → profil du user connecté (show)
//   POST   /api/user/update-password → changer son mot de passe (updatePassword)
//   GET    /api/admin/users         → liste tous les users (admin only)
//   POST   /api/admin/users         → crée un user interne (admin only)
//   DELETE /api/admin/users/{user}  → supprime un user (admin only)
//
// PROTÉGÉ PAR :
//   auth:sanctum + backoffice.portal pour les routes user
//   auth:sanctum + admin pour les routes admin
// =============================================================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    // =========================================================================
    // PROFIL
    // =========================================================================

    /**
     * Retourne le profil du user connecté.
     * Appelé par GET /api/user (protégé auth:sanctum + backoffice.portal)
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'phone'      => $user->phone,
            'role'       => $user->role,
            'is_admin'   => $user->is_admin,
            'is_active'  => $user->is_active,
            'last_login' => $user->last_login_at,
        ]);
    }

    /**
     * Change le mot de passe du user connecté.
     * Appelé par POST /api/user/update-password
     *
     * VÉRIFIE : l'ancien mot de passe avant d'accepter le nouveau.
     * Après changement : révoque tous les tokens → force reconnexion.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $request->user();

        // Vérifie que l'ancien mot de passe est correct
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Mot de passe actuel incorrect.'], 422);
        }

        $user->update(['password' => Hash::make($request->password)]);

        // Révoque tous les tokens → force reconnexion sur tous les appareils
        $user->tokens()->delete();

        return response()->json(['message' => 'Mot de passe mis à jour. Reconnectez-vous.']);
    }

    // =========================================================================
    // ADMIN — GESTION DES USERS
    // =========================================================================

    /**
     * Liste tous les utilisateurs internes (admin + employee).
     * Appelé par GET /api/admin/users (admin only)
     */
    public function index(): JsonResponse
    {
        $users = User::whereIn('role', ['admin', 'employee'])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'role', 'is_admin', 'is_active', 'last_login_at', 'created_at']);

        return response()->json($users);
    }

    /**
     * Crée un nouvel utilisateur interne (admin ou employee).
     * Appelé par POST /api/admin/users (admin only)
     *
     * Le mot de passe généré est temporaire → l'utilisateur devra le changer.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'role'  => 'required|in:admin,employee',
            'phone' => 'nullable|string|max:30',
        ]);

        // Génère un mot de passe temporaire aléatoire
        $tempPassword = \Illuminate\Support\Str::random(12);

        $user = User::create([
            'name'                 => $request->name,
            'email'                => $request->email,
            'phone'                => $request->phone,
            'password'             => Hash::make($tempPassword),
            'role'                 => $request->role,
            'is_admin'             => $request->role === 'admin',
            'is_active'            => true,
            'password_is_temporary' => true,
        ]);

        return response()->json([
            'message'       => 'Utilisateur créé.',
            'user'          => $user->only(['id', 'name', 'email', 'role']),
            'temp_password' => $tempPassword, // À envoyer par email en prod
        ], 201);
    }

    /**
     * Supprime un utilisateur interne.
     * Appelé par DELETE /api/admin/users/{user} (admin only)
     *
     * SÉCURITÉ : un admin ne peut pas se supprimer lui-même.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        // Empêche l'auto-suppression
        if ($request->user()->id === $user->id) {
            return response()->json(['message' => 'Vous ne pouvez pas supprimer votre propre compte.'], 422);
        }

        // Révoque tous les tokens avant suppression
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Utilisateur supprimé.']);
    }
}
