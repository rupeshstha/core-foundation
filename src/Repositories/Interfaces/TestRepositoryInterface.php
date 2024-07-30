<?php

namespace CoreFoundation\Repositories\Interfaces;

use CoreFoundation\Contracts\BaseRepositoryInterface;
use CoreFoundation\Repositories\TestRepository;
use CoreFoundation\Attributes\BatchRegistrar;

#[BatchRegistrar(TestRepository::class)]
interface TestRepositoryInterface extends BaseRepositoryInterface
{
}
