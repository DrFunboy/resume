<?php

namespace App\Http\Resources;

use App\Models\AmoConnection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AmoConnection
 */
class AmoConnectionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'filter_id' => $this->filter_id,
            'filter_name' => $this->filter->name,
            'sheet_id' => $this->sheet_id,
            'sheet_fields' => $this->sheet_fields,
            'date_sync' => $this->date_sync ? date('Y-m-d H:i:s', strtotime($this->date_sync)) : null,
            'active' => $this->active,
            'author_id' => $this->amo_author,
            'created_at' => date('Y-m-d H:i:s', strtotime($this->created_at))
        ];
    }
}
