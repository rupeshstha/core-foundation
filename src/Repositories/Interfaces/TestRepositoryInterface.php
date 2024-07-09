<?php

namespace CoreFoundation\Repositories\Interfaces;

use CoreFoundation\Contracts\BaseRepositoryInterface;
use CoreFoundation\Repositories\TestRepository;
use CoreFoundation\Attributes\BulkBind;

#[BulkBind(TestRepository::class)]
interface TestRepositoryInterface extends BaseRepositoryInterface
{
}
