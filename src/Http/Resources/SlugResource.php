<?php

namespace JobMetric\Url\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Class SlugResource
 *
 * Transforms the slug model into a structured JSON resource.
 *
 * @property string $slugable_type
 * @property int $slugable_id
 * @property string $slug
 * @property string|null $collection
 * @property Carbon|null $deleted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property mixed $slugable_resource
 */
class SlugResource extends JsonResource
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
            'slugable_type' => $this->slugable_type,
            'slugable_id' => $this->slugable_id,
            'slug' => $this->slug,
            'collection' => $this->collection,
            'deleted_at' => $this->deleted_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'slugable' => $this?->slugable_resource,
        ];
    }
}
