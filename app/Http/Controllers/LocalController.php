<?php

namespace App\Http\Controllers;

use App\Models\Local;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class LocalController extends Controller
{
    /**
     * Listar locales del usuario autenticado
     */
    public function index()
    {
        try {
            $locales = Local::where('user_id', auth()->id())->get();

            return response()->json($locales, 200);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Error al obtener los locales',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Crear un nuevo local
     */
    public function store(Request $request)
    {
        try {
            $user = auth()->user();

            $subscription = $user->subscription;

            if (!$subscription) {
                return response()->json([
                    'message' => 'El usuario no tiene una suscripción activa',
                ], 403);
            }

            $plan = $subscription->plan;

            $planConfig = config("plans.$plan");

            $data = $request->validate([
                'nombre' => [
                    'required',
                    'string',
                    'max:255',
                    'regex:/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s]+$/'
                ],
                'descripcion' => 'nullable|string',
                'direccion' => 'nullable|string',
                'account' => 'nullable|string',
                'phone' => 'nullable|string'
            ]);

            $slug = Str::slug($data['nombre']);

            // Verificar unicidad
            if (Local::where('slug', $slug)->exists()) {
                return response()->json([
                    'message' => 'Ya existe un local con ese nombre'
                ], 422);
            }

            if (!$planConfig) {
                return response()->json([
                    'message' => 'Plan inválido',
                ], 403);
            }

            $maxLocals = $planConfig['max_locals'];

            if (
                $maxLocals !== null &&
                $user->locales()->count() >= $maxLocals
            ) {
                return response()->json([
                    'message' => 'Límite de locales alcanzado para tu plan',
                    'plan'    => $plan,
                    'limit'   => $maxLocals,
                ], 403);
            }

            $local = Local::create([
                'user_id' => auth()->id(),
                'nombre' => $data['nombre'],
                'descripcion' => $data['descripcion'] ?? '',
                'direccion' => $data['direccion'] ?? '',
                'account' => $data['account'] ?? '',
                'phone' => $data['phone'] ?? '',
                'slug' => $slug
            ]);

            return response()->json($local, 201);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Error al crear el local',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mostrar un local específico
     */
    public function show(string $localId)
    {
        try {
            $local = Local::where('id', $localId)
                ->firstOrFail();

            return response()->json($local, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Local no encontrado',
            ], 404);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Error al obtener el local',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    /**
     * Mostrar un local específico
     */
    public function showClientLocal(string $slug)
    {
        try {
            $local = Local::where('slug', $slug)
                ->with(['categorias.productos'])
                ->firstOrFail();

            return response()->json([
                'local'  =>  $local,
                'today'  =>  $local->todaySchedule()
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Local no encontrado',
            ], 404);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Error al obtener el local',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar un local
     */
    public function update(Request $request, $id)
    {
        try {
            $local = Local::where('id', $id)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            $data = $request->validate([
                'nombre' => 'required|string|max:255',
                'descripcion' => 'someone|string|nullable',
                'direccion' => 'someone|string|nullable',
                'account' => 'someone|string|nullable',
                'phone' => 'someone|string|nullable'
            ]);

            $local->update($data);

            return response()->json($local, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Local no encontrado',
            ], 404);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Error al actualizar el local',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar un local
     */
    public function destroy($id)
    {
        try {
            $local = Local::where('id', $id)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            $local->delete();

            return response()->json([
                'message' => 'Local eliminado correctamente',
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Local no encontrado',
            ], 404);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Error al eliminar el local',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
