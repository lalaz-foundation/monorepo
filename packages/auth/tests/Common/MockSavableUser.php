<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Common;

/**
 * Mock user with save() method.
 */
class MockSavableUser
{
    public ?string $remember_token = null;
    public bool $saved = false;

    public function save(): void
    {
        $this->saved = true;
    }
}
