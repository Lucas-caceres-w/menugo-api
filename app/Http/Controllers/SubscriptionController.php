<?php

namespace App\Http\Controllers;

use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use Illuminate\Http\Request;
use MercadoPago\Client\PreApproval\PreApprovalClient;
use MercadoPago\MercadoPagoConfig;

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
        $subscription = $request->user()->activeSubscription();

        if (! $subscription) {
            return response()->json([
                'message' => 'No tienes una suscripción activa',
            ], 404);
        }

        return response()->json([
            'plan' => $subscription->plan,
            'auto_renew' => (bool) $subscription->auto_renew,
            'ends_at' => $subscription->ends_at,
        ]);
    }

    public function toggleAutoRenew(Request $request)
    {
        $enabled = $request->boolean('enabled');
        $subscription = $request->user()->activeSubscription();

        if (! $subscription) {
            return response()->json(['message' => 'No tienes una suscripción activa'], 404);
        }

        MercadoPagoConfig::setAccessToken(config('services.mercadopago.access_token'));
        $client = new PreApprovalClient();

        if (! $enabled) {
            if ($subscription->preapproval_id) {
                $client->update($subscription->preapproval_id, ['status' => 'cancelled']);
            }

            $subscription->update([
                'auto_renew' => false,
                'preapproval_id' => null,
            ]);

            return response()->json(['auto_renew' => false]);
        }

        if ($subscription->preapproval_id) {
            return response()->json(['auto_renew' => true]);
        }

        $preApproval = $client->create([
            'reason' => "Renovación del plan {$subscription->plan} de MenuGo",
            'external_reference' => "subscription_{$subscription->id}_{$subscription->plan}",
            'payer_email' => $request->user()->email,
            'back_url' => config('app.frontend_url') . '/dashboard/subscription?status=success',
            'status' => 'pending',
            'auto_recurring' => [
                'frequency' => 1,
                'frequency_type' => 'months',
                'transaction_amount' => (float) $subscription->price,
                'currency_id' => $subscription->currency ?: 'ARS',
            ],
        ]);

        $subscription->update([
            'auto_renew' => true,
            'preapproval_id' => $preApproval->id,
        ]);

        return response()->json([
            'auto_renew' => true,
            'checkout_url' => $preApproval->init_point,
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
