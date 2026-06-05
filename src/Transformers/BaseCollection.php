<?php

namespace CoreFoundation\Transformers;

use LogicException;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * BaseCollection
 *
 * Abstract JSON API collection transformer. Enforces explicit resource class
 * declaration and disables Laravel's default "data" wrapping.
 *
 * USAGE:
 *
 *   final class UserCollection extends BaseCollection
 *   {
 *       public $collects = UserResource::class;
 *   }
 *
 * In a controller — non-paginated:
 *
 *   return $this->successResponse('Users fetched.', new UserCollection($users));
 *
 * In a controller — paginated:
 *
 *   return $this->paginatedResponse('Users fetched.', new UserCollection($paginator));
 *
 * Add computed collection-level fields (override toArray() only for this):
 *
 *   final class OrderCollection extends BaseCollection
 *   {
 *       public string $collects = OrderResource::class;
 *
 *       public function toArray(Request $request): array
 *       {
 *           return [
 *               'items'   => parent::toArray($request),
 *               'summary' => $this->collection->sum('total'),
 *           ];
 *       }
 *   }
 */
abstract class BaseCollection extends ResourceCollection
{
    /**
     * Disable Laravel's default "data" wrapping.
     * BaseController::successResponse() / paginatedResponse() owns the response envelope.
     */
    public static $wrap = null;

    /**
     * Enforce explicit $collects declaration — no naming-convention magic.
     *
     * @throws LogicException when the child class does not declare $collects.
     */
    public function __construct(mixed $resource)
    {
        if (! isset($this->collects)) {
            throw new LogicException(
                static::class.' must declare: public string $collects = YourResource::class;'
            );
        }

        parent::__construct($resource);
    }
}
