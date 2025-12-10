<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Web\Routing;

use Lalaz\Web\Routing\Attribute\Route;
use Lalaz\Web\Routing\Router;
use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Route::class)]
/**
 * Tests for attribute-based routing.
 */
final class AttributeRouteTest extends FrameworkUnitTestCase
{
    public function testregistersControllersViaAttributes(): void
    {
        $router = new Router();
        $router->registerControllers([AttributeTestController::class]);

        $match = $router->match("GET", "/attr");
        $this->assertSame([AttributeTestController::class, "index"], $match->definition()->handler());

        $matchParam = $router->match("DELETE", "/items/5");
        $this->assertSame(["id" => "5"], $matchParam->params());
    }
}

class AttributeTestController
{
    #[Route(path: "/attr", method: "GET")]
    public function index(): void {}

    #[Route(path: "/items/{id}", methods: ["GET", "DELETE"])]
    public function handle(): void {}
}
