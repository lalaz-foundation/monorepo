<?php

declare(strict_types=1);

namespace Lalaz\Web\Tests\Common;

use PHPUnit\Framework\TestCase;
use Lalaz\Web\Http\ViewDataBag;

/**
 * Base test case for Web package unit tests.
 *
 * Provides common utilities for testing HTTP, view,
 * and security-related functionality.
 *
 * @package lalaz/web
 */
abstract class WebUnitTestCase extends TestCase
{
    /**
     * Original $_SESSION backup.
     *
     * @var array<string, mixed>
     */
    private array $originalSession = [];

    /**
     * Original $_SERVER backup.
     *
     * @var array<string, mixed>
     */
    private array $originalServer = [];

    /**
     * Original $_COOKIE backup.
     *
     * @var array<string, mixed>
     */
    private array $originalCookie = [];

    /**
     * Original $_POST backup.
     *
     * @var array<string, mixed>
     */
    private array $originalPost = [];

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->backupGlobals();
        $this->initializeSession();
        ViewDataBag::reset();
    }

    /**
     * Clean up the test environment.
     */
    protected function tearDown(): void
    {
        ViewDataBag::reset();
        $this->restoreGlobals();
        parent::tearDown();
    }

    /**
     * Backup global state.
     */
    private function backupGlobals(): void
    {
        $this->originalSession = $_SESSION ?? [];
        $this->originalServer = $_SERVER;
        $this->originalCookie = $_COOKIE;
        $this->originalPost = $_POST;
    }

    /**
     * Restore global state.
     */
    private function restoreGlobals(): void
    {
        $_SESSION = $this->originalSession;
        $_SERVER = $this->originalServer;
        $_COOKIE = $this->originalCookie;
        $_POST = $this->originalPost;
    }

    /**
     * Initialize session for tests.
     */
    private function initializeSession(): void
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        $_SESSION = [];
    }

    // =========================================================================
    // Mock Factories
    // =========================================================================

    /**
     * Create a mock request array.
     *
     * @param string $method HTTP method
     * @param string $path Request path
     * @param array<string, mixed> $body Request body
     * @param array<string, string> $headers Request headers
     * @return array<string, mixed>
     */
    protected function mockRequest(
        string $method = 'GET',
        string $path = '/',
        array $body = [],
        array $headers = [],
    ): array {
        return [
            'method' => strtoupper($method),
            'path' => $path,
            'body' => $body,
            'headers' => $headers,
        ];
    }

    /**
     * Set up server variables for testing.
     *
     * @param array<string, mixed> $vars Server variables to set
     */
    protected function setServerVars(array $vars): void
    {
        foreach ($vars as $key => $value) {
            $_SERVER[$key] = $value;
        }
    }

    /**
     * Set up a secure HTTPS environment.
     */
    protected function setSecureEnvironment(): void
    {
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['SERVER_PORT'] = 443;
    }

    /**
     * Set up an insecure HTTP environment.
     */
    protected function setInsecureEnvironment(): void
    {
        unset($_SERVER['HTTPS']);
        $_SERVER['SERVER_PORT'] = 80;
    }

    /**
     * Set up an AJAX request environment.
     */
    protected function setAjaxRequest(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    }

    /**
     * Set up a JSON request environment.
     */
    protected function setJsonRequest(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
    }

    /**
     * Flash old input data.
     *
     * @param array<string, mixed> $data Input data
     */
    protected function flashInput(array $data): void
    {
        ViewDataBag::flashInput($data);
        ViewDataBag::reset();
    }

    /**
     * Flash validation errors.
     *
     * @param array<string, array<int, string>> $errors Errors
     */
    protected function flashErrors(array $errors): void
    {
        ViewDataBag::flashErrors($errors);
        ViewDataBag::reset();
    }

    // =========================================================================
    // HTML Assertions
    // =========================================================================

    /**
     * Assert that an HTML string contains an element with given attributes.
     *
     * @param string $html HTML string
     * @param string $tag Tag name
     * @param array<string, string> $attributes Expected attributes
     */
    protected function assertHtmlContainsElement(
        string $html,
        string $tag,
        array $attributes = [],
    ): void {
        $this->assertStringContainsString("<{$tag}", $html, "HTML should contain <{$tag}> element");

        foreach ($attributes as $attr => $value) {
            $pattern = "{$attr}=\"{$value}\"";
            $this->assertStringContainsString(
                $pattern,
                $html,
                "Element <{$tag}> should have attribute {$attr}=\"{$value}\""
            );
        }
    }

    /**
     * Assert that an HTML string contains an input with given attributes.
     *
     * @param string $html HTML string
     * @param string $type Input type
     * @param string $name Input name
     * @param array<string, string> $attributes Additional attributes
     */
    protected function assertHtmlContainsInput(
        string $html,
        string $type,
        string $name,
        array $attributes = [],
    ): void {
        $attributes = array_merge([
            'type' => $type,
            'name' => $name,
        ], $attributes);

        $this->assertHtmlContainsElement($html, 'input', $attributes);
    }

    /**
     * Assert that an HTML string contains a form with given action.
     *
     * @param string $html HTML string
     * @param string $action Form action
     * @param string $method Form method
     */
    protected function assertHtmlContainsForm(
        string $html,
        string $action,
        string $method = 'POST',
    ): void {
        $this->assertHtmlContainsElement($html, 'form', [
            'action' => $action,
            'method' => $method,
        ]);
    }

    /**
     * Assert that HTML is XSS-safe (no unescaped script tags).
     *
     * @param string $html HTML string
     */
    protected function assertXssSafe(string $html): void
    {
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('onerror=', $html);
        $this->assertStringNotContainsString('onclick=', $html);
    }

    /**
     * Assert that a string contains all the given substrings.
     *
     * @param string $haystack
     * @param array<int, string> $needles
     */
    protected function assertStringContainsAll(string $haystack, array $needles): void
    {
        foreach ($needles as $needle) {
            $this->assertStringContainsString($needle, $haystack);
        }
    }

    /**
     * Assert that a string does not contain any of the given substrings.
     *
     * @param string $haystack
     * @param array<int, string> $needles
     */
    protected function assertStringNotContainsAny(string $haystack, array $needles): void
    {
        foreach ($needles as $needle) {
            $this->assertStringNotContainsString($needle, $haystack);
        }
    }
}
