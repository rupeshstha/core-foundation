# Streamlined Foundations for Robust API

[![Latest Version on Packagist](https://img.shields.io/packagist/v/rupeshstha/core-foundation.svg?style=flat-square)](https://packagist.org/packages/rupeshstha/core-foundation)
[![Total Downloads](https://img.shields.io/packagist/dt/rupeshstha/core-foundation.svg?style=flat-square)](https://packagist.org/packages/rupeshstha/core-foundation)
![GitHub Actions](https://github.com/rupeshstha/core-foundation/actions/workflows/main.yml/badge.svg)

A foundational package for streamlined development, offering essential core functionality and components to accelerate project creation and enhance scalability.

## Installation

You can install the package via composer:

```bash
composer require rupeshstha/core-foundation
```

## Usage

This package offers to build your amazing project by uplifting heavy work. Also this package will help you to DRY your code.

#### BaseController
Here is how it would looks like on your controller. You just need to extend BaseController
```php
use Rupeshstha/Http/Controllers/BaseController;

class UserController extends BaseController
{
    public function __construct(
        protected UserService $userService,
        protected UserResource $userResource,
        protected UserCollection $userCollection
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        try {
            // Your amazing code, something like this.
            $filterable = $request->query();
            $users = $this->userService->index($filterable);
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }

        return $this->successResponse(
            message: $this->lang("fetch-success"),
            payload: $this->userCollection->collection($users)
        );
    }
}

```
#### BaseRequest
All the validation 


### Coding Preference
How do i prefer writing code?
Well, I prefer to write code under PSR-12 standard but in some case i have my own preferences.
#### My Coding practice

- Variable and function names should always be in the camel case.
- use illuminate's response class for HTTP code.
    ```php
    use Illuminate\Http\Response;

    Response::HTTP_CREATED;
    ```
- If we multi-lined the function params then it should declare type hinting.
  - If there are more than 3 prams then they should be multi-lined.
  - (Prefered multi-lined with type hinting if there are more than 2 parameters to fulfill).
    * NOTE::This is conditional.
- Leave an extra line at the end of the file (PSR-12).
- Use a double quote for string if you need to concat variables. ie
    ```php
    $fullName = "{$fistName} {$lastName}";
    ```
    ```php
    class SomeMagicClass
    {
        public function doSomeMagic(string $method, array $parameter): object
        {
            return $this->driver()->{$method}(...$parameter);
        }
    }
    ```

    - NOTE::Strictly add parenthesis `{}` on variable.
  - (Prefered double quote on a string to maintain consistency).
- multiline if condition if there is more than one condition.
  - Format example:

  ```php
  if (
    !isset($data["zip_code"])
    && !$data["use_zip_range"]
  ) {
    $data["zip_code"] = "*";
  } elseif (
    // conditions
    && // conditions
  ) {
    //code..
  } else {
    //code..
  }
  ```
- modelkey in the repository name is separated with `"."` full stop.
- There should not be a comma at the end of the parameter on methods.
  - A comma is only strictly used on the last of the array index.
- Do not use `__()` it for translations.
  * Do's
    ```php
    trans("core::app.core.response.user.invalid-password", ["name" => "User"]);
    ```
  * Don't
    ```php
    __("core::app.core.response.user.invalid-password", ["name" => "User"]);
    ```
  * Preferred to use trait ResponseMessage.
  ```php
  class SomeMagicClass
  {
    use ResponseMessage;

    public function doSomeMagic(): string
    {
        //some magic code...
        return $this->lang("fetch.success", ["name" => "User"]);
    }
  }
  ```

## Config

- The config file should strictly follow proper naming convention
    - Config file name should use `_` as seperator and lower case, ie `"attribute_types.php"`
- It should be binded in service provider on boot method.
- It should be publishable.
- Format example
  ```php
  public function registerConfig(): void
  {
    // To publish config file, this is necessary for packaging.
    $this->publishes([
      module_path($this->moduleName, "Config/strategy.php") => $this->getConfigPath("stragtegy"),
    ], "strategy");

    // To add or merge config file.
    $this->mergeConfigFrom(
      path: module_path($this->moduleName, "Config/strategy.php"),
      key: "strategy"
    );
  }
  ```

## Routes

- NOTE::Api route file api.php prefix `"api"` has been removed.
- Prefix `"bo"` ie **back office** and `"sf"` ie **store front**.
- All the groups ie middleware, prefix, name should be written as functions.
- Use `apiResource` instead of `resource`.
- Do not add `"/"` at first URL
  - Do's
  ```php
  Route::get("me", [ProfileController::class, "me"])->name("me")

  // prefixed then need /
  Route::prefix("me")
    ->name("me")
    ->group(function () {
      Route::get("/", [AccountController::class, "show"])
        ->name("me.show");
      Route::put("/", [AccountController::class, "update"])
        ->name("me.update");
    }
  );
  ```
  - Don't
  ```php
  Route::get("/me", [ProfileController::class, "myProfile"])->name("me.profile");

  Route::prefix("me")
    ->name("me")
    ->group(function () {
      Route::get("", [AccountController::class, "show"])
        ->name("me.show");
      Route::put("", [AccountController::class, "update"])
        ->name("me.update");
    }
  );
  ```


- NOTE::Give proper indentation.

  #### BO format example:

  ```php
  Route::middleware(["api"])
    ->prefix("bo")
    ->name("bo")
    ->group(function () {
        Route::prefix("user")
            ->name("user")
            ->group(function () {
                Route::apiResource("admins", UserController::class);
                Route::apiResource("roles", RoleController::class);
            }
        );
    }
  );
  ```

  #### SF format example

  ```php
  Route::middleware(["api"])
    ->prefix("sf")
    ->name("sf.user")
    ->group(function () {
        Route::put("me", [AccountController::class, "update"])->name("me.update");
    }
  );
  ```

## Classes

- Construct DI should be multi-lined.
- If the class is made then it should specify its functionality with proper naming convention and folder structure.

## Controllers

- Methods should strictly have a try-catch.
- Catch block should return handleException method.
    ```php
    try {
        // some magic code
    } catch (Exception $exception) {
        return $this->handleException($exception);
    }
    ```
- After a try-catch block, it should always return JsonResponse.
  - Format example:

  ```php
  return $this->successResponse(
    message: $this->lang("fetch-success", ["email" => $user->email]),
    responseCode: Response::HTTP_OK
  );
  ```
- It should always extend ResourceController or BaseController according to the requirement
  - If there is normal CRUD then extend ResourceController.
- It should always inject model and repository interfaces.
- It should always initialize the base controller's construct.
    ```php
    public function __construct(
        protected UserService $userService,
        protected UserResource $userResource,
        protected UserCollection $userCollection
    ) {
    }
    ```

## Models

- Add `SEARCHABLE` for searchable/filtrable.
    ```php
    public const SEARCHABLE = [
        "name",
        "sku",
    ];
    ```
- Relations should be declared with proper formatting.
  - It should be in camel case.
  - It should always have a return type.
    - Format example:

    ```php
    public function user(): BelongsTo
    {
        return $this->belongsTo(
            related: User::class,
            foreignKey: "user_id",
        );
    }
    ```

## Repositories

- It should always extends BaseRepository and implements its own interface.
  - Interfaces should bind in RepositoryServiceProvider
    - format example

    ```php
    $this->app->bind(
        abstract: UserRepositoryInterface::class,
        concrete: UserRepository::class
    );
    ```
  - NOTE::Every module should have its own RepositoryServiceProvider
  - The interface should always extends BaseRepositoryInterface.

## Service Classes

- It should always extends BaseService
- All the business logic should be written in these classes.



### Testing

```bash
composer test
```

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email rupeshshrestha9818@gmail.com instead of using the issue tracker.

## Credits

-   [Rupesh Shrestha](https://github.com/rupeshstha)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
