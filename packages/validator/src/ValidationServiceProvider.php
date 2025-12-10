<?php

declare(strict_types=1);

namespace Lalaz\Validator;

use Lalaz\Container\ServiceProvider;

/**
 * Service provider for the Validator package.
 *
 * Registers the Validator in the container.
 */
final class ValidationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->singleton(Validator::class, function (): Validator {
            return new Validator();
        });

        // Register ORM adapter if ORM package is available
        if (
            interface_exists(\Lalaz\Orm\Contracts\ModelValidatorInterface::class)
        ) {
            $this->singleton(
                \Lalaz\Orm\Contracts\ModelValidatorInterface::class,
                function (): \Lalaz\Orm\Contracts\ModelValidatorInterface {
                    return new Adapters\OrmValidatorAdapter(new Validator());
                }
            );
        }
    }
}
