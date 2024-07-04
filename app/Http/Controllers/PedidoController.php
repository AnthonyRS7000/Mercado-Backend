<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\Producto;
use App\Models\detalles_pedido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PedidoController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha' => 'required|date',
            'estado' => 'required|integer',
            'direccion_entrega' => 'required|string|max:255',
            'cliente_id' => 'required|exists:clientes,id',
            'metodo_pago_id' => 'required|exists:metodo_pagos,id',
            'productos' => 'required|array',
            'productos.*.producto_id' => 'required|exists:productos,id',
            'productos.*.cantidad' => 'required|numeric|min:0.1'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pedido = Pedido::create([
            'fecha' => $request->fecha,
            'estado' => $request->estado,
            'direccion_entrega' => $request->direccion_entrega,
            'cliente_id' => $request->cliente_id,
            'metodo_pago_id' => $request->metodo_pago_id,
            'total' => 0
        ]);

        $total = 0;
        foreach ($request->productos as $producto) {
            $productoModel = Producto::find($producto['producto_id']);
            $precioUnitario = $productoModel->precio;
            $cantidad = $producto['cantidad'];
            $subtotal = $precioUnitario * $cantidad;

            detalles_pedido::create([
                'cantidad' => $cantidad,
                'precio_unitario' => $precioUnitario,
                'subtotal' => $subtotal,
                'pedido_id' => $pedido->id,
                'producto_id' => $producto['producto_id']
            ]);

            $total += $subtotal;
        }

        $pedido->total = $total;
        $pedido->save();

        return response()->json($pedido->load('detalles_pedido.producto'), 201);
    }
}
