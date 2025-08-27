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
    protected $description = 'Activa y restaura flyers según fecha de inicio y fin';

    public function handle()
    {
        $now = Carbon::now('America/Lima');

        // 🔹 1. Aplicar descuento a flyers que ya comenzaron y aún no se aplicaron
        $flyersToStart = Flyer::where('estado', 1)
            ->where('aplicado', 0)
            ->where('fecha_inicio', '<=', $now)
            ->where('fecha_fin', '>', $now)
            ->get();

        foreach ($flyersToStart as $flyer) {
            if ($flyer->producto_id) {
                $producto = Producto::find($flyer->producto_id);
                if ($producto) {
                    FlyerProducto::create([
                        'flyer_id' => $flyer->id,
                        'producto_id' => $producto->id,
                        'precio_original' => $producto->precio,
                    ]);
                    $producto->precio = $producto->precio - ($producto->precio * ($flyer->descuento / 100));
                    $producto->save();
                }
            } else {
                foreach ($flyer->proveedor->productos as $p) {
                    FlyerProducto::create([
                        'flyer_id' => $flyer->id,
                        'producto_id' => $p->id,
                        'precio_original' => $p->precio,
                    ]);
                    $p->precio = $p->precio - ($p->precio * ($flyer->descuento / 100));
                    $p->save();
                }
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
            $productos = FlyerProducto::where('flyer_id', $flyer->id)
                ->with('producto')
                ->get();

            foreach ($productos as $fp) {
                if ($fp->producto) {
                    $fp->producto->precio = $fp->precio_original;
                    $fp->producto->save();
                }
            }

            $flyer->estado = 0; // desactivado
            $flyer->save();

            $this->info("Flyer {$flyer->id} restaurado y desactivado.");
        }

        return 0;
    }
}
