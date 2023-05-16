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

# BaseController
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
