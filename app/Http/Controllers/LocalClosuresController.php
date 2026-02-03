<?php

namespace App\Http\Controllers;

use App\Models\Local;
use App\Models\localClosures;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LocalClosuresController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    public function store(Request $request, Local $localId)
    {
        $data = $request->validate([
            'date'   => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $closure = LocalClosures::create(
            [
                'local_id' => $localId->id,
                'date'     => $data['date'],
                'reason'   => $data['reason'] ?? null,
            ],
        );

        return response()->json($closure, 201);
    }

    /**
     * Crear vacaciones (rango)
     */
    public function storeRange(Request $request, Local $localId)
    {
        $data = $request->validate([
            'from'   => ['required', 'date'],
            'to'     => ['required', 'date', 'after_or_equal:from'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $dates = [];
        $current = Carbon::parse($data['from']);
        $end     = Carbon::parse($data['to']);

        while ($current->lte($end)) {
            $dates[] = $current->toDateString();
            $current->addDay();
        }

        DB::transaction(function () use ($dates, $localId, $data) {
            $existing = LocalClosures::where('local_id', $localId->id)
                ->whereIn('date', $dates)
                ->pluck('date')
                ->toArray();

            $toInsert = array_diff($dates, $existing);

            $rows = array_map(fn($date) => [
                'local_id'   => $localId->id,
                'date'       => $date,
                'reason'     => $data['reason'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ], $toInsert);

            if (!empty($rows)) {
                localClosures::insert($rows);
            }
        });

        return response()->json([
            'message' => 'Vacaciones registradas correctamente',
        ], 201);
    }

    /**
     * Eliminar cierre
     */
    public function destroy(Local $localId, localClosures $closure)
    {
        if ($closure->local_id !== $localId->id) {
            abort(403);
        }

        $closure->delete();

        return response()->json([
            'message' => 'Día cerrado eliminado',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(localClosures $localClosures)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(localClosures $localClosures)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, localClosures $localClosures)
    {
        //
    }
}
