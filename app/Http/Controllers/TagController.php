<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Resources\TagResource;

use App\Models\Tag;

class TagController extends Controller
{
    public function __invoke() {
        return TagResource::collection(
            Tag::all()
        );
    }
}
