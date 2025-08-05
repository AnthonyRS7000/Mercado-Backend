<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    protected $table = 'pagos';

    protected $fillable = [
        'pedido_id',
        'user_id',
        'monto',
        // Mercado Pago fields:
        'mp_payment_id',
        'mp_preference_id',
        'mp_status',
        'mp_status_detail',
        'mp_payment_type_id',
        'mp_installments',
        'mp_card_issuer_id',
        'mp_card_id',
        'mp_raw_response',
    ];

    protected $attributes = [
        'metodo_pago' => 2, // Mercado Pago
    ];

    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
