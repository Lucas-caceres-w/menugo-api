<?php

namespace App\Http\Controllers;

use App\Models\Local;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
                'phone' => 'nullable|string|max:20'
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

            $phone = null;

            if (!empty($data['phone'])) {
                // eliminar todo lo que no sea número
                $digits = preg_replace('/\D+/', '', $data['phone']);

                /*
                     Reglas Argentina:
                     - si empieza con 54 y no tiene 9 → agregar 9
                     - si no empieza con 54 → agregar 549
                    */

                if (str_starts_with($digits, '54')) {
                    if (!str_starts_with($digits, '549')) {
                        $digits = '549' . substr($digits, 2);
                    }
                } else {
                    $digits = '549' . $digits;
                }

                $phone = $digits;
            }

            $local = Local::create([
                'user_id' => auth()->id(),
                'nombre' => $data['nombre'],
                'descripcion' => $data['descripcion'] ?? '',
                'direccion' => $data['direccion'] ?? '',
                'account' => $data['account'] ?? '',
                'phone' => $phone ?? '',
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
                ->with(['categorias.productos.extras'])
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
                'descripcion' => 'nullable|string|nullable',
                'direccion' => 'nullable|string|nullable',
                'account' => 'nullable|string|nullable',
                'phone' => 'nullable|string|nullable'
            ]);

            $phone = null;

            if (!empty($data['phone'])) {
                // eliminar todo lo que no sea número
                $digits = preg_replace('/\D+/', '', $data['phone']);

                if (str_starts_with($digits, '54')) {
                    if (!str_starts_with($digits, '549')) {
                        $digits = '549' . substr($digits, 2);
                    }
                } else {
                    $digits = '549' . $digits;
                }

                $phone = $digits;
            }
            $local->update([
                'nombre' => $data['nombre'],
                'descripcion' => $data['descripcion'] ?? '',
                'direccion' => $data['direccion'] ?? '',
                'account' => $data['account'] ?? '',
                'phone' => $phone ?? '',
            ]);

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
    
    public function saveImages(Request $request, int $localId)
    {
        try {
            $user = $request->user();

            // 🔐 Validar ownership del local
            $local = Local::where('id', $localId)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $data = $request->validate([
                'avatar' => 'nullable|image|max:4096', // 4MB
                'cover'  => 'nullable|image|max:6144', // 6MB
            ]);

            // 📸 Imagen de perfil
            if ($request->hasFile('avatar')) {
                if ($local->avatar) {
                    Storage::disk('public')->delete($local->avatar);
                }

                $data['avatar'] = $request->file('avatar')
                    ->store('locales/avatars', 'public');
            }

            // 🖼 Imagen de portada
            if ($request->hasFile('cover')) {
                if ($local->cover) {
                    Storage::disk('public')->delete($local->cover);
                }

                $data['cover'] = $request->file('cover')
                    ->store('locales/covers', 'public');
            }

            $local->update($data);

            return response()->json([
                'message' => 'Imágenes del local actualizadas',
                'local' => $local->fresh(),
            ], 200);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Error al guardar las imágenes',
            ], 500);
        }
    }
    public function destroyImages(Request $request, Local $local, string $type)
    {
        $user = $request->user();

        // 🔐 ownership
        if ($local->user_id !== $user->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        if (!in_array($type, ['avatar', 'cover'])) {
            return response()->json(['message' => 'Tipo inválido'], 422);
        }

        if ($local->$type) {
            Storage::disk('public')->delete($local->$type);
            $local->update([$type => null]);
        }

        return response()->json([
            'message' => 'Imagen eliminada correctamente'
        ]);
    }
}
