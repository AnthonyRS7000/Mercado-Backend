<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Flyer;
use App\Models\FlyerProducto;
use App\Models\Producto;
use Carbon\Carbon;

class RestaurarPreciosFlyer extends Command
{
    protected $signature = 'flyers:restaurar';
    protected $description = 'Aplica o restaura los precios de los productos según el estado de los flyers';

    public function handle()
    {
        $now = Carbon::now('America/Lima');

        // 🔹 1. Aplicar descuento a flyers que ya empezaron pero aún no se aplicaron
        $flyersToStart = Flyer::where('estado', 1)
            ->where('aplicado', 0)
            ->where('fecha_inicio', '<=', $now)
            ->where('fecha_fin', '>', $now)
            ->get();

        foreach ($flyersToStart as $flyer) {
            $productos = $flyer->producto_id
                ? Producto::where('id', $flyer->producto_id)->get()
                : Producto::where('proveedor_id', $flyer->proveedor_id)->get();

            foreach ($productos as $p) {
                // Guardar precio original en flyer_productos
                FlyerProducto::create([
                    'flyer_id'        => $flyer->id,
                    'producto_id'     => $p->id,
                    'precio_original' => $p->precio,
                ]);

                // Aplicar descuento
                $p->precio = $p->precio - ($p->precio * ($flyer->descuento / 100));
                $p->save();
            }

            $flyer->aplicado = 1;
            $flyer->save();

            $this->info("Flyer {$flyer->id} aplicado.");
        }

        // 🔹 2. Restaurar precios de flyers que ya terminaron
        $flyersToEnd = Flyer::where('estado', 1)
            ->where('aplicado', 1)
            ->where('fecha_fin', '<=', $now)
            ->get();

        foreach ($flyersToEnd as $flyer) {
            $productos = FlyerProducto::where('flyer_id', $flyer->id)->with('producto')->get();

            foreach ($productos as $fp) {
                if ($fp->producto) {
                    $fp->producto->precio = $fp->precio_original;
                    $fp->producto->save();
                }
            }

            $flyer->estado = 0;
            $flyer->save();

            $this->info("Flyer {$flyer->id} restaurado y desactivado.");
        }

        return 0;
    }
}
