<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute la colonne phone à la table users.
     *
     * POURQUOI phone ici et pas dans companies ?
     *   companies.phone = téléphone de la société (facturation, contact pro)
     *   users.phone     = téléphone personnel du compte → utilisé pour le reset SMS
     *
     * UNIQUE : un numéro ne peut appartenir qu'à un seul compte
     * NULLABLE : le téléphone est optionnel (la plupart des users n'en ont pas)
     * phone_verified_at : null = jamais vérifié, date = vérifié via OTP Twilio
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Placé après email pour garder une logique de lecture cohérente
            $table->string('phone', 30)->nullable()->unique()->after('email');
            $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
        });
    }

    /**
     * Annule la migration — supprime les colonnes ajoutées.
     * Utilisé si tu fais php artisan migrate:rollback
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'phone_verified_at']);
        });
    }
};