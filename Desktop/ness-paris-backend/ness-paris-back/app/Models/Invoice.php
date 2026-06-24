<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = [
        'number', 'company_id', 'order_id', 'status',
        'currency', 'subtotal_cents', 'tax_cents', 'total_cents',
        'issued_at', 'due_at', 'paid_at', 'pdf_path', 'notes',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'due_at'    => 'date',
        'paid_at'   => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function getTotalEurosAttribute(): float
    {
        return $this->total_cents / 100;
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'draft'     => 'Brouillon',
            'sent'      => 'Envoyée',
            'paid'      => 'Payée',
            'overdue'   => 'En retard',
            'cancelled' => 'Annulée',
            default     => $this->status,
        };
    }
}
