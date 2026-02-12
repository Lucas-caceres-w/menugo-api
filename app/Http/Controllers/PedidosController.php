<?php

namespace App\Http\Controllers;

use App\Models\Local;
use App\Models\Pedidos;
use App\Models\Productos;
use App\Services\MercadoPagoServices;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Throwable;

class PedidosController extends Controller
{

    function calculateDistanceKm($lat1, $lng1, $lat2, $lng2)
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Listar pedidos (ADMIN del local)
     */
    public function index(Request $request, int $localId)
    {
        try {
            $user = $request->user();

            // Verificar que el local pertenezca al usuario
            $local = Local::where('id', $localId)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $pedidos = Pedidos::where('local_id', $local->id)
                ->with('items.extras')
                ->orderByDesc('created_at')
                ->get();
            // Paginación

            return response()->json($pedidos, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Local no encontrado o sin permisos',
            ], 403);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'No se pudieron obtener los pedidos',
            ], 500);
        }
    }


    /**
     * Crear pedido (CLIENTE)
     */
    public function store(Request $request, $localId, MercadoPagoServices $mpServices)
    {
        try {
            $data = $request->validate([
                'name'    => 'required|string|max:255',
                'phone'   => 'required|string|max:50',
                'client_address' => 'nullable|string|max:255',
                'client_lat' => 'nullable|numeric',
                'client_lng' => 'nullable|numeric',
                'tipo_entrega' => 'required|in:delivery,retiro',
                'observacion' => 'nullable|string|max:255',
                'payment_method'   => 'required|in:cash,transfer',
                'transfer_type'    => 'nullable|in:manual,mercadopago',

                'items'              => 'required|array|min:1',
                'items.*.producto_id' => 'required|integer|exists:productos,id',
                'items.*.cantidad'   => 'required|integer|min:1',

                'items.*.extras'      => 'nullable|array',
                'items.*.extras.*'    => 'integer',
            ]);

            $local = Local::findOrFail($localId);

            $estado = match (true) {
                $data['payment_method'] === 'cash'
                => 'pendiente',

                $data['transfer_type'] === 'manual'
                => 'pendiente_pago',

                $data['transfer_type'] === 'mercadopago'
                => 'pendiente_pago',
            };

            $pedido = Pedidos::create([
                'local_id'        => $local->id,
                'client_name'     => $data['name'],
                'client_phone'    => $data['phone'],
                'client_address'  => $data['client_address'],
                'client_lat'      => $data['client_lat'],
                'client_lng'      => $data['client_lng'],
                'tipo_entrega'    => $data['tipo_entrega'],
                'observacion'     => $data['observacion'],
                'payment_method'  => $data['payment_method'],
                'payment_status'  => 'unpaid',
                'estado'          => $estado,
                'total'           => 0,
            ]);

            $total = 0;

            foreach ($data['items'] as $item) {
                $producto = Productos::where('id', $item['producto_id'])
                    ->whereHas(
                        'categoria',
                        fn($q) =>
                        $q->where('local_id', $local->id)
                    )
                    ->firstOrFail();

                $precioBase = $producto->precio;
                $extrasTotal = 0;

                $pedidoItem = $pedido->items()->create([
                    'pedido_id'     => $pedido->id,
                    'producto_id'     => $producto->id,
                    'producto_nombre'   => $producto->nombre,
                    'precio_unitario'     => $producto->precio,
                    'cantidad'       => $item['cantidad'],
                    'subtotal'       => 0,
                ]);

                if (!empty($item['extras'])) {
                    $extras = $producto->extras()
                        ->whereIn('id', $item['extras'])
                        ->get();

                    foreach ($extras as $extra) {
                        $pedidoItem->extras()->create([
                            'extra_id'     => $extra->id,
                            'extra_nombre' => $extra->nombre,
                            'extra_precio' => $extra->precio,
                        ]);

                        $extrasTotal += $extra->precio;
                    }
                }

                // 3️⃣ Subtotal REAL
                $subtotal = ($precioBase + $extrasTotal) * $item['cantidad'];

                $pedidoItem->update([
                    'subtotal' => $subtotal,
                ]);

                $total += $subtotal;
            }

            $costoEnvio = 0;
            if ($data['tipo_entrega'] === 'delivery' && $local->lat && $local->lng && $data['client_lat'] && $data['client_lng']) {
                $distanceKm = $this->calculateDistanceKm(
                    (float)$local->lat,
                    (float)$local->lng,
                    (float)$data['client_lat'],
                    (float)$data['client_lng']
                );

                if ($distanceKm > $local->radio_max) {
                    return response()->json([
                        'message' => 'La dirección está fuera del radio de envío permitido',
                        'distance' => round($distanceKm, 2),
                        'radio_maximo' => $local->radio_max,
                    ], 422);
                }

                $costoEnvio = round($distanceKm * $local->precio_envio); // precio_envio es por km
                $total += $costoEnvio;
            }

            $pedido->update(['total' => $total, 'costo_envio' => $costoEnvio]);

            $checkoutUrl = null;
            if (
                $data['payment_method'] === 'transfer' &&
                $data['transfer_type'] === 'mercadopago'
            ) {
                if (!$local->mercadoPagoToken) {
                    return response()->json([
                        'message' => 'El local no tiene MercadoPago habilitado'
                    ], 400);
                }

                $pedido->load('items');

                $preference = $mpServices->createPreference(
                    $local,
                    $pedido,
                );

                $checkoutUrl = $preference->init_point;
            };

            return response()->json(
                [
                    'pedido' => $pedido->load('items'),
                    'transfer_type' => $data['transfer_type'],
                    'checkout_url'  => $checkoutUrl
                ],
                201
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Error al crear el pedido',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar pedido (ADMIN o CLIENTE)
     */
    public function show(Request $request, Pedidos $pedido)
    {
        try {
            // Admin del local
            if ($request->user() && $pedido->local->user_id === $request->user()->id) {
                return response()->json(
                    $pedido->load('items.extras'),
                    200
                );
            }

            // Cliente (link público o token cliente)
            return response()->json(
                $pedido->load('items'),
                200
            );
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'No se pudo obtener el pedido',
            ], 500);
        }
    }

    public function showCliente(Request $request, $pedidoId)
    {
        try {
            // Admin del local
            $pedido = Pedidos::where('id', $pedidoId)->with('items.extras')->with('local')->get();

            if (!isset($pedido)) {
                return response()->json(
                    null,
                    204
                );
            }

            return response()->json(
                $pedido,
                200
            );
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'No se pudo obtener el pedido',
            ], 500);
        }
    }

    /**
     * Actualizar estado del pedido (ADMIN)
     */
    public function update(Request $request, Pedidos $pedido)
    {
        try {
            if ($pedido->local->user_id !== $request->user()->id) {
                abort(403, 'No autorizado');
            }

            $data = $request->validate([
                'estado' => 'required|in:pendiente,aprobado,pagado,cancelado',
            ]);

            $pedido->update($data);

            return response()->json($pedido, 200);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Error al actualizar el pedido',
            ], 500);
        }
    }

    /**
     * Cancelar pedido (ADMIN)
     */
    public function destroy(Request $request, Pedidos $pedido)
    {
        try {
            if ($pedido->local->user_id !== $request->user()->id) {
                abort(403, 'No autorizado');
            }

            $pedido->update([
                'estado' => 'cancelado',
            ]);

            return response()->json([
                'message' => 'Pedido cancelado',
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Error al cancelar el pedido',
            ], 500);
        }
    }
}
