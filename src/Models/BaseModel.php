<?php

namespace Perfocard\Flow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\DB;
use Perfocard\Flow\Contracts\ShouldBeDefibrillated;
use Perfocard\Flow\Contracts\ShouldBeTouched;
use Perfocard\Flow\Contracts\ShouldCollectStatus;
use Perfocard\Flow\Exceptions\ShouldBeDefibrillatedException;
use Perfocard\Flow\Exceptions\ShouldCollectStatusException;
use Perfocard\Flow\Exceptions\UndefinedStatusException;
use Perfocard\Flow\Observers\ModelObserver;
use Perfocard\Flow\Support\CascadeStatusBuilder;
use Webfox\LaravelBackedEnums\BackedEnum;

class BaseModel extends Model
{
    public ?string $__creatingStatusPayload = null;

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

        static::creating(function (Model $model) {
            $model->__creatingStatusPayload = $model->statusPayload;

            unset($model->statusPayload);
        });

        static::created(function (Model $model) {
            // Preserving the initial status if the model implements the ShouldCollectStatus interface
            if ($model instanceof ShouldCollectStatus) {
                if ($model->status === null) {
                    throw new UndefinedStatusException;
                }

                $model->statuses()->create([
                    'status' => $model->status,
                    'payload' => $model->__creatingStatusPayload,
                ]);
            }
        });

        static::updating(function (Model $model) {
            // Saving the new status if it has changed and the model implements the ShouldCollectStatus interface
            if ($model instanceof ShouldCollectStatus) {

                if ($model->status === null) {
                    throw new UndefinedStatusException;
                }

                $force = property_exists($model, '__forceStatusEvents') && $model->__forceStatusEvents === true;
                $changed = $model->status != $model->getOriginal('status');

                if ($force || $changed) {
                    $model->statuses()->create([
                        'status' => $model->status,
                        'payload' => $model->statusPayload,
                    ]);
                }

                unset($model->statusPayload);
            }
        });

        static::deleting(function (Model $model) {
            // Removing all statuses if the model implements the ShouldCollectStatus interface
            if ($model instanceof ShouldCollectStatus) {
                $model->statuses()->delete();
            }
        });

        static::observe(ModelObserver::class);
    }

    public function defibrillate(): ?static
    {
        if (! ($this instanceof ShouldCollectStatus)) {
            throw new ShouldCollectStatusException;
        }

        if (! ($this->status instanceof ShouldBeDefibrillated)) {
            throw new ShouldBeDefibrillatedException;
        }

        return $this->setStatusAndSave(
            status: $this->status->defibrillate()
        );
    }

    public function setStatus(BackedEnum|ShouldBeDefibrillated $status, ?string $payload = null): self
    {
        $this->status = $status;
        $this->statusPayload = $payload;

        return $this;
    }

    public function setStatusAndSave(BackedEnum|ShouldBeDefibrillated $status, ?string $payload = null): self
    {
        $this->setStatus($status, $payload);
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
