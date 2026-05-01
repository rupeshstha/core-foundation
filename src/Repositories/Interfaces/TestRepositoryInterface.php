<?php

namespace CoreFoundation\Repositories\Interfaces;

use CoreFoundation\Attributes\BatchRegistrar;
use CoreFoundation\Contracts\BaseRepositoryInterface;
use CoreFoundation\Repositories\TestRepository;

#[BatchRegistrar(TestRepository::class)]
interface TestRepositoryInterface extends BaseRepositoryInterface {}
