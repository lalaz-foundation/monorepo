<?php

declare(strict_types=1);

namespace Lalaz\Web\Http;

use Lalaz\Web\Http\Contracts\RenderableInterface;
use Lalaz\Web\Http\Contracts\ResponseInterface;
use Lalaz\Web\View\ErrorBag;

/**
 * Fluent redirect response builder.
 *
 * Allows chaining methods to build a redirect response with
 * flash data (old input, validation errors, messages).
 *
 * Usage:
 * ```php
 * return redirect('/users/create')
 *     ->withInput()
 *     ->withErrors($validator->errors());
 *
 * return redirect('/dashboard')
 *     ->with('success', 'Profile updated!');
 *
 * return back()->withErrors(['email' => 'Invalid email']);
 * ```
 *
 * @package Lalaz\Web\Http
 */
class RedirectResponse implements RenderableInterface
{
    /**
     * Redirect URL.
     */
    private string $url;

    /**
     * HTTP status code.
     */
    private int $status = 302;

    /**
     * Input data to flash.
     *
     * @var array<string, mixed>
     */
    private array $flashInput = [];

    /**
     * Validation errors to flash.
     *
     * @var array<string, array<int, string>>
     */
    private array $flashErrors = [];

    /**
     * General flash messages.
     *
     * @var array<string, mixed>
     */
    private array $flashMessages = [];

    /**
     * Whether to include all input.
     */
    private bool $withAllInput = false;

    /**
     * Specific input keys to include.
     *
     * @var array<int, string>|null
     */
    private ?array $onlyInput = null;

    /**
     * Input keys to exclude.
     *
     * @var array<int, string>
     */
    private array $exceptInput = [];

    /**
     * Create a new redirect response.
     *
     * @param string $url Redirect URL
     * @param int $status HTTP status code (default 302)
     */
    public function __construct(string $url, int $status = 302)
    {
        $this->url = $url;
        $this->status = $status;
    }

    /**
     * Include input data in the flash session.
     *
     * @param array<int, string>|null $keys Specific keys to include, or null for all
     * @return self
     */
    public function withInput(?array $keys = null): self
    {
        if ($keys === null) {
            $this->withAllInput = true;
        } else {
            $this->onlyInput = $keys;
        }

        return $this;
    }

    /**
     * Include all input except specified keys.
     *
     * @param array<int, string> $keys Keys to exclude
     * @return self
     */
    public function withInputExcept(array $keys): self
    {
        $this->withAllInput = true;
        $this->exceptInput = $keys;
        return $this;
    }

    /**
     * Include validation errors in the flash session.
     *
     * @param array<string, string|array<int, string>>|ErrorBag $errors Validation errors
     * @return self
     */
    public function withErrors(array|ErrorBag $errors): self
    {
        if ($errors instanceof ErrorBag) {
            $this->flashErrors = $errors->toArray();
        } else {
            // Normalize errors to array format
            foreach ($errors as $field => $messages) {
                $this->flashErrors[$field] = (array) $messages;
            }
        }

        return $this;
    }

    /**
     * Add a flash message.
     *
     * @param string $key Message key
     * @param mixed $value Message value
     * @return self
     */
    public function with(string $key, mixed $value): self
    {
        $this->flashMessages[$key] = $value;
        return $this;
    }

    /**
     * Set the HTTP status code.
     *
     * @param int $status HTTP status code
     * @return self
     */
    public function status(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    /**
     * Flash all data to the session.
     *
     * Should be called before sending the response.
     *
     * @param array<string, mixed>|null $currentInput Current request input (optional)
     * @return void
     */
    public function flash(?array $currentInput = null): void
    {
        // Resolve input to flash
        $input = $this->resolveInput($currentInput);
        if (!empty($input)) {
            ViewDataBag::flashInput($input);
        }

        // Flash errors
        if (!empty($this->flashErrors)) {
            ViewDataBag::flashErrors($this->flashErrors);
        }

        // Flash general messages
        foreach ($this->flashMessages as $key => $value) {
            SessionManager::setValue("_flash_{$key}", $value);
        }
    }

    /**
     * Resolve which input data to flash.
     *
     * @param array<string, mixed>|null $currentInput
     * @return array<string, mixed>
     */
    private function resolveInput(?array $currentInput): array
    {
        // Use provided input or try to get from globals
        $input = $currentInput ?? $_POST;

        // If specific keys requested
        if ($this->onlyInput !== null) {
            return array_intersect_key($input, array_flip($this->onlyInput));
        }

        // If all input requested
        if ($this->withAllInput) {
            // Exclude sensitive fields by default
            $defaultExcept = ['password', 'password_confirmation', '_token', '_csrf'];
            $except = array_merge($defaultExcept, $this->exceptInput);
            return array_diff_key($input, array_flip($except));
        }

        // If specific input was set directly
        return $this->flashInput;
    }

    /**
     * Get the redirect URL.
     *
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Get the HTTP status code.
     *
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * Get the flash errors.
     *
     * @return array<string, array<int, string>>
     */
    public function getErrors(): array
    {
        return $this->flashErrors;
    }

    /**
     * Get the flash messages.
     *
     * @return array<string, mixed>
     */
    public function getMessages(): array
    {
        return $this->flashMessages;
    }

    /**
     * Check if this response has any flash data.
     *
     * @return bool
     */
    public function hasFlashData(): bool
    {
        return $this->withAllInput
            || $this->onlyInput !== null
            || !empty($this->flashInput)
            || !empty($this->flashErrors)
            || !empty($this->flashMessages);
    }

    /**
     * Render this redirect to the given HTTP response.
     *
     * Flashes any pending data to the session and sets up
     * the redirect headers on the response object.
     *
     * @param ResponseInterface $response The response to render to.
     * @return void
     */
    public function toResponse(ResponseInterface $response): void
    {
        // Flash data to session before redirect
        $this->flash();

        // Set redirect on response
        $response->status($this->status);
        $response->redirect($this->url);
    }
}
