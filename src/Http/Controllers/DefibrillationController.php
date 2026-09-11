<?php

namespace Perfocard\Flow\Http\Controllers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Perfocard\Flow\Models\FlowModel;

class DefibrillationController
{
    /**
     * Defibrillate the given model on behalf of the signed-in user.
     */
    public function store(Request $request, string $type, string $model): JsonResponse|RedirectResponse
    {
        $model = $this->resolve($type, $model);

        abort_unless($model->canBeDefibrillatedBy($request->user()), 403);

        if (! $model->canDefibrillate()) {
            $message = __('This resource cannot be defibrillated.');

            if ($request->expectsJson()) {
                return new JsonResponse(['message' => $message], 409);
            }

            return back()->withErrors(['defibrillation' => $message]);
        }

        $model->defibrillate();

        if ($request->expectsJson()) {
            return new JsonResponse(['message' => __('Resource defibrillated.')]);
        }

        return back();
    }

    /**
     * Resolve the morph alias and key into a Flow model.
     */
    private function resolve(string $type, string $key): FlowModel
    {
        $class = Relation::getMorphedModel($type);

        if ($class === null) {
            // Falling back to the class name keeps the package usable without a
            // morph map, but never around an application that enforces one.
            abort_if(Relation::requiresMorphMap(), 404);

            $class = $type;
        }

        abort_unless(is_subclass_of($class, FlowModel::class), 404);

        return $class::query()->findOrFail($key);
    }
}
