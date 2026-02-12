<?php

namespace App\Http\Controllers;

use App\Models\Categorias;
use App\Models\Local;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CategoriasController extends Controller
{
    /**
     * Listar categorías de un local
     */
    public function index(Request $request, $localId)
    {
        try {
            $local = Local::where('id', $localId)
                ->where('user_id', $request->user()->id)
                ->first();

            $categorias = $local->categorias()->orderBy('orden')->get();

            return response()->json($categorias, 200);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'No se pudieron obtener las categorías',
            ], 500);
        }
    }

    /**
     * Crear categoría
     */
    public function store(Request $request, int $localId)
    {
        try {
            $data = $request->validate([
                'nombre' => 'required|string|max:255',
                'orden' => 'nullable|string',
                'activo' => 'boolean',
                'image_category' => 'nullable|image|max:2048',
            ]);

            if ($request->hasFile('image_category')) {
                $path = $request->file('image_category')
                    ->store('categories', 'public');
                $data['image_category'] = $path;
            }

            Categorias::create([
                'nombre' => $data['nombre'],
                'orden' => $data['orden'],
                'activo' => $data['activo'] ?? true,
                'image_category' => $data['image_category'] ?? '',
                'local_id' => $localId
            ]);

            return response()->json(['message' => 'Categoría creada']);
        } catch (Throwable $th) {
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }


    /**
     * Mostrar una categoría
     */
    public function show(int $localId)
    {
        try {
            $menu = Categorias::where('local_id', $localId)
                ->orderBy('orden')
                ->with('productos.extras')
                ->get();

            return response()->json($menu, 200);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'No se pudo obtener la categoría',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar categoría
     */
    public function update(Request $request, int $categoriaId)
    {
        try {
            $categoria = Categorias::find($categoriaId);

            $data = $request->validate([
                'nombre' => 'sometimes|required|string|max:255',
                'orden'  => 'sometimes|integer',
                'activo' => 'sometimes|boolean',
                'image_category' => 'nullable|image|max:2048',
            ]);

            if ($request->hasFile('image_category')) {

                if ($categoria->image_category) {
                    Storage::disk('public')->delete($categoria->image_category);
                }

                $path = $request->file('image_category')
                    ->store('categories', 'public');

                $data['image_category'] = $path;
            }

            $categoria->update($data);

            return response()->json($categoria, 200);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Error al actualizar la categoría',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar categoría
     */
    public function destroy(Categorias $categoria)
    {
        try {
            $categoria->delete();

            return response()->json([
                'message' => 'Categoría eliminada',
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Error al eliminar la categoría',
            ], 500);
        }
    }
    public function reorder(Request $request)
    {
        foreach ($request->all() as $item) {
            Categorias::where('id', $item['id'])
                ->update(['orden' => $item['orden']]);
        }

        return response()->json(['message' => 'Orden actualizado']);
    }
}
