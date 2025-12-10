<?php

declare(strict_types=1);

namespace Lalaz\Validator;

use RuntimeException;

/**
 * Thrown when validation fails.
 *
 * @package lalaz/validation
 * @author Gregory Serrao <hello@lalaz.dev>
 */
final class ValidationException extends RuntimeException
{
    /**
     * @param array<string, array<int, string>> $errors
     */
    public function __construct(
        private array $errors,
        string $message = 'Validation failed.',
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
