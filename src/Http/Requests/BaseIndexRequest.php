<?php

namespace CoreFoundation\Http\Requests;

/**
 * BaseIndexRequest
 *
 * Foundation request for index / listing endpoints.
 *
 * Validates the universal query parameters (sort, per_page).
 * Operator-prefixed filter keys (__eq_, __like_, etc.) are dynamic by design —
 * their column-level security is enforced downstream by FilterApplicator's
 * searchable whitelist, so they are extracted without pre-validation here.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ USAGE                                                                       │
 * │                                                                             │
 * │   // Domain request — add resource-specific rules here:                     │
 * │   class UserIndexRequest extends BaseIndexRequest {}                        │
 * │                                                                             │
 * │   // Controller:                                                             │
 * │   public function index(UserIndexRequest $request): JsonResponse            │
 * │   {                                                                         │
 * │       $users = $this->repository->fetchAll(                                  │
 * │           criteria: $request->criteria(),                                   │
 * │           perPage:  $request->perPage(),                                    │
 * │       );                                                                     │
 * │   }                                                                         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ QUERY STRING FORMAT                                                         │
 * │                                                                             │
 * │ Filters (operator-prefixed, any searchable column):                         │
 * │   ?__eq_status=active&__like_name=john&__in_role[]=admin&__in_role[]=staff  │
 * │                                                                             │
 * │ Sort (prefix '-' = descending):                                             │
 * │   ?sort[]=name&sort[]=-created_at                                           │
 * │   ?sort[name]=asc&sort[created_at]=desc                                     │
 * │                                                                             │
 * │ Pagination:                                                                 │
 * │   ?per_page=50                                                              │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
abstract class BaseIndexRequest extends BaseRequest
{
    protected function baseRules(): array
    {
        return [
            'sort' => ['sometimes', 'array'],
            'sort.*' => ['string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Build the criteria array for BaseRepository::fetchAll().
     *
     * @return array{filters: array<string, mixed>, sort: array}
     */
    public function criteria(): array
    {
        return [
            'filters' => $this->extractFilters(),
            'sort' => $this->validated('sort', []),
        ];
    }

    /**
     * Validated per_page value, defaulting to 25.
     */
    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 25);
    }

    /**
     * Extract all operator-prefixed keys from the raw request input.
     *
     * Keys like __eq_name, __like_email, __in_status are passed as-is to
     * FilterApplicator. Column-level security (searchable whitelist) is its job.
     *
     * @return array<string, mixed>
     */
    private function extractFilters(): array
    {
        return collect($this->all())
            ->filter(fn ($value, $key) => str_starts_with((string) $key, '__'))
            ->all();
    }
}
