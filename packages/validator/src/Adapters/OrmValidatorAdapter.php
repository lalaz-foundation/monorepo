<?php

declare(strict_types=1);

namespace Lalaz\Validator\Adapters;

use Lalaz\Orm\Contracts\ModelValidatorInterface;
use Lalaz\Orm\Model;
use Lalaz\Validator\ValidationException;
use Lalaz\Validator\Validator;

/**
 * Adapter that bridges the Validator package with ORM's ModelValidatorInterface.
 *
 * This allows the ORM to use the Validator package for model validation
 * without tight coupling between the packages.
 */
final class OrmValidatorAdapter implements ModelValidatorInterface
{
    public function __construct(
        private readonly Validator $validator
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function validate(
        Model $model,
        array $data,
        array $rules,
        string $operation,
    ): void {
        if (empty($rules)) {
            return;
        }

        $errors = $this->validator->validateData($data, $rules);

        if (!empty($errors)) {
            throw new ValidationException(
                $errors,
                'Validation failed for ' . get_class($model)
            );
        }
    }
}
