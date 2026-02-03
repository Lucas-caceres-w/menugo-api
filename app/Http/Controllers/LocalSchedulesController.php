<?php

namespace App\Http\Controllers;

use App\Models\Local;
use App\Models\localSchedules;
use Illuminate\Http\Request;
use Throwable;

class LocalSchedulesController extends Controller
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

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Local $localId)
    {
        try {
            $schedules = $localId->schedules()
                ->orderBy('day_of_week')
                ->get();

            $closures = $localId->closures()
                ->orderBy('date')
                ->get();

            return response()->json(
                [
                    'schedules' => $schedules,
                    'closures' => $closures
                ],
                200
            );
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Error al obtener los horarios',
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(localSchedules $localSchedules)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Local $localId)
    {
        $data = $request->validate([
            'schedules' => 'required|array',
            'schedules.*.day_of_week' => 'required|integer|between:0,6',
            'schedules.*.is_closed' => 'required|boolean',
            'schedules.*.opens_at' => 'nullable|required_if:schedules.*.is_closed,false|date_format:H:i',
            'schedules.*.closes_at' => 'nullable|required_if:schedules.*.is_closed,false|date_format:H:i',
        ]);

        try {
            $grouped = collect($data['schedules'])->groupBy('day_of_week');

            // Validar máximo 2 bloques por día
            foreach ($grouped as $day => $blocks) {
                if ($blocks->count() > 2) {
                    return response()->json([
                        'message' => "Máximo 2 bloques por día ($day)",
                    ], 422);
                }
            }

            // Limpiar horarios existentes
            $localId->schedules()->delete();

            // Crear nuevos
            foreach ($data['schedules'] as $schedule) {
                $localId->schedules()->create([
                    'day_of_week' => $schedule['day_of_week'],
                    'is_closed'   => $schedule['is_closed'],
                    'opens_at'    => $schedule['is_closed'] ? null : $schedule['opens_at'],
                    'closes_at'   => $schedule['is_closed'] ? null : $schedule['closes_at'],
                ]);
            }

            return response()->json([
                'message' => 'Horarios actualizados correctamente',
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Error al actualizar los horarios' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(localSchedules $localSchedules)
    {
        //
    }
}
