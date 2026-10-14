<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\GivingReason;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The reasons members can give for at this church (Tithe, Offering, Building fund…), managed from the phone by the church's
 * finance staff. The same list is on the website under Finance → Giving reasons.
 */
class GivingReasonController extends Controller
{
    public function index(Church $church): JsonResponse
    {
        $totals = $church->donations()->getQuery()->succeeded()->whereNotNull('giving_reason_id')->selectRaw('giving_reason_id, COUNT(*) as gifts, SUM(amount) as total')->groupBy('giving_reason_id')->get()->keyBy('giving_reason_id');

        return response()->json(['data' => $church->givingReasons()->ordered()->get()->map(fn (GivingReason $r) => [
            'id' => $r->id, 'name' => $r->name, 'description' => $r->description, 'is_active' => $r->is_active,
            'gifts' => (int) ($totals[$r->id]->gifts ?? 0), 'total' => (float) ($totals[$r->id]->total ?? 0),
        ])->values()]);
    }

    public function store(Request $request, Church $church): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('giving_reasons', 'name')->where('church_id', $church->id)],
            'description' => ['nullable', 'string', 'max:160'],
        ]);
        $reason = $church->givingReasons()->create([...$data, 'category' => 'offering', 'is_active' => true, 'sort' => (int) $church->givingReasons()->max('sort') + 1]);
        Audit::log('giving_reason.created', $reason, $reason->name);

        return response()->json(['data' => ['id' => $reason->id, 'name' => $reason->name, 'description' => $reason->description, 'is_active' => true, 'gifts' => 0, 'total' => 0.0]], 201);
    }

    public function update(Request $request, Church $church, GivingReason $givingReason): JsonResponse
    {
        abort_unless($givingReason->church_id === $church->id, 404);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:60', Rule::unique('giving_reasons', 'name')->where('church_id', $church->id)->ignore($givingReason->id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:160'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $givingReason->update($data);
        Audit::log('giving_reason.updated', $givingReason, $givingReason->name);

        return response()->json(['data' => ['id' => $givingReason->id, 'name' => $givingReason->name, 'description' => $givingReason->description, 'is_active' => $givingReason->is_active]]);
    }

    public function destroy(Church $church, GivingReason $givingReason): JsonResponse
    {
        abort_unless($givingReason->church_id === $church->id, 404);
        $name = $givingReason->name;
        $givingReason->delete();
        Audit::log('giving_reason.deleted', null, $name);

        return response()->json(['deleted' => true]);
    }
}
