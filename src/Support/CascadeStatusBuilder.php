<?php

namespace Perfocard\Flow\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Perfocard\Flow\Contracts\ShouldCollectStatus;
use Perfocard\Flow\Models\Status;

class CascadeStatusBuilder extends Builder
{
    public function delete()
    {
        $model = $this->getModel();

        // perform atomically
        return DB::transaction(function () use ($model) {
            if ($model instanceof ShouldCollectStatus) {
                $idsSubquery = $this->applyScopes()
                    ->cloneWithout(['columns', 'orders', 'limit', 'offset'])
                    ->select($model->getQualifiedKeyName());

                Status::where('statusable_type', $model->getMorphClass())
                    ->whereIn('statusable_id', $idsSubquery)
                    ->delete();
            }

            // 2) Delete the model records themselves (bulk).
            //    parent::delete() preserves normal behavior (including SoftDeletes — updating deleted_at)
            return parent::delete();
        });
    }
}
