<?php

namespace Rupeshstha\CoreFoundation\Http\Controllers;

use Illuminate\Http\Request;

class TestController
{
    public function __invoke(Request $request)
    {
        dd("asdad");
    }
}
