<?php
declare(strict_types=1);

namespace App\Interface\Http\Controllers;

use App\Interface\Http\Response;

abstract class BaseController
{
    protected function ok(array $data = [], int $status = 200): Response
    {
        return Response::json(true, $data, [], $status);
    }

    protected function fail(string $code, string $message, int $status = 400): Response
    {
        return Response::json(false, [], [['code' => $code, 'message' => $message]], $status);
    }
}
