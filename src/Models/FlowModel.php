<?php

namespace Perfocard\Flow\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\DB;
use Perfocard\Flow\Contracts\BackedEnum;
use Perfocard\Flow\Contracts\ShouldBeDefibrillated;
use Perfocard\Flow\Contracts\ShouldBeTouched;
use Perfocard\Flow\Contracts\ShouldCollectStatus;
use Perfocard\Flow\Exceptions\CannotBeDefibrillatedException;
use Perfocard\Flow\Exceptions\ShouldBeDefibrillatedException;
use Perfocard\Flow\Exceptions\ShouldCollectStatusException;
use Perfocard\Flow\Exceptions\UndefinedStatusException;
use Perfocard\Flow\Observers\ModelObserver;
use Perfocard\Flow\Support\CascadeStatusBuilder;

/**
 * @property string|null $statusPayload
 * @property BackedEnum $status
 */
class FlowModel extends Model
{
    public ?string $__creatingStatusPayload = null;

    public ?string $__creatingStatusType = null;

    public bool $__forceStatusEvents = false;

    /**
     * Get the fillable attributes for the model.
     *
     * @return array<string>
     */
    public function getFillable()
    {
        return [
            ...$this->fillable,
            'statusPayload',
            'statusType',
        ];
    }

    public function newEloquentBuilder($query)
    {
        return new CascadeStatusBuilder($query);
    }

    public function statuses(): MorphMany
    {
        if (! ($this instanceof ShouldCollectStatus)) {
            throw new ShouldCollectStatusException;
        }

        return $this->morphMany(config('flow.status.model'), 'statusable');
    }

    public function latestStatus(): MorphOne
    {
        if (! ($this instanceof ShouldCollectStatus)) {
            throw new ShouldCollectStatusException;
        }

        return $this->morphOne(config('flow.status.model'), 'statusable')
            ->latest();
    }

    public function oldestStatus(): MorphOne
    {
        if (! ($this instanceof ShouldCollectStatus)) {
            throw new ShouldCollectStatusException;
        }

        return $this->morphOne(config('flow.status.model'), 'statusable')
            ->oldest();
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function (FlowModel $model) {
            $model->__creatingStatusPayload = $model->statusPayload;
            $model->__creatingStatusType = $model->statusType;

            unset($model->statusPayload, $model->statusType);
        });

        static::created(function (FlowModel $model) {
            // Preserving the initial status if the model implements the ShouldCollectStatus interface
            if ($model instanceof ShouldCollectStatus) {
                if ($model->status === null) {
                    throw new UndefinedStatusException;
                }

                $model->statuses()->create([
                    'status' => $model->status,
                    'payload' => $model->__creatingStatusPayload,
                    'type' => $model->statusType,
                ]);
            }
        });

        static::updating(function (FlowModel $model) {
            // Saving the new status if it has changed and the model implements the ShouldCollectStatus interface
            if ($model instanceof ShouldCollectStatus) {

                if ($model->status === null) {
                    throw new UndefinedStatusException;
                }

                $force = $model->__forceStatusEvents === true;
                $changed = $model->status != $model->getOriginal('status');

                if ($force || $changed) {
                    $model->statuses()->create([
                        'status' => $model->status,
                        'payload' => $model->statusPayload,
                        'type' => $model->statusType,
                    ]);
                }

                unset($model->statusPayload, $model->statusType);
            }
        });

        static::deleting(function (FlowModel $model) {
            // Removing all statuses if the model implements the ShouldCollectStatus interface
            if ($model instanceof ShouldCollectStatus) {
                $model->statuses()->delete();
            }
        });
    }

    protected static function booted()
    {
        static::observe(ModelObserver::class);
    }

    /**
     * Determine whether the current status has a status to defibrillate into.
     */
    public function canDefibrillate(): bool
    {
        return $this instanceof ShouldCollectStatus
            && $this->status instanceof ShouldBeDefibrillated
            && $this->status->defibrillate() !== null;
    }

    /**
     * Determine whether the given user may defibrillate this model.
     *
     * Closed by default: the client surface stays unavailable until a model
     * overrides this. Nova does not consult it — there the gate is a policy.
     */
    public function canBeDefibrillatedBy(?Authenticatable $user): bool
    {
        return false;
    }

    /**
     * Get the client URL that defibrillates this model, or null when unavailable.
     */
    public function defibrillationUrl(?Authenticatable $user = null): ?string
    {
        if (! config('flow.defibrillation.enabled')) {
            return null;
        }

        if (! $this->canDefibrillate()) {
            return null;
        }

        if (! $this->canBeDefibrillatedBy($user ?? auth()->user())) {
            return null;
        }

        return route('flow.defibrillations.store', [
            'type' => $this->getMorphClass(),
            'model' => $this->getKey(),
        ]);
    }

    public function defibrillate(): self
    {
        if (! ($this instanceof ShouldCollectStatus)) {
            throw new ShouldCollectStatusException;
        }

        return DB::transaction(function () {
            // Holding the row until commit, so a double click cannot dispatch
            // the queued event twice.
            $this->newQueryWithoutScopes()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->refresh();

            if (! ($this->status instanceof ShouldBeDefibrillated)) {
                throw new ShouldBeDefibrillatedException;
            }

            $status = $this->status->defibrillate();

            if ($status === null) {
                throw new CannotBeDefibrillatedException;
            }

            return $this->setStatusAndSave($status);
        });
    }

    public function setStatus(BackedEnum|ShouldBeDefibrillated $status, ?string $payload = null, ?StatusType $type = null): self
    {
        $this->status = $status;
        $this->statusPayload = $payload;
        $this->statusType = $type;

        return $this;
    }

    public function setStatusAndSave(BackedEnum|ShouldBeDefibrillated $status, ?string $payload = null, ?StatusType $type = null): self
    {
        $this->setStatus($status, $payload, $type);
        $this->save();

        return $this;
    }

    public function touch($attribute = null): bool
    {
        return DB::transaction(function () use ($attribute) {
            $shouldForce = ($this instanceof ShouldCollectStatus)
                && ($this->status instanceof ShouldBeTouched);

            if ($shouldForce) {
                $this->__forceStatusEvents = true;
            }

            try {
                return parent::touch($attribute);
            } finally {
                if ($shouldForce) {
                    $this->__forceStatusEvents = false;
                }
            }
        });
    }
}
