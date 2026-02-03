<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
            public function toArray(Request $request): array
            {
                        return [
                                    'id' => $this->id,
                                    'plan' => $this->plan,
                                    'status' => $this->status,
                                    'starts_at' => $this->starts_at,
                                    'ends_at' => $this->ends_at,
                                    'price' => $this->price,
                                    'currency' => $this->currency,
                                    'is_active' => $this->isActive(),
                                    'config' => config("plans.{$this->plan}"),
                        ];
            }
}
