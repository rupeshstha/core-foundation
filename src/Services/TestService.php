<?php

namespace CoreFoundation\Services;

use CoreFoundation\Repositories\TestRepository;

class TestService extends BaseService
{
    public function __construct(
        protected TestRepository $testRepository
    ) {
    }

    public function index(array $filterable = [], array $relationship = [])
    {
        $data = $this->testRepository->fetchAll($filterable, $relationship);
        dd($data);
    }
}
