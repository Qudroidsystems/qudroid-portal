<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;

/** Base for every mobile API (v1) controller. See RespondsWithApi for the response envelope. */
abstract class ApiController extends Controller
{
    use RespondsWithApi;
}
