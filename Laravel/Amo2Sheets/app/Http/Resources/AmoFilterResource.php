<?php

namespace App\Http\Resources;

use App\Models\AmoConnection;
use App\Models\AmoFilter;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AmoFilter
 */
class AmoFilterResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'used' => $this->used,
            'name' => $this->name,
            'comment' => $this->comment,
            'pipeline_id' => $this->pipeline_id,
            'filter_url' => $this->filter_url,
            'author_id' => $this->amo_author,
            'created_at' => date('Y-m-d H:i:s', strtotime($this->created_at))
        ];
    }
}
