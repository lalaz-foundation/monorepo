<?php

declare(strict_types=1);

namespace Lalaz\Runtime\Http\Request;

use Lalaz\Web\Http\Contracts\RequestFactoryInterface;
use Lalaz\Web\Http\Contracts\RequestInterface;
use Lalaz\Web\Http\Request;

/**
 * SAPI request factory implementation.
 *
 * Creates Request objects from PHP superglobals ($_GET, $_POST,
 * $_SERVER, $_COOKIE, $_FILES). This is the standard factory
 * for handling incoming HTTP requests in web server environments.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
final class SapiRequestFactory implements RequestFactoryInterface
{
    /**
     * Create a Request instance from PHP superglobals.
     *
     * Delegates to Request::fromGlobals() to construct a request
     * object populated with data from $_GET, $_POST, $_SERVER,
     * $_COOKIE, and $_FILES.
     *
     * @return RequestInterface The constructed request instance.
     */
    public function fromGlobals(): RequestInterface
    {
        return Request::fromGlobals();
    }
}
