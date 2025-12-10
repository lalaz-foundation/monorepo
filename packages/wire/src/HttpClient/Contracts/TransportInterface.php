<?php

declare(strict_types=1);

namespace Lalaz\Wire\HttpClient\Contracts;

use Lalaz\Wire\HttpClient\HttpRequest;

interface TransportInterface
{
    /**
     * @return array{status:int, headers:array<string,string>, body:mixed}
     */
    public function send(HttpRequest $request): array;
}
