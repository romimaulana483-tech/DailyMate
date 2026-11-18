<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Read a query-string value only when it is a scalar string.
     * Array input (for example ?q[]=x) is ignored instead of causing a 500.
     */
    protected function queryString(Request $request, string $key, ?string $default = null): ?string
    {
        $value = $request->query($key, $default);

        return is_string($value) ? $value : $default;
    }
}
