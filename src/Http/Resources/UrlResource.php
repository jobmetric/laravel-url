<?php

namespace JobMetric\Url\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Class UrlResource
 *
 * Transforms the Url model into a structured JSON resource.
 *
 * @property string $urlable_type
 * @property int $urlable_id
 * @property string $full_url
 * @property string|null $collection
 * @property int $version
 * @property Carbon|null $deleted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read  mixed $urlable_resource
 */
class UrlResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'urlable_type' => $this->urlable_type,
            'urlable_id' => $this->urlable_id,
            'full_url' => $this->full_url,
            'collection' => $this->collection,
            'version' => $this->version,

            // ISO 8601 timestamps for interoperability across clients
            'deleted_at' => $this->deleted_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Resource derived from UrlableResourceEvent
            'urlable' => $this->urlable_resource,
        ];
    }
}
