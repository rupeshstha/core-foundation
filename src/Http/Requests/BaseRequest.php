<?php

namespace CoreFoundation\Http\Requests;

use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class BaseRequest extends FormRequest
{
    /**
     * Format the errors from the given Validator instance.
     */
    protected function formatErrors(Validator $validator): array
    {
        return $validator->getMessageBag()->toArray();
    }

    /**
     * Handle a failed validation attempt.
     *
     *
     * @throws ValidationException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException($this->response(
            $this->formatErrors($validator)
        ));
    }

    /**
     * Get the proper failed validation response for the request.
     */
    public function response(array $errors): Response
    {
        return new JsonResponse(['message' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [];

        if (in_array($this->method(), ['PUT', 'PATCH'], true)) {
            $rules = $this->update();
        } elseif ($this->method() === 'POST') {
            $rules = $this->store();
        }

        return $rules;
    }

    /**
     * Get the validation rule that apply to store request
     */
    protected function store(): array
    {
        return [];
    }

    /**
     * Get the validation rule that apply to update request
     */
    protected function update(): array
    {
        return $this->store();
    }
}
