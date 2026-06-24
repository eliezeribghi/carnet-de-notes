<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        // Identité commande
        'number', 'status', 'company_id', 'customer_id',

        // Client
        'customer_email', 'customer_name', 'customer_phone', 'customer_company',

        // Adresse livraison
        'shipping_address', 'shipping_city', 'shipping_zip', 'shipping_country',

        // Adresse facturation
        'billing_name', 'billing_email', 'billing_vat_number',
        'billing_address', 'billing_line2', 'billing_zip', 'billing_city', 'billing_country',

        // Montants
        'currency', 'subtotal_cents', 'shipping_cents', 'total_cents',

        // Stripe
        'stripe_checkout_session_id', 'stripe_payment_intent_id', 'paid_at',

        // Livraison classique
        'tracking_number', 'carrier', 'shipped_at', 'delivered_at',

        // Remboursement
        'refund_status', 'refunded_cents', 'notes',

        // ✅ Méthode d'expédition choisie au checkout
        'shipping_method_key', 'shipping_method_label', 'shipping_carrier',

        // ✅ Champs Sendcloud — MANQUAIENT dans $fillable !
        'sendcloud_checkout_option_id',
        'sendcloud_service_point_id',
        'sendcloud_shipping_method_id',

        // ✅ Résultat après création du parcel Sendcloud
        'shipping_tracking_number',
        'shipping_tracking_url',
        'shipping_label_url',
        'shipping_status',
        
        // 
        'pennylane_invoice_id',

    ];
 
    protected $casts = [
        'paid_at'      => 'datetime',
        'shipped_at'   => 'datetime',
        'delivered_at' => 'datetime',
    ];

    // -------------------------------------------------------
    // Relations
    // -------------------------------------------------------

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    // -------------------------------------------------------
    // Accesseurs (getters calculés)
    // -------------------------------------------------------

    public function getTotalEurosAttribute(): float
    {
        return $this->total_cents / 100;
    }

    public function getSubtotalEurosAttribute(): float
    {
        return $this->subtotal_cents / 100;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft'           => 'Brouillon',
            'pending_payment' => 'En attente de paiement',
            'paid'            => 'Payée',
            'processing'      => 'En préparation',
            'shipped'         => 'Expédiée',
            'delivered'       => 'Livrée',
            'cancelled'       => 'Annulée',
            'refunded'        => 'Remboursée',
            default           => $this->status,
        };
    }
}
