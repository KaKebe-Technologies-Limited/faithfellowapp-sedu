<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Reaction;
use App\Services\Reactions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Reactions on church content from the app: POST /react/{type}/{id} { type: like|love|smile|amen|pray|celebrate } presses one (or takes it back).
 */
class ReactionController extends Controller
{
    public function toggle(Request $request, Reactions $reactions, string $type, int $id): JsonResponse
    {
        $model = $reactions->find($type, $id, $request->user());
        $data = $request->validate(['type' => ['required', 'string', 'in:'.implode(',', array_keys(Reaction::TYPES))]]);

        // like and smile need the database update that has not been applied yet: say so kindly, and keep the original four working.
        if (! array_key_exists($data['type'], Reaction::available())) {
            throw ValidationException::withMessages(['type' => __('Reactions are being switched on. Please try again soon.')]);
        }

        return response()->json($reactions->toggle($model, $request->user(), $data['type']));
    }
}
