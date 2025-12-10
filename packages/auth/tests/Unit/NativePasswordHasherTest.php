<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Unit;

use Lalaz\Auth\Tests\Common\AuthUnitTestCase;

use Lalaz\Auth\Contracts\PasswordHasherInterface;
use Lalaz\Auth\NativePasswordHasher;

/**
 * Tests for NativePasswordHasher class.
 */
class NativePasswordHasherTest extends AuthUnitTestCase
{
    public function test_implements_password_hasher_interface(): void
    {
        $hasher = new NativePasswordHasher();
        $this->assertInstanceOf(PasswordHasherInterface::class, $hasher);
    }

    public function test_default_algorithm(): void
    {
        $hasher = new NativePasswordHasher();
        $this->assertEquals(PASSWORD_DEFAULT, $hasher->getAlgorithm());
    }

    public function test_custom_algorithm(): void
    {
        $hasher = new NativePasswordHasher(PASSWORD_BCRYPT);
        $this->assertEquals(PASSWORD_BCRYPT, $hasher->getAlgorithm());
    }

    public function test_has_pepper_returns_false_when_not_set(): void
    {
        $hasher = new NativePasswordHasher();
        $this->assertFalse($hasher->hasPepper());
    }

    public function test_has_pepper_returns_true_when_set(): void
    {
        $hasher = new NativePasswordHasher(pepper: 'secret-pepper');
        $this->assertTrue($hasher->hasPepper());
    }

    public function test_has_pepper_returns_false_for_empty_string(): void
    {
        $hasher = new NativePasswordHasher(pepper: '');
        $this->assertFalse($hasher->hasPepper());
    }

    public function test_hash_creates_valid_hash(): void
    {
        $hasher = new NativePasswordHasher();
        $hash = $hasher->hash('password123');

        $this->assertNotEmpty($hash);
        $this->assertNotEquals('password123', $hash);
    }

    public function test_hash_creates_different_hashes_for_same_password(): void
    {
        $hasher = new NativePasswordHasher();

        $hash1 = $hasher->hash('password123');
        $hash2 = $hasher->hash('password123');

        $this->assertNotEquals($hash1, $hash2);
    }

    public function test_verify_returns_true_for_correct_password(): void
    {
        $hasher = new NativePasswordHasher();
        $hash = $hasher->hash('password123');

        $this->assertTrue($hasher->verify('password123', $hash));
    }

    public function test_verify_returns_false_for_incorrect_password(): void
    {
        $hasher = new NativePasswordHasher();
        $hash = $hasher->hash('password123');

        $this->assertFalse($hasher->verify('wrong-password', $hash));
    }

    public function test_hash_with_pepper(): void
    {
        $hasher = new NativePasswordHasher(pepper: 'secret-pepper');
        $hash = $hasher->hash('password123');

        $this->assertTrue($hasher->verify('password123', $hash));
        $this->assertFalse($hasher->verify('wrong', $hash));
    }

    public function test_different_peppers_produce_incompatible_hashes(): void
    {
        $hasher1 = new NativePasswordHasher(pepper: 'pepper-one');
        $hasher2 = new NativePasswordHasher(pepper: 'pepper-two');

        $hash = $hasher1->hash('password123');

        $this->assertTrue($hasher1->verify('password123', $hash));
        $this->assertFalse($hasher2->verify('password123', $hash));
    }

    public function test_needs_rehash_returns_false_for_current_options(): void
    {
        $hasher = new NativePasswordHasher();
        $hash = $hasher->hash('password123');

        $this->assertFalse($hasher->needsRehash($hash));
    }

    public function test_needs_rehash_returns_true_for_different_algorithm(): void
    {
        $hasher1 = new NativePasswordHasher(PASSWORD_BCRYPT);
        $hasher2 = new NativePasswordHasher(PASSWORD_ARGON2ID);

        $hash = $hasher1->hash('password123');

        $this->assertTrue($hasher2->needsRehash($hash));
    }

    public function test_bcrypt_factory(): void
    {
        $hasher = NativePasswordHasher::bcrypt(10);

        $this->assertEquals(PASSWORD_BCRYPT, $hasher->getAlgorithm());
        $this->assertEquals(['cost' => 10], $hasher->getOptions());
    }

    public function test_bcrypt_factory_with_pepper(): void
    {
        $hasher = NativePasswordHasher::bcrypt(12, 'my-pepper');

        $this->assertTrue($hasher->hasPepper());
        $hash = $hasher->hash('password123');
        $this->assertStringStartsWith('$2y$12$', $hash);
    }

    public function test_argon2id_factory(): void
    {
        $hasher = NativePasswordHasher::argon2id(
            memoryCost: 32768,
            timeCost: 2,
            threads: 1
        );

        $this->assertEquals(PASSWORD_ARGON2ID, $hasher->getAlgorithm());
        $this->assertEquals([
            'memory_cost' => 32768,
            'time_cost' => 2,
            'threads' => 1,
        ], $hasher->getOptions());
    }

    public function test_argon2id_factory_with_pepper(): void
    {
        $hasher = NativePasswordHasher::argon2id(pepper: 'my-pepper');

        $this->assertTrue($hasher->hasPepper());
        $hash = $hasher->hash('password123');
        $this->assertStringStartsWith('$argon2id$', $hash);
    }

    public function test_from_config_with_defaults(): void
    {
        $hasher = NativePasswordHasher::fromConfig([]);

        $this->assertEquals(PASSWORD_DEFAULT, $hasher->getAlgorithm());
        $this->assertFalse($hasher->hasPepper());
        $this->assertEquals([], $hasher->getOptions());
    }

    public function test_from_config_with_all_options(): void
    {
        $hasher = NativePasswordHasher::fromConfig([
            'algorithm' => PASSWORD_BCRYPT,
            'pepper' => 'config-pepper',
            'options' => ['cost' => 14],
        ]);

        $this->assertEquals(PASSWORD_BCRYPT, $hasher->getAlgorithm());
        $this->assertTrue($hasher->hasPepper());
        $this->assertEquals(['cost' => 14], $hasher->getOptions());
    }

    public function test_full_workflow_with_pepper(): void
    {
        $hasher = NativePasswordHasher::argon2id(pepper: 'production-pepper');

        $password = 'MySecurePassword123!';
        $hash = $hasher->hash($password);

        $this->assertTrue($hasher->verify($password, $hash));
        $this->assertFalse($hasher->verify('wrong', $hash));
        $this->assertFalse($hasher->needsRehash($hash));
    }
}
