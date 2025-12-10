<?php

declare(strict_types=1);

namespace Lalaz\Web\Tests;

use Lalaz\Web\Tests\Common\WebUnitTestCase;

/**
 * Legacy test case alias.
 *
 * @deprecated Use WebUnitTestCase or WebIntegrationTestCase directly.
 * @package lalaz/web
 */
abstract class TestCase extends WebUnitTestCase
{
    // This class exists for backwards compatibility.
    // New tests should extend WebUnitTestCase or WebIntegrationTestCase directly.
}
