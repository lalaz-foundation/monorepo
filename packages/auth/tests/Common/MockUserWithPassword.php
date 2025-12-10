<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Common;

/**
 * Mock user with getAuthPassword().
 */
class MockUserWithPassword
{
    private string $password;

    public function __construct(string $password)
    {
        $this->password = $password;
    }

    public function getAuthPassword(): string
    {
        return $this->password;
    }
}
