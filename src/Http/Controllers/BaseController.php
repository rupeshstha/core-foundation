<?php

namespace CoreFoundation\Http\Controllers;

use CoreFoundation\Traits\HasLang;
use Illuminate\Routing\Controller;
use CoreFoundation\Traits\HasApiResponse;
use CoreFoundation\Traits\HasExceptionHandler;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * BaseController
 *
 * Foundation controller for all API controllers.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ CAPABILITIES                                                                │
 * │                                                                             │
 * │ HasApiResponse     → successResponse(), createdResponse(),                  │
 * │                      paginatedResponse(), noContentResponse()               │
 * │                      All return JsonResponse with the agreed envelope.      │
 * │                                                                             │
 * │ HasExceptionHandler → handleException(Throwable $e)                         │
 * │                       Maps known exceptions to status codes + messages.     │
 * │                       Generates UUID for fatal (unexpected) exceptions.     │
 * │                       Override knownExceptions() to add domain exceptions.  │
 * │                                                                             │
 * │ HasLang            → $this->lang('key')                                     │
 * │                      Resolves to trans('{controller-prefix}.key').          │
 * │                      UserController → trans('user.key').                    │
 * │                      Override langPrefix() to customise.                    │
 * │                                                                             │
 * │ AuthorizesRequests → $this->authorize(), $this->authorizeResource()         │
 * │                      Standard Laravel policy authorisation.                 │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ USAGE PATTERN                                                               │
 * │                                                                             │
 * │   class UserController extends BaseController                               │
 * │   {                                                                         │
 * │       public function __construct(                                          │
 * │           private readonly UserService $userService,                        │
 * │       ) {}                                                                  │
 * │                                                                             │
 * │       public function index(Request $request): JsonResponse                 │
 * │       {                                                                     │
 * │           try {                                                             │
 * │               $users = $this->userService->index($request->query());        │
 * │           } catch (Throwable $e) {                                          │
 * │               return $this->handleException($e);                            │
 * │           }                                                                 │
 * │                                                                             │
 * │           return $this->successResponse(                                    │
 * │               message: $this->lang('fetch-success'),                        │
 * │               payload: UserResource::collection($users),                    │
 * │           );                                                                │
 * │       }                                                                     │
 * │                                                                             │
 * │       public function store(StoreUserRequest $request): JsonResponse        │
 * │       {                                                                     │
 * │           try {                                                             │
 * │               $user = $this->userService->create($request->validated());    │
 * │           } catch (Throwable $e) {                                          │
 * │               return $this->handleException($e);                            │
 * │           }                                                                 │
 * │                                                                             │
 * │           return $this->createdResponse(                                    │
 * │               message: $this->lang('create-success'),                       │
 * │               payload: new UserResource($user),                             │
 * │           );                                                                │
 * │       }                                                                     │
 * │                                                                             │
 * │       public function destroy(User $user): JsonResponse                     │
 * │       {                                                                     │
 * │           try {                                                             │
 * │               $this->userService->delete($user);                            │
 * │           } catch (Throwable $e) {                                          │
 * │               return $this->handleException($e);                            │
 * │           }                                                                 │
 * │                                                                             │
 * │           return $this->noContentResponse();                                │
 * │       }                                                                     │
 * │   }                                                                         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ DOMAIN EXCEPTIONS — DO NOT MAP IN CONTROLLERS                               │
 * │                                                                             │
 * │ Domain exceptions (InsufficientInventory, PaymentDeclined, etc.) should     │
 * │ extend BaseApiException and carry their own render() logic.                 │
 * │ Laravel calls render() automatically — no controller mapping needed.        │
 * │                                                                             │
 * │   class InsufficientInventoryException extends BaseApiException             │
 * │   {                                                                         │
 * │       public function render(Request $request): JsonResponse                │
 * │       {                                                                     │
 * │           return response()->json([                                         │
 * │               'message' => 'Insufficient inventory for this order.',        │
 * │               'errors'  => [],                                              │
 * │           ], 422);                                                          │
 * │       }                                                                     │
 * │   }                                                                         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ TRANSLATION FILE CONVENTION                                                 │
 * │                                                                             │
 * │   lang/en/user.php:                                                         │
 * │   return [                                                                  │
 * │       'fetch-success'  => 'Users fetched successfully.',                    │
 * │       'create-success' => 'User created successfully.',                     │
 * │       'update-success' => 'User updated successfully.',                     │
 * │       'delete-success' => 'User deleted successfully.',                     │
 * │   ];                                                                        │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
abstract class BaseController extends Controller
{
    use AuthorizesRequests;
    use HasApiResponse;
    use HasExceptionHandler;
    use HasLang;

    protected function baseClassSuffix(): string
    {
        return 'Controller';
    }
}
