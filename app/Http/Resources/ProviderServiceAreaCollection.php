<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ProviderServiceAreaCollection extends ResourceCollection
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        return ProviderServiceAreaResource::collection($this->collection)->resolve($request);
    }
}
