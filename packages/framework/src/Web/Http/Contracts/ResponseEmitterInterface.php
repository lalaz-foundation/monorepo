<?php

declare(strict_types=1);

namespace Lalaz\Web\Http\Contracts;

use Lalaz\Web\Http\Response;

/**
 * Contract for HTTP response emitters capable of flushing body chunks.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 */
interface ResponseEmitterInterface extends ResponseBodyEmitterInterface
{
    /**
     * Emits an entire HTTP response (status, headers, body).
     *
     * @param Response $response
     * @return void
     */
    public function emit(Response $response): void;
}
