<?php

namespace CoreFoundation\Services;

use CoreFoundation\Manipulators\ObjectMutable;
use CoreFoundation\Traits\HasCacheable;
use CoreFoundation\Traits\HasEvent;
use CoreFoundation\Traits\HasFactory;

abstract class BaseService
{
    use HasCacheable;
    use HasEvent;
    use HasFactory;

    protected ObjectMutable $objectMutable;
}
