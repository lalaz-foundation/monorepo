<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Common;

/**
 * Base test case for Framework package integration tests.
 *
 * Extends FrameworkUnitTestCase with additional traits for
 * tests that need to interact with the container, configuration,
 * and HTTP layer.
 *
 * Use this when testing components that work together,
 * such as middleware pipelines, route handling, etc.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
abstract class FrameworkIntegrationTestCase extends FrameworkUnitTestCase
{
    use InteractsWithContainer;
    use InteractsWithConfig;
    use CreatesTempFiles;

    /**
     * Get the list of setup methods to call.
     *
     * @return array<int, string>
     */
    protected function getSetUpMethods(): array
    {
        return [
            'setUpConfig',
            'setUpContainer',
        ];
    }

    /**
     * Get the list of teardown methods to call.
     *
     * @return array<int, string>
     */
    protected function getTearDownMethods(): array
    {
        return [
            'tearDownConfig',
            'tearDownContainer',
            'tearDownTempFiles',
        ];
    }
}
