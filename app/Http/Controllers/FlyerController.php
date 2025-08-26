<?php

namespace App\Http\Controllers;

use App\Models\Flyer;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\FlyerProducto;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class FlyerController extends Controller
{
    public function index()
    {
        $flyers = Flyer::with(['proveedor', 'producto'])
            ->where('estado', 1)
            ->where(function($q){
                $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', now());
            })
            ->get();

        return response()->json($flyers, 200);
    }

    public function show($id)
    {
        $flyer = Flyer::with(['proveedor', 'producto'])->find($id);

        if (!$flyer) {
            return response()->json(['error' => 'Flyer no encontrado.'], 404);
        }

        return response()->json($flyer, 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'titulo'        => 'nullable|string|max:255',
            'descripcion'   => 'nullable|string',
            'imagen'        => 'nullable|file|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'imagen_url'    => 'nullable|string|max:500',
            'proveedor_id'  => 'required|exists:proveedors,id',
            'producto_id'   => 'nullable|exists:productos,id',
            'descuento'     => 'required|numeric|min:1|max:100',
            'fecha_inicio'  => 'required|date_format:Y-m-d H:i:s',
            'fecha_fin'     => 'required|date_format:Y-m-d H:i:s',
            'estado'        => 'nullable|boolean',
            'password'      => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // ✅ Validar fechas con Carbon
        $fechaInicio = Carbon::parse($request->fecha_inicio);
        $fechaFin = Carbon::parse($request->fecha_fin);

        if ($fechaInicio->lt(Carbon::now())) {
            return response()->json(['error' => 'La fecha/hora de inicio no puede ser menor a la actual'], 422);
        }

        if ($fechaFin->lt($fechaInicio)) {
            return response()->json(['error' => 'La fecha/hora de fin debe ser mayor que la de inicio'], 422);
        }

        // ✅ Imagen
        $imagenUrl = null;
        if ($request->hasFile('imagen')) {
            $file = $request->file('imagen');
            $imagenPath = $file->store('flyers', 'public');
            $imagenUrl = Storage::url($imagenPath);
        } elseif ($request->filled('imagen_url')) {
            $imagenUrl = $request->imagen_url;
        }

        // ✅ Crear flyer
        $flyer = Flyer::create([
            'titulo'        => $request->titulo,
            'descripcion'   => $request->descripcion,
            'imagen'        => $imagenUrl,
            'proveedor_id'  => $request->proveedor_id,
            'producto_id'   => $request->producto_id,
            'descuento'     => $request->descuento,
            'fecha_inicio'  => $fechaInicio,
            'fecha_fin'     => $fechaFin,
            'estado'        => $request->estado ?? 1,
        ]);

        // ✅ Aplicar descuento
        if ($flyer->producto_id) {
            // Descuento a un solo producto
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
            // Descuento a toda la tienda (requiere contraseña del proveedor)
            if (!$request->filled('password')) {
                return response()->json(['error' => 'Se requiere contraseña del proveedor para aplicar a toda la tienda'], 403);
            }

            $proveedor = Proveedor::with('user')->find($flyer->proveedor_id);
            if (!$proveedor) {
                return response()->json(['error' => 'Proveedor no encontrado.'], 404);
            }

            if (!Hash::check($request->password, $proveedor->user->password)) {
                return response()->json(['error' => 'Contraseña incorrecta.'], 403);
            }

            $productos = Producto::where('proveedor_id', $proveedor->id)->get();
            foreach ($productos as $p) {
                FlyerProducto::create([
                    'flyer_id' => $flyer->id,
                    'producto_id' => $p->id,
                    'precio_original' => $p->precio,
                ]);

                $p->precio = $p->precio - ($p->precio * ($flyer->descuento / 100));
                $p->save();
            }
        }

        return response()->json($flyer, 201);
    }

    public function update(Request $request, $id)
    {
        $flyer = Flyer::find($id);

        if (!$flyer) {
            return response()->json(['error' => 'Flyer no encontrado.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'titulo'        => 'sometimes|string|max:255',
            'descripcion'   => 'sometimes|string',
            'imagen'        => 'nullable|file|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'imagen_url'    => 'nullable|string|max:500',
            'proveedor_id'  => 'nullable|exists:proveedors,id',
            'producto_id'   => 'nullable|exists:productos,id',
            'fecha_inicio'  => 'nullable|date_format:Y-m-d H:i:s',
            'fecha_fin'     => 'nullable|date_format:Y-m-d H:i:s',
            'estado'        => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // ✅ Validar fechas si las envía
        if ($request->filled('fecha_inicio')) {
            $fechaInicio = Carbon::parse($request->fecha_inicio);
            if ($fechaInicio->lt(Carbon::now())) {
                return response()->json(['error' => 'La fecha/hora de inicio no puede ser menor a la actual'], 422);
            }
            $flyer->fecha_inicio = $fechaInicio;
        }

        if ($request->filled('fecha_fin')) {
            $fechaFin = Carbon::parse($request->fecha_fin);
            if ($flyer->fecha_inicio && $fechaFin->lt($flyer->fecha_inicio)) {
                return response()->json(['error' => 'La fecha/hora de fin debe ser mayor que la de inicio'], 422);
            }
            $flyer->fecha_fin = $fechaFin;
        }

        // ✅ Imagen
        if ($request->hasFile('imagen')) {
            if ($flyer->imagen && Storage::disk('public')->exists(str_replace('/storage/', '', $flyer->imagen))) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $flyer->imagen));
            }
            $file = $request->file('imagen');
            $imagenPath = $file->store('flyers', 'public');
            $flyer->imagen = Storage::url($imagenPath);
        } elseif ($request->filled('imagen_url')) {
            $flyer->imagen = $request->imagen_url;
        }

        foreach (['titulo', 'descripcion', 'proveedor_id', 'producto_id', 'estado'] as $campo) {
            if ($request->has($campo)) {
                $flyer->{$campo} = $request->{$campo};
            }
        }

        $flyer->save();

        return response()->json($flyer, 200);
    }

    public function destroy($id)
    {
        $flyer = Flyer::find($id);

        if (!$flyer) {
            return response()->json(['error' => 'Flyer no encontrado.'], 404);
        }

        if ($flyer->imagen && Storage::disk('public')->exists(str_replace('/storage/', '', $flyer->imagen))) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $flyer->imagen));
        }

        $flyer->delete();
        return response()->json(['message' => 'Flyer eliminado exitosamente.'], 200);
    }
}
