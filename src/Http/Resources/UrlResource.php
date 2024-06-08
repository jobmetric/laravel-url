<?php

namespace JobMetric\Url\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed id
 * @property mixed urlable_type
 * @property mixed urlable_id
 * @property mixed url
 * @property mixed collection
 * @property mixed created_at
 * @property mixed updated_at
 * @property mixed urlable_resource
 */
class UrlResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'urlable_type' => $this->urlable_type,
            'urlable_id' => $this->urlable_id,
            'url' => $this->url,
            'collection' => $this->collection,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'urlable' => $this?->urlable_resource
        ];
    }
}
