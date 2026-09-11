<?php

namespace Perfocard\Flow\Support;

use Perfocard\Flow\Exceptions\UnexpectedModelException;
use Perfocard\Flow\Models\FlowModel;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Builds a Callback / Endpoint / Task instance for a given model.
 *
 * The model is a constructor dependency of the concrete handler, typed with
 * the leaf class (`__construct(protected Payment $payment)`). Constructors are
 * exempt from PHP's signature compatibility checks, which is what lets the
 * handler narrow the type while the Flow contracts stay strict.
 */
class HandlerResolver
{
    /**
     * Cached constructor lookups, keyed by handler class.
     *
     * @var array<string, array{string, string}|null>
     */
    protected static array $parameters = [];

    /**
     * Resolve the handler through the container with the model bound to the
     * constructor parameter that types it. Remaining dependencies resolve
     * through normal dependency injection.
     */
    public static function resolve(string $handler, FlowModel $model): object
    {
        $parameter = static::modelParameter($handler);

        if ($parameter === null) {
            return app($handler);
        }

        [$name, $type] = $parameter;

        if (! $model instanceof $type) {
            throw new UnexpectedModelException($handler, $type, $model::class);
        }

        return app($handler, [$name => $model]);
    }

    /**
     * Forget the cached constructor lookups.
     */
    public static function flush(): void
    {
        static::$parameters = [];
    }

    /**
     * Find the first constructor parameter typed as a FlowModel.
     *
     * @return array{string, string}|null Parameter name and declared type
     */
    protected static function modelParameter(string $handler): ?array
    {
        if (array_key_exists($handler, static::$parameters)) {
            return static::$parameters[$handler];
        }

        $constructor = (new ReflectionClass($handler))->getConstructor();

        foreach ($constructor?->getParameters() ?? [] as $parameter) {
            $type = $parameter->getType();

            if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            if (is_a($type->getName(), FlowModel::class, true)) {
                return static::$parameters[$handler] = [$parameter->getName(), $type->getName()];
            }
        }

        return static::$parameters[$handler] = null;
    }
}
