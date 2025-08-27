<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Flyer;
use App\Models\FlyerProducto;
use Carbon\Carbon;

class RestaurarPreciosFlyer extends Command
{
    protected $signature = 'flyers:restaurar';
    protected $description = 'Restaura los precios originales de los productos cuando expira un flyer';

    public function handle()
    {
        $now = Carbon::now('America/Lima');

        $flyers = Flyer::where('estado', 1)
            ->where('fecha_inicio', '<=', $now) // ✅ asegurar que ya comenzó
            ->where('fecha_fin', '<=', $now)    // ✅ y que ya terminó
            ->get();

        foreach ($flyers as $flyer) {
            $productos = FlyerProducto::where('flyer_id', $flyer->id)
                ->with('producto')
                ->get();

            foreach ($productos as $fp) {
                if ($fp->producto) {
                    $fp->producto->precio = $fp->precio_original;
                    $fp->producto->save();
                }
            }

            // Desactivar flyer
            $flyer->estado = 0;
            $flyer->save();

            $this->info("Flyer {$flyer->id} restaurado y desactivado.");
        }

        return 0;
    }
}
