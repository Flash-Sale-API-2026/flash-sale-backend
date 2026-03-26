<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{message: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'message' => (string) data_get($this->resource, 'message'),
        ];
    }
}
