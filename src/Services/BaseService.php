<?php

namespace CoreFoundation\Services;

use CoreFoundation\Traits\HasEvent;
use CoreFoundation\Traits\HasFactory;
use CoreFoundation\Traits\HasCacheable;
use CoreFoundation\Manipulators\ObjectMutable;

abstract class BaseService
{
    use HasCacheable;
    use HasEvent;
    use HasFactory;

    protected ObjectMutable $objectMutable;
}
