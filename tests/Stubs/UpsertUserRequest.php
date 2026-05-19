<?php

namespace CoreFoundation\Tests\Stubs;

use Illuminate\Validation\Rule;
use CoreFoundation\Http\Requests\BaseRequest;

// =============================================================================
// PATTERN A — One class handles both Store and Update
// Best for: resources where most rules are shared, update just relaxes a few
// =============================================================================

class UpsertUserRequest extends BaseRequest
{
    // Shared between Store and Update
    protected function baseRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'string', Rule::in(['admin', 'editor', 'viewer'])],
        ];
    }

    // POST only — additional fields required on create
    protected function storeRules(): array
    {
        return [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    // PUT/PATCH — relax required fields, handle unique ignore
    protected function updateRules(): array
    {
        return [
            // Override baseRules()'s 'required' → 'sometimes' for partial updates
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes', 'email', 'max:255',
                // Developer handles unique ignore themselves using routeModel()
                Rule::unique('users', 'email')->ignore($this->routeModel('user')),
            ],
            'role' => ['sometimes', 'string', Rule::in(['admin', 'editor', 'viewer'])],
            'password' => ['sometimes', 'string', 'min:8', 'confirmed'],
        ];
    }

    // Optional: merge route parameters into validated data
    protected function prepareForValidation(): void
    {
        if ($this->isUpdating()) {
            $this->mergeRouteParameters(['user']);
        }
    }

    // Optional: custom messages
    public function messages(): array
    {
        return [
            'email.unique' => 'This email address is already taken.',
        ];
    }
}

// =============================================================================
// PATTERN B — Separate classes per lifecycle
// Best for: resources where Store and Update diverge significantly
// =============================================================================

class StoreUserRequest extends BaseRequest
{
    protected function baseRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', Rule::in(['admin', 'editor', 'viewer'])],
        ];
    }
}

class UpdateUserRequest extends BaseRequest
{
    protected function baseRules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes', 'email',
                Rule::unique('users', 'email')->ignore($this->routeModel('user')),
            ],
            'password' => ['sometimes', 'string', 'min:8', 'confirmed'],
            'role' => ['sometimes', 'string', Rule::in(['admin', 'editor', 'viewer'])],
        ];
    }
}
