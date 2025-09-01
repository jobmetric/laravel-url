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
 * @property int $id
 * @property string $urlable_type
 * @property int $urlable_id
 * @property string $url
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property mixed $urlable_resource
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
            'id' => $this->id,
            'urlable_type' => $this->urlable_type,
            'urlable_id' => $this->urlable_id,
            'url' => $this->url,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'urlable' => $this?->urlable_resource,
        ];
    }
}
