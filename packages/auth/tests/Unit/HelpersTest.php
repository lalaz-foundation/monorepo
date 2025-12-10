<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Unit;

use Lalaz\Auth\Tests\Common\AuthUnitTestCase;
use PHPUnit\Framework\Attributes\Test;
use Lalaz\Auth\AuthManager;
use Lalaz\Auth\AuthContext;
use Lalaz\Auth\GuardContext;

/**
 * Tests for auth helper functions.
 *
 * These tests verify that the global helper functions work correctly
 * and handle edge cases gracefully.
 */
final class HelpersTest extends AuthUnitTestCase
{
    protected function setUp(): void
    {
        // Include helpers if not already loaded
        require_once dirname(__DIR__, 2) . '/src/helpers.php';
    }

    // ===== Function existence tests =====

    #[Test]
    public function auth_function_exists(): void
    {
        $this->assertTrue(function_exists('auth'));
    }

    #[Test]
    public function auth_context_function_exists(): void
    {
        $this->assertTrue(function_exists('auth_context'));
    }

    #[Test]
    public function user_function_exists(): void
    {
        $this->assertTrue(function_exists('user'));
    }

    #[Test]
    public function authenticated_function_exists(): void
    {
        $this->assertTrue(function_exists('authenticated'));
    }

    #[Test]
    public function guest_function_exists(): void
    {
        $this->assertTrue(function_exists('guest'));
    }

    // ===== Function signature tests =====

    #[Test]
    public function auth_function_signature_is_correct(): void
    {
        $reflection = new \ReflectionFunction('auth');

        $this->assertCount(1, $reflection->getParameters());

        $param = $reflection->getParameters()[0];
        $this->assertSame('guard', $param->getName());
        $this->assertTrue($param->allowsNull());
        $this->assertTrue($param->isDefaultValueAvailable());
        $this->assertNull($param->getDefaultValue());
    }

    #[Test]
    public function auth_context_function_signature_is_correct(): void
    {
        $reflection = new \ReflectionFunction('auth_context');

        $this->assertCount(0, $reflection->getParameters());
    }

    #[Test]
    public function user_function_signature_is_correct(): void
    {
        $reflection = new \ReflectionFunction('user');

        $this->assertCount(1, $reflection->getParameters());

        $param = $reflection->getParameters()[0];
        $this->assertSame('guard', $param->getName());
        $this->assertTrue($param->allowsNull());
    }

    #[Test]
    public function authenticated_function_signature_is_correct(): void
    {
        $reflection = new \ReflectionFunction('authenticated');

        $this->assertCount(1, $reflection->getParameters());

        $param = $reflection->getParameters()[0];
        $this->assertSame('guard', $param->getName());
        $this->assertTrue($param->allowsNull());
    }

    #[Test]
    public function guest_function_signature_is_correct(): void
    {
        $reflection = new \ReflectionFunction('guest');

        $this->assertCount(1, $reflection->getParameters());

        $param = $reflection->getParameters()[0];
        $this->assertSame('guard', $param->getName());
        $this->assertTrue($param->allowsNull());
    }

    // ===== Return type tests =====

    #[Test]
    public function auth_return_type_includes_auth_manager(): void
    {
        $reflection = new \ReflectionFunction('auth');
        $returnType = $reflection->getReturnType();

        $this->assertInstanceOf(\ReflectionUnionType::class, $returnType);

        $types = array_map(fn($t) => $t->getName(), $returnType->getTypes());

        $this->assertContains(AuthManager::class, $types);
        $this->assertContains(GuardContext::class, $types);
        $this->assertContains('null', $types);
    }

    #[Test]
    public function auth_context_return_type_is_nullable_auth_context(): void
    {
        $reflection = new \ReflectionFunction('auth_context');
        $returnType = $reflection->getReturnType();

        $this->assertTrue($returnType->allowsNull());
        $this->assertSame(AuthContext::class, $returnType->getName());
    }

    #[Test]
    public function user_return_type_is_mixed(): void
    {
        $reflection = new \ReflectionFunction('user');
        $returnType = $reflection->getReturnType();

        $this->assertSame('mixed', $returnType->getName());
    }

    #[Test]
    public function authenticated_return_type_is_bool(): void
    {
        $reflection = new \ReflectionFunction('authenticated');
        $returnType = $reflection->getReturnType();

        $this->assertSame('bool', $returnType->getName());
    }

    #[Test]
    public function guest_return_type_is_bool(): void
    {
        $reflection = new \ReflectionFunction('guest');
        $returnType = $reflection->getReturnType();

        $this->assertSame('bool', $returnType->getName());
    }

    // ===== Behavior tests (without container) =====

    #[Test]
    public function helpers_handle_null_gracefully(): void
    {
        // Without container, these should not throw exceptions
        // and should return safe default values
        $this->assertNull(user());
        $this->assertFalse(authenticated());
        $this->assertTrue(guest());
    }

