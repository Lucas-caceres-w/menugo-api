<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
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
    public function show(Request $request)
    {
        $subscription = $request->user()->activeSubscription()
            ?: $request->user()->subscription()
                ->where('status', '!=', 'pending')
                ->latest('id')
                ->first();

        if (! $subscription || (!$subscription->isActive() && !$subscription->isRenewalAvailable())) {
            return response()->json([
                'message' => 'No tienes una suscripción activa',
            ], 404);
        }

        $status = $subscription->isActive()
            ? ($subscription->isRenewalAvailable() ? 'renewal_due' : 'active')
            : 'grace';

        return response()->json([
            'plan' => $subscription->plan,
            'status' => $status,
            'renewal_available' => $subscription->isRenewalAvailable(),
            'ends_at' => $subscription->ends_at,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Subscription $subscription)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Subscription $subscription)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Subscription $subscription)
    {
        //
    }
}
