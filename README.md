# Streamlined Foundations for Robust API

[![Latest Version on Packagist](https://img.shields.io/packagist/v/rupeshstha/core-foundation.svg?style=flat-square)](https://packagist.org/packages/rupeshstha/core-foundation)
[![Total Downloads](https://img.shields.io/packagist/dt/rupeshstha/core-foundation.svg?style=flat-square)](https://packagist.org/packages/rupeshstha/core-foundation)
![GitHub Actions](https://github.com/rupeshstha/core-foundation/actions/workflows/main.yml/badge.svg)

A foundational package for streamlined development, offering essential core functionality and components to accelerate project creation and enhance scalability. This package is built keeping on mind laravel octane, It fully supports laravel octane.

## Installation

You can install the package via composer:

```bash
composer require rupeshstha/core-foundation
```

## Usage

This package offers to build your amazing project by uplifting heavy work. Also this package will help you to DRY your code.
You can always check ![Official Documentation](https://rupesh-shrestha.gitbook.io/core-foundation) for detail information.

### Application Performance Monitoring (APM)
This package also offers basic but must needed APM.

#### Server Timing
This package include server timing facade to monitor application performance. It will adds Server-Timing header information from within your apps.

#### Usage
To add server-timing header information, you need to add the `\CoreFoundation\Http\Middlewares\ServerTimingMiddleware::class`, middleware to your HTTP Kernel. In order to get the most accurate results, put the middleware as the first one to load in the middleware stack.

### Laravel 11
`bootstrap/app.php`
```php
return Application::configure(basePath: dirname(__DIR__))
    // ...
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prepend(\CoreFoundation\Http\Middlewares\ServerTimingMiddleware::class);
    })
    // ...
    ->create();
```

### Laravel 10 and below
`app/Http/Kernel.php`
```php
class Kernel extends HttpKernel
{
    protected $middleware = [
        \CoreFoundation\Http\Middlewares\ServerTimingMiddleware::class,
        // ...
    ];
}
```

---

By default, the middleware measures only three things, to keep it as light-weight as possible:

Bootstrap (time before the middleware gets called)
Application time (time to get a response within the app)
Total (total time before sending out the response)

Once the package is successfully installed, you can see your timing information in the developer tools of your browser. Here's an example from Chrome:


## Adding additional measurements

If you want to provide additional measurements, you can use the start and stop methods. If you do not explicitly stop a measured event, the event will automatically be stopped once the middleware receives your response. This can be useful if you want to measure the time your Blade views take to compile.

```php
ServerTiming::start('Running expensive task');

// Take a nap
sleep(5);

ServerTiming::stop('Running expensive task');
```

If you already know the exact time that you want to set as the measured time, you can use the `setDuration` method. The duration should be set as milliseconds:

```php
ServerTiming::setDuration('Running expensive task', 1200);
```

In addition to providing milliseconds as the duration, you can also pass a callable that will be measured instead:


```php
ServerTiming::setDuration('Running expensive task', function() {
    sleep(5);
});
```
## Adding textual information

You can also use the Server-Timing middleware to only set textual information without providing a duration.

```php
ServerTiming::addMetric('User: '.$user->id);
```
# Coding Preference
How do i prefer writing code?
Well, I prefer to write code under PSR-12 standard but in some case i have my own preferences.

## How it looks like after using this package.

#### BaseController
Here is how it would looks like on your controller. You just need to extend `CoreFoundation/Http/Controllers/BaseController::class`
```php
use CoreFoundation/Http/Controllers/BaseController;

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
Write your own validation on your own request class. You just need to extend `CoreFoundation\Http\Requests\BaseRequest::class`
```php
class YourAmazingCodeRequest extends BaseRequest
{
    public function store(): array
    {
        return [
            "code" => ["required", "unique:users,code"],
            "name" => ["required"]
        ];
    }

    public function update(): array
    {
        $userId = $this->route()->parameter("user");
        $storeRules = $this->store();

        $rules = array_merge($storeRules, [
            "code" => ["sometimes", "unique:application_methods,code,{$userId}"],
        ]);

        return $rules;
    }
}
```
## Repositories
This package offers repository pattern to your project natively. You don't have to worry about the cache and filterable when using offered methods. If you wish to have your own method and implement cache then you should consider to use CacheManager.
```php
// bind repo in service container
$this->app->singleton(
    abstract: UserRepositoryInterface::class,
    concrete: UserRepository::class
);

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    protected function setModel(): string
    {
        return User::class;
    }
}

interface UserRepositoryInterface extends BaseRepositoryInterface
{
}

```

## Service Classes
This package offices service patterns. You can write business logic in your service class. If your application follows EDD then it will make your life easier. It provides bunch of methods that resolves and factories your classes.


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