    #[Test]
    public function helpers_are_defined_only_once(): void
    {
        // Loading helpers multiple times should not cause errors
        require_once dirname(__DIR__, 2) . '/src/helpers.php';
        require_once dirname(__DIR__, 2) . '/src/helpers.php';

        $this->assertTrue(function_exists('auth'));
        $this->assertTrue(function_exists('auth_context'));
        $this->assertTrue(function_exists('user'));
        $this->assertTrue(function_exists('authenticated'));
        $this->assertTrue(function_exists('guest'));
    }

    #[Test]
    public function all_helper_functions_are_globally_available(): void
    {
        $expectedFunctions = [
            'auth',
            'auth_context',
            'user',
            'authenticated',
            'guest',
        ];

        foreach ($expectedFunctions as $fn) {
            $this->assertTrue(
                function_exists($fn),
                "Function {$fn}() should be globally available"
            );
        }
    }

    #[Test]
    public function auth_accepts_null_guard_explicitly(): void
    {
        // Should not throw
        $result = auth(null);

        // Result depends on resolve availability
        $this->assertTrue($result === null || $result instanceof AuthManager);
    }

    #[Test]
    public function auth_accepts_string_guard(): void
    {
        // Should not throw
        $result = auth('web');

        // Result depends on resolve availability
        $this->assertTrue($result === null || $result instanceof GuardContext);
    }
}

/**
 * Integration tests for helper functions with real AuthContext.
 */
final class HelpersIntegrationTest extends AuthUnitTestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/src/helpers.php';
    }

    #[Test]
    public function auth_context_class_works_with_user(): void
    {
        // This test verifies the AuthContext logic that helpers use
        $context = new AuthContext();
        $fakeUser = (object)['id' => 1, 'name' => 'Test User'];

        $context->setUser($fakeUser);

        // Verify the context works as expected (which user() would use)
        $this->assertSame($fakeUser, $context->user());
        $this->assertTrue($context->isAuthenticated());
        $this->assertFalse($context->isGuest());
    }

    #[Test]
    public function auth_context_is_authenticated_logic(): void
    {
        $context = new AuthContext();

        // Without user
        $this->assertFalse($context->isAuthenticated());

        // With user
        $context->setUser((object)['id' => 1]);
        $this->assertTrue($context->isAuthenticated());
    }

    #[Test]
    public function auth_context_is_guest_logic(): void
    {
        $context = new AuthContext();

        // Without user
        $this->assertTrue($context->isGuest());

        // With user
        $context->setUser((object)['id' => 1]);
        $this->assertFalse($context->isGuest());
    }

    #[Test]
    public function auth_context_with_specific_guard(): void
    {
        $context = new AuthContext();

        // Set user for specific guard
        $apiUser = (object)['id' => 2, 'type' => 'api'];
        $context->setUser($apiUser, 'api');

        // Verify context method (which user('api') would call)
        $this->assertSame($apiUser, $context->user('api'));
        $this->assertNull($context->user('web')); // Different guard
    }

    #[Test]
    public function guard_context_provides_chained_access(): void
    {
        $context = new AuthContext();
        $fakeUser = (object)['id' => 1];
        $context->setUser($fakeUser, 'api');

        // Get guard context (which auth('api') would return)
        $guardContext = $context->guard('api');

        $this->assertInstanceOf(GuardContext::class, $guardContext);
        $this->assertSame($fakeUser, $guardContext->user());
        $this->assertTrue($guardContext->check());
        $this->assertFalse($guardContext->guest());
    }
}

/**
 * Tests for helper function edge cases.
 */
final class HelpersEdgeCasesTest extends AuthUnitTestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/src/helpers.php';
    }

    #[Test]
    public function guest_is_opposite_of_authenticated(): void
    {
        // Without resolve/container, verify default behavior
        $isAuthenticated = authenticated();
        $isGuest = guest();

        // They should be opposites
        $this->assertNotSame($isAuthenticated, $isGuest);
    }

    #[Test]
    public function user_accepts_empty_string_guard(): void
    {
        // Should not throw, treated as valid guard name
        $result = user('');

        $this->assertNull($result);
    }

    #[Test]
    public function authenticated_with_non_existent_guard_returns_false(): void
    {
        $result = authenticated('non_existent_guard');

        $this->assertFalse($result);
    }

    #[Test]
    public function guest_with_non_existent_guard_returns_true(): void
    {
        $result = guest('non_existent_guard');

        $this->assertTrue($result);
    }

    #[Test]
    public function auth_function_handles_exception_gracefully(): void
    {
        // Even if resolve fails internally, auth() should return null
        // This is a safety test
        $result = auth('any_guard');

        $this->assertTrue($result === null || $result instanceof GuardContext);
    }

    #[Test]
    public function auth_context_returns_null_or_instance(): void
    {
        $result = auth_context();

        $this->assertTrue($result === null || $result instanceof AuthContext);
    }
}
