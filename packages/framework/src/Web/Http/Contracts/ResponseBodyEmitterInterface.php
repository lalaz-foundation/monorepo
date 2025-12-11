<?php

declare(strict_types=1);

namespace Lalaz\Web\Http\Contracts;

/**
 * Abstraction for emitting HTTP response body chunks.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 */
interface ResponseBodyEmitterInterface
{
    /**
     * Write a chunk of the HTTP response body to the underlying transport.
     *
     * @param string $chunk Arbitrary UTF-8/binary payload chunk.
     * @return void
     */
    public function write(string $chunk): void;
}
