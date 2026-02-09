<?php

namespace App\Http\Controllers;

use App\Models\Categorias;
use App\Models\Productos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProductosController extends Controller
{
    /**
     * Listar productos de una categoría
     */
    public function index(Request $request, $categoriaId)
    {
        try {
            $categoria = Categorias::where('id', $categoriaId)
                ->whereHas('local', function ($q) use ($request) {
                    $q->where('user_id', $request->user()->id);
                })
                ->firstOrFail();

            $productos = $categoria->productos()->orderBy('nombre')->with('product_extras')->get();

            return response()->json($productos, 200);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'No se pudieron obtener los productos',
            ], 500);
        }
    }

    /**
     * Crear producto
     */

    public function store(Request $request, $categoriaId)
    {
        DB::beginTransaction();

        try {
            $data = $request->validate([
                'nombre'        => 'required|string|max:255',
                'precio'        => 'required|numeric|min:0',
                'descripcion'   => 'nullable|string',
                'sku'           => 'nullable|string|max:100',
                'activo'        => 'sometimes|boolean',
                'image_product' => 'nullable|image|max:4096', // 4MB
                'extras'        => 'nullable|string', // viene como JSON
            ]);

            // 🔐 Validar que la categoría pertenece al local del usuario
            $categoria = Categorias::where('id', $categoriaId)
                ->whereHas('local', function ($q) use ($request) {
                    $q->where('user_id', $request->user()->id);
                })
                ->firstOrFail();

            // 📸 Guardar imagen si viene
            if ($request->hasFile('image_product')) {
                $path = $request->file('image_product')
                    ->store('products', 'public');

                $data['image_product'] = $path;
            }


            $producto = $categoria->productos()->create($data);

            if ($request->filled('extras')) {
                $extras = json_decode($request->extras, true);

                foreach ($extras as $extra) {
                    $producto->extras()->create([
                        'nombre'   => $extra['nombre'],
                        'precio'   => $extra['precio'],
                    ]);
                }
            }

            DB::commit();

            return response()->json($producto, 201);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear el producto',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Mostrar producto
     */
    public function show(Request $request, Productos $producto)
    {
        try {
            $this->authorizeProducto($request, $producto);

            return response()->json($producto, 200);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'No se pudo obtener el producto',
            ], 500);
        }
    }

    /**
     * Actualizar producto
     */
    public function update(Request $request, $productoId)
    {
        DB::beginTransaction();
        try {
            $producto = Productos::findOrFail($productoId);

            $data = $request->validate([
                'nombre'        => 'sometimes|string|max:255',
                'precio'        => 'sometimes|numeric|min:1',
                'descripcion'   => 'nullable|string',
                'image_product' => 'nullable|image|max:2048',
                'sku'           => 'nullable|string|max:100',
                'activo'        => 'sometimes|boolean',
                'extras'        => 'sometimes|string'
            ]);

            logger()->info('DATA UPDATE PRODUCTO', $data);

            if (isset($data['precio'])) {
                $data['precio'] = (int) round($data['precio']);
            }

            // 📸 Imagen
            if ($request->hasFile('image_product')) {
                $data['image_product'] = $request->file('image_product')
                    ->store('products', 'public');
            }

            $producto->extras()->delete();

            if ($request->filled('extras')) {
                $extras = json_decode($request->extras, true);

                if (!is_array($extras)) {
                    throw new \Exception('Formato de extras inválido');
                }

                foreach ($extras as $extra) {
                    $producto->extras()->create([
                        'nombre'   => $extra['nombre'],
                        'precio'   => $extra['precio'],
                    ]);
                }
            }

            // 🔐 Solo actualiza lo que vino
            $producto->fill($data);
            $producto->save();

            DB::commit();

            return response()->json($producto, 200);
        } catch (Throwable $e) {
            report($e);
            DB::rollBack();

            return response()->json([
                'message' => 'Error al actualizar el producto',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Eliminar producto
     */
    public function destroy(int $productoId)
    {
        try {

            $producto = Productos::where('id', $productoId);

            $producto->delete();

            return response()->json([
                'message' => 'Producto eliminado',
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Error al eliminar el producto',
            ], 500);
        }
    }

    /**
     * Validar ownership del producto
     */
    private function authorizeProducto(Request $request, Productos $producto)
    {
        if ($producto->categoria->local->user_id !== $request->user()->id) {
            abort(403, 'No autorizado');
        }
    }
}
