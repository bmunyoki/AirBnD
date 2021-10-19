<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

use App\Notifications\OfficePendingApproval;

use App\Http\Resources\OfficeResource;

use App\Models\Office;
use App\Models\Reservation;

use App\Models\Validators\OfficeValidator;

class OfficeController extends Controller {
    
    public function index(): AnonymousResourceCollection {
        $offices = Office::query()
            /* When a user ID is provided in request and that ID is for the logged in user, return all the offices, otherwise filter hidden and pending approval */
            ->when(request('user_id') && auth()->user() && request('user_id') == auth()->user()->id, 
                function($builder) {
                    $builder = $builder;
                },
                function($builder) {
                    $builder->where('approval_status', Office::APPROVAL_APPROVED);
                    $builder->where('hidden', false);
                }
            )
            ->when(request('user_id'), function($builder) {
                $builder->whereUserId(request('user_id'));
            })
            //Filter offices with reservations for the provided user
            ->when(request('visitor_id'), function($builder) {
                $builder->whereRelation('reservations', 'user_id', '=', request('visitor_id'));
            })
            // If location is provided, order by it else by latest
            ->when(request('lat') && request('lng'), 
                function($builder) {
                    $builder->nearestTo(request('lat'), request('lng'));
                },
                function($builder) {
                    $builder->orderBy('id', 'ASC');
                }
            )
            ->with(['images', 'tags', 'user'])
            ->withCount(['reservations'], function($builder) {
                $builder->where('status', Reservation::STATUS_ACTIVE);
            })
            ->paginate(20);

        return OfficeResource::collection(
            $offices
        );
    }


    public function show(Office $office): JsonResource {
        $office->loadCount(['reservations'], function($builder) {
            $builder->where('status', Reservation::STATUS_ACTIVE);
        })->load(['images', 'tags', 'user']);

        return OfficeResource::make($office);
    }

    public function create(): JsonResource {
        //auth()->user()->tokenCan('office.create');

        abort_unless(auth()->user()->tokenCan('office.create'),
            Response::HTTP_FORBIDDEN
        );

        $attributes = (new OfficeValidator())->validate(
            $office = new Office(), 
            request()->all()
        );

        $attributes['user_id'] = auth()->user()->id;
        $attributes['approval_status'] = Office::APPROVAL_PENDING;

        $office = DB::transaction(function() use ($office, $attributes) {
            $office->fill(
                Arr::except($attributes, ['tags'])
            )->save();

            // You can also create the office using user relation ie.
            /*$office = auth()->user()->offices()->create(
                Arr::except($attributes, ['tags'])
            );*/

            // Below here if it was an update to the office, we can use sync instead of attach
            if (isset($attributes['tags'])) {
                $office->tags()->attach($attributes['tags']);
            }

            return $office;
        });
        

        return OfficeResource::make(
            $office->load(['user', 'images', 'tags'])
        );
    }

    public function update(Office $office): JsonResource {
        abort_unless(auth()->user()->tokenCan('office.create'),
            Response::HTTP_FORBIDDEN
        );

        $this->authorize('update', $office);

        $attributes = (new OfficeValidator())->validate($office, request()->all());

        $attributes['user_id'] = auth()->user()->id;

        $office->fill(
            Arr::except($attributes, ['tags'])
        );

        // Is dirty checks if any of the attributes have been changes. Episode 4 from min 43
        if ($requiresReview = $office->isDirty(['lat', 'lng', 'price_per_day'])) {
            $office->fill(['approval_status' => Office::APPROVAL_PENDING]);
        }

        DB::transaction(function() use ($office, $attributes) {
            $office->save();

            if (isset($attributes['tags'])) {
                $office->tags()->sync($attributes['tags']);
            }
        });

        if ($requiresReview) {
            Notification::send(User::where('is_admin', true)->get(), new OfficePendingApproval($office));
        }
        

        return OfficeResource::make(
            $office->load(['user', 'images', 'tags'])
        );
    }

    public function delete(Office $office) {
        abort_unless(auth()->user()->tokenCan('office.delete'),
            Response::HTTP_FORBIDDEN
        );

        $this->authorize('delete', $office);

        /*if ($office->reservations()->where('status', Reservation::STATUS_ACTIVE)->exists()) {
            throw ValidationException::withMessages(['office' => 'Cannot delete an office with active reservations']);
        }*/

        // The above can be rewritten as below which is better
        throw_if(
            $office->reservations()->where('status', Reservation::STATUS_ACTIVE)->exists(),
            ValidationException::withMessages(['office' => 'Cannot delete an office with active reservations'])
        );

        $office->delete();
    }
}
