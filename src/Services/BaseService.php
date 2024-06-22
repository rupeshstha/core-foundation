<?php

namespace CoreFoundation\Services;

use CoreFoundation\Traits\HasEvent;
use CoreFoundation\Manipulators\ObjectMutable;
use CoreFoundation\Traits\HasCacheable;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

abstract class BaseService
{
    use HasCacheable;
    use HasEvent;

    protected ObjectMutable $objectMutable;

    /**
     * Validate with custom attributes data.
     *
     * Sometimes you need to validate data inside business logic, You can use this method to validate data
     *
     * @param array $data
     * @param array $rules
     * @param array $messages
     * @param array $customAttributes
     *
     * @return array
     */
    public function validate(
        array $data,
        array $rules,
        array $messages = [],
        array $customAttributes = []
    ): array {
        try {
            $validator = Validator::make($data, $rules, $messages, $customAttributes);
            if ($validator->fails()) {
                throw ValidationException::withMessages($validator->errors()->toArray());
            }
            $validated = $validator->validated();
        } catch (Exception $exception) {
            throw $exception;
        }

        return $validated;
    }

    public function before(mixed ...$data): mixed
    {
        dd($data);
    }

    public function after(mixed ...$data): mixed
    {
    }
}
