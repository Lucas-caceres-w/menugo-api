<?php

namespace App\Http\Controllers;

use App\Models\Pedidos;
use App\Models\Transacciones;
use Illuminate\Http\Request;
use Throwable;

class TransaccionesController extends Controller
{
    /**
     * Listar transacciones de un pedido (ADMIN)
     */
    public function index(Request $request, $pedidoId)
    {
        try {
            $pedido = Pedidos::where('id', $pedidoId)
                ->whereHas('local', function ($q) use ($request) {
                    $q->where('user_id', $request->user()->id);
                })
                ->firstOrFail();

            return response()->json(
                $pedido->transacciones,
                200
            );
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'No se pudieron obtener las transacciones',
            ], 500);
        }
    }

    /**
     * Registrar pago manual (ADMIN)
     */
    public function store(Request $request, $pedidoId)
    {
        try {
            $data = $request->validate([
                'total'        => 'required|numeric|min:0',
                'medio_pago'   => 'required|string',
                'referencia'   => 'nullable|string',
                'fecha_pago'   => 'nullable|date',
            ]);

            $pedido = Pedidos::where('id', $pedidoId)
                ->whereHas('local', function ($q) use ($request) {
                    $q->where('user_id', $request->user()->id);
                })
                ->firstOrFail();

            $transaccion = $pedido->transacciones()->create([
                'total'               => $data['total'],
                'medio_pago'          => $data['medio_pago'],
                'estado'              => 'aprobado',
                'referencia_externa'  => $data['referencia'] ?? null,
                'fecha_pago'          => $data['fecha_pago'] ?? now(),
            ]);

            // Si hay al menos una transacción aprobada → pedido pagado
            $pedido->update(['estado' => 'pagado']);

            return response()->json($transaccion, 201);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Error al registrar la transacción',
            ], 500);
        }
    }

    /**
     * Ver una transacción (ADMIN)
     */
    public function show(Request $request, Transacciones $transaccion)
    {
        try {
            if ($transaccion->pedido->local->user_id !== $request->user()->id) {
                abort(403, 'No autorizado');
            }

            return response()->json($transaccion, 200);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'No se pudo obtener la transacción',
            ], 500);
        }
    }

    /**
     * Actualizar estado de transacción (ADMIN)
     */
    public function update(Request $request, Transacciones $transaccion)
    {
        try {
            if ($transaccion->pedido->local->user_id !== $request->user()->id) {
                abort(403, 'No autorizado');
            }

            $data = $request->validate([
                'estado' => 'required|in:pendiente,aprobado,rechazado',
            ]);

            $transaccion->update($data);

            // Si se aprueba → marcar pedido como pagado
            if ($data['estado'] === 'aprobado') {
                $transaccion->pedido->update(['estado' => 'pagado']);
            }

            return response()->json($transaccion, 200);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Error al actualizar la transacción',
            ], 500);
        }
    }
}
