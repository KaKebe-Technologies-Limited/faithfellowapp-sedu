<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Rules\NoContactDetails;
use App\Services\PeopleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * "Me" in the app: edit the profile and the photo, choose how findable you are, change the password.
 */
class ProfileController extends Controller
{
    public function __construct(protected PeopleService $people) {}

    public function update(Request $request): UserResource
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32', 'regex:/^[0-9+\s()-]{7,32}$/'],
            'country' => ['sometimes', 'required', 'string', 'size:2'],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'gender' => ['sometimes', 'nullable', Rule::in(['male', 'female'])],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:500', new NoContactDetails],
            'interests' => ['sometimes', 'nullable', 'array', 'max:8'],
            'interests.*' => ['string', 'max:30'],
            'directory_opt_in' => ['sometimes', 'boolean'],
            'open_to_connect' => ['sometimes', 'boolean'],
        ]);

        $user->fill($data);
        if (array_key_exists('country', $data)) {
            $user->country = strtoupper($data['country']);
        }
        if (isset($data['interests'])) {
            $user->interests = collect($data['interests'])->map(fn ($i) => trim(strip_tags($i)))->filter()->unique()->values()->all();
        }

        // Being found by other members is for adults who gave their date of birth.
        if (($user->directory_opt_in || $user->open_to_connect) && ! $this->people->isAdult($user)) {
            throw ValidationException::withMessages(['date_of_birth' => __('To be found by other members, add your date of birth. This is for members aged 18 and over.')]);
        }

        $user->save();

        return new UserResource($user->fresh()->load('subscribedChurches.media'));
    }

    /** The member's approximate place (rounded to about 1 km), used only to find believers nearby. */
    public function location(Request $request): UserResource
    {
        $data = $request->validate(['latitude' => ['required', 'numeric', 'between:-90,90'], 'longitude' => ['required', 'numeric', 'between:-180,180']]);
        $request->user()->forceFill(['latitude' => round((float) $data['latitude'], 2), 'longitude' => round((float) $data['longitude'], 2), 'location_updated_at' => now()])->save();

        return new UserResource($request->user()->fresh()->load('subscribedChurches.media'));
    }

    public function removeLocation(Request $request): UserResource
    {
        $request->user()->forceFill(['latitude' => null, 'longitude' => null, 'location_updated_at' => null])->save();

        return new UserResource($request->user()->fresh()->load('subscribedChurches.media'));
    }

    public function avatar(Request $request): UserResource
    {
        $request->validate(['avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096']]);
        $request->user()->addMediaFromRequest('avatar')->toMediaCollection('avatar');

        return new UserResource($request->user()->fresh()->load('subscribedChurches.media'));
    }

    public function removeAvatar(Request $request): UserResource
    {
        $request->user()->clearMediaCollection('avatar');

        return new UserResource($request->user()->fresh()->load('subscribedChurches.media'));
    }

    public function password(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update(['password' => Hash::make($data['password'])]);
        // Other phones sign in again; this one keeps its token.
        $request->user()->tokens()->where('id', '!=', $request->user()->currentAccessToken()?->id)->delete();

        return response()->json(['message' => __('Password changed.')]);
    }
}
