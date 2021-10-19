<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

use Illuminate\Support\Arr;

class OfficeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        // To return everything, use this
        //return parent::toArray($request);

        // For proper API resources, return this whihc does not expose some private data
        /*return Arr::except(parent::toArray($request), [
            'user_id', 'created_at', 'updated_at', 'deleted_at'
        ]); */

        // Again, to return a user resource instead of a user model. Same for images and tags
        return [
            'user' => UserResource::make($this->user),
            'images' => ImageResource::collection($this->images),
            'tags' => TagResource::collection($this->tags),

            $this->merge(Arr::except(parent::toArray($request), [
                'user_id', 'created_at', 'updated_at', 'deleted_at'
            ]))
        ];
    }
}
