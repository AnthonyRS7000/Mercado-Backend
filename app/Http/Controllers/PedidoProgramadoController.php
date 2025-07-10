<?php

namespace App\Http\Controllers;

use App\Models\PedidoProgramado;
use Illuminate\Http\Request;

class PedidoProgramadoController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'metodo_pago_id' => 'required|exists:metodo_pagos,id',
            'direccion_entrega' => 'required|string',
            'fecha_programada' => 'required|date',
            'hora_programada' => 'required|date_format:H:i',
        ]);

        $pedido = PedidoProgramado::create($request->all());

        return response()->json([
            'message' => 'Pedido programado creado correctamente',
            'pedido' => $pedido
        ], 201);
    }
}
