<?php

declare(strict_types=1);

namespace Lalaz\Web\View;

use Lalaz\Web\Http\Concerns\FlashMessage;
use Lalaz\Web\Http\ViewDataBag;
use Lalaz\Web\Security\CsrfProtection;
use Lalaz\Web\View\Components\ComponentRenderer;
use Lalaz\Web\View\Form\FormBuilder;

/**
 * Collection of view helper functions exposed to template engines (Twig, etc).
 */
class ViewHelpers
{
    use FlashMessage;

    /**
     * Show flash message by name.
     */
    public static function flashMessage(): ViewFunction
    {
        return new ViewFunction(
            'showFlashMessage',
            fn (string $name) => self::showFlashMessage($name),
        );
    }

    /**
     * Generate URLs for named routes.
     */
    public static function route(): ViewFunction
    {
        return new ViewFunction('route', fn (string $action) => route($action));
    }

    /**
     * Ternary helper returning left/right string based on condition.
     */
    public static function conditional(): ViewFunction
    {
        return new ViewFunction(
            'conditional',
            fn (bool $condition, string $left, string $right) => $condition
                ? $left
                : $right,
        );
    }

    /**
     * Render left content when condition is true, else null.
     */
    public static function renderIf(): ViewFunction
    {
        return new ViewFunction(
            'renderIf',
            fn (string $left, bool $condition) => $condition ? $left : null,
        );
    }

    /**
     * Resolve built assets from Vite manifest.
     */
    public static function asset(): ViewFunction
    {
        return new ViewFunction('asset', function (string $path) {
            static $manifest = null;
            static $lastModifiedTime = null;

            $manifestPath = './public/dist/manifest.json';

            if (!file_exists($manifestPath)) {
                return '';
            }

            $currentModifiedTime = filemtime($manifestPath);

            if (
                $manifest === null ||
                $currentModifiedTime !== $lastModifiedTime
            ) {
                $manifestContents = file_get_contents($manifestPath);
                $manifest = json_decode($manifestContents, true);
                $lastModifiedTime = $currentModifiedTime;

                if (json_last_error() !== JSON_ERROR_NONE) {
                    return $path;
                }
            }

            $fileKey = "App/Assets/{$path}";

            if (!isset($manifest[$fileKey])) {
                return $path;
            }

            $file = $manifest[$fileKey]['file'];
            return "/public/dist/$file";
        });
    }

    /**
     * Expose CSRF token value.
     */
    public static function csrfToken(): ViewFunction
    {
        return new ViewFunction(
            'csrfToken',
            fn () => CsrfProtection::getToken(),
        );
    }

    /**
     * Render CSRF hidden input field.
     */
    public static function csrfField(): ViewFunction
    {
        $callable = function () {
            $token = CsrfProtection::getToken();
            $fieldName = CsrfProtection::getTokenFieldName();
            return '<input type="hidden" name="' .
                htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') .
                '" value="' .
                htmlspecialchars($token, ENT_QUOTES, 'UTF-8') .
                '">';
        };

        return new ViewFunction(
            'csrfField',
            $callable,
            ['is_safe' => ['html']], // Hint for template engines that support it
        );
    }

    /**
     * Include Lalaz Live JS dependencies.
     */
    public static function liveScripts(): ViewFunction
    {
        return new ViewFunction(
            'liveScripts',
            function () {
                $jsPath = './public/vendor/lalaz/live/lalaz-live.js';
                $version = file_exists($jsPath) ? filemtime($jsPath) : time();
                return '<script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/morph@3.x.x/dist/cdn.min.js"></script>' .
                    '<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>' .
                    '<script src="/vendor/lalaz/live/lalaz-live.js?v=' .
                    $version .
                    '"></script>';
            },
            ['is_safe' => ['html']],
        );
    }

    /**
     * Include Lalaz Live CSS.
     */
    public static function liveStyles(): ViewFunction
    {
        return new ViewFunction(
            'liveStyles',
            function () {
                $cssPath = './public/vendor/lalaz/live/lalaz-live.css';
                $version = file_exists($cssPath) ? filemtime($cssPath) : time();
                return '<link rel="stylesheet" href="/vendor/lalaz/live/lalaz-live.css?v=' .
                    $version .
                    '">';
            },
            ['is_safe' => ['html']],
        );
    }

    /**
     * Render a Lalaz Live component if the helper exists.
     */
    public static function live(): ViewFunction
    {
        return new ViewFunction(
            'live',
            function (string $name, array $params = []) {
                if (function_exists('live')) {
                    return live($name, $params);
                }
                return '<!-- Lalaz Live not installed -->';
            },
            ['is_safe' => ['html']],
        );
    }

    /**
     * Include Lalaz Reactive JS dependencies.
     *
     * Usage in Twig:
     *   {{ reactiveScripts() | raw }}
     */
    public static function reactiveScripts(): ViewFunction
    {
        return new ViewFunction(
            'reactiveScripts',
            function () {
                if (function_exists('reactiveScripts')) {
                    return reactiveScripts();
                }
                return '<!-- Lalaz Reactive not installed -->';
            },
            ['is_safe' => ['html']],
        );
    }

    /**
     * Include Lalaz Reactive CSS.
     *
     * Usage in Twig:
     *   {{ reactiveStyles() | raw }}
     */
    public static function reactiveStyles(): ViewFunction
    {
        return new ViewFunction(
            'reactiveStyles',
            function () {
                if (function_exists('reactiveStyles')) {
                    return reactiveStyles();
                }
                return '<!-- Lalaz Reactive CSS not installed -->';
            },
            ['is_safe' => ['html']],
        );
    }

    /**
     * Render a Lalaz Reactive component if the helper exists.
     *
     * Usage in Twig:
     *   {{ reactive('counter') | raw }}
     *   {{ reactive('todo-list', { items: [] }) | raw }}
     */
    public static function reactive(): ViewFunction
    {
        return new ViewFunction(
            'reactive',
            function (string $name, array $params = []) {
                if (function_exists('reactive')) {
                    return reactive($name, $params);
                }
                return '<!-- Lalaz Reactive not installed -->';
            },
            ['is_safe' => ['html']],
        );
    }

    /**
     * Render a view component.
     *
     * Usage in Twig:
     *   {{ component('alert', { type: 'success', message: 'Done!' }) }}
     *   {{ component('forms/input', { name: 'email', label: 'Email' }) }}
     */
    public static function component(): ViewFunction
    {
        return new ViewFunction(
            'component',
            function (string $name, array $props = []) {
                return ComponentRenderer::getInstance()->render($name, $props);
            },
            ['is_safe' => ['html']],
        );
    }

    /**
     * Get old input value from the previous request.
     *
     * Usage in Twig:
     *   <input type="email" name="email" value="{{ old('email') }}">
     *   <input type="name" name="name" value="{{ old('name', user.name) }}">
     */
    public static function old(): ViewFunction
    {
        return new ViewFunction(
            'old',
            fn (?string $key = null, mixed $default = null) => ViewDataBag::getOldInput($key, $default),
        );
    }

    /**
     * Get all old input from the previous request.
     *
     * Usage in Twig:
     *   {% set inputs = oldInput() %}
     */
    public static function oldInput(): ViewFunction
    {
        return new ViewFunction(
            'oldInput',
            fn () => ViewDataBag::getOldInput(),
        );
    }

    /**
     * Get the first error message for a field.
     *
     * Usage in Twig:
     *   {{ error('email') }}
     *   <span class="error">{{ error('password') }}</span>
     */
    public static function error(): ViewFunction
    {
        return new ViewFunction(
            'error',
            fn (string $field) => ViewDataBag::getFirstError($field),
        );
    }

    /**
     * Get all error messages for a field.
     *
     * Usage in Twig:
     *   {% for msg in fieldErrors('email') %}
     *     <li>{{ msg }}</li>
     *   {% endfor %}
     */
    public static function fieldErrors(): ViewFunction
    {
        return new ViewFunction(
            'fieldErrors',
            fn (string $field) => ViewDataBag::getErrors()->get($field),
        );
    }

    /**
     * Check if a specific field has validation errors.
     *
     * Usage in Twig:
     *   {% if hasError('email') %}
     *     <span class="error">{{ error('email') }}</span>
     *   {% endif %}
     */
    public static function hasError(): ViewFunction
    {
        return new ViewFunction(
            'hasError',
            fn (string $field) => ViewDataBag::hasErrors($field),
        );
    }

    /**
     * Check if there are any validation errors.
     *
     * Usage in Twig:
     *   {% if hasErrors() %}
     *     <div class="alert alert-danger">Please fix the errors below.</div>
     *   {% endif %}
     */
    public static function hasErrors(): ViewFunction
    {
        return new ViewFunction(
            'hasErrors',
            fn () => ViewDataBag::hasErrors(),
        );
    }

    /**
     * Get all error messages as a flat array.
     *
     * Usage in Twig:
     *   {% for msg in allErrors() %}
     *     <li>{{ msg }}</li>
     *   {% endfor %}
     */
    public static function allErrors(): ViewFunction
    {
        return new ViewFunction(
            'allErrors',
            fn () => ViewDataBag::getAllErrors(),
        );
    }

    /**
     * Get the error bag instance.
     *
     * Usage in Twig:
     *   {% set errors = errorBag() %}
     *   {% if errors.has('email') %}...{% endif %}
     */
    public static function errorBag(): ViewFunction
    {
        return new ViewFunction(
            'errorBag',
            fn () => ViewDataBag::getErrors(),
        );
    }

    // ========================================
    // Form Helpers
    // ========================================

    /**
     * Generate a method spoofing hidden field.
     *
     * Usage in Twig:
     *   {{ methodField('DELETE') | raw }}
     *   {{ methodField('PUT') | raw }}
     */
    public static function methodField(): ViewFunction
    {
        return new ViewFunction(
            'methodField',
            fn (string $method) => FormBuilder::method($method),
            ['is_safe' => ['html']],
        );
    }

    /**
     * Open a form with automatic CSRF and method spoofing.
     *
     * Usage in Twig:
     *   {{ formOpen('/users', 'POST', { class: 'my-form' }) | raw }}
     *   {{ formOpen('/users/5', 'DELETE') | raw }}
     */
    public static function formOpen(): ViewFunction
    {
        return new ViewFunction(
            'formOpen',
            fn (string $action, string $method = 'POST', array $attrs = []) =>
                FormBuilder::open($action, $method, $attrs),
            ['is_safe' => ['html']],
        );
    }

    /**
     * Close a form.
     *
     * Usage in Twig:
     *   {{ formClose() | raw }}
     */
    public static function formClose(): ViewFunction
    {
        return new ViewFunction(
            'formClose',
            fn () => FormBuilder::close(),
            ['is_safe' => ['html']],
        );
    }

    /**
     * Generate a text input with label and error handling.
     *
     * Usage in Twig:
     *   {{ inputText('name', { label: 'Name', required: true }) | raw }}
     */
    public static function inputText(): ViewFunction
    {
        return new ViewFunction(
            'inputText',
            fn (string $name, array $options = []) => FormBuilder::text($name, $options),
            ['is_safe' => ['html']],
        );
    }

    /**
     * Generate an email input with label and error handling.
     *
     * Usage in Twig:
     *   {{ inputEmail('email', { label: 'Email', required: true }) | raw }}
     */
    public static function inputEmail(): ViewFunction
    {
        return new ViewFunction(
            'inputEmail',
            fn (string $name, array $options = []) => FormBuilder::email($name, $options),
            ['is_safe' => ['html']],
        );
    }

    /**
     * Generate a password input.
     *
     * Usage in Twig:
     *   {{ inputPassword('password', { label: 'Password' }) | raw }}
     */
    public static function inputPassword(): ViewFunction
    {
        return new ViewFunction(
            'inputPassword',
            fn (string $name, array $options = []) => FormBuilder::password($name, $options),
            ['is_safe' => ['html']],
        );
    }

    /**
     * Generate a number input.
     *
     * Usage in Twig:
     *   {{ inputNumber('age', { label: 'Age', min: 0, max: 120 }) | raw }}
     */
    public static function inputNumber(): ViewFunction
    {
        return new ViewFunction(
            'inputNumber',
            fn (string $name, array $options = []) => FormBuilder::number($name, $options),
            ['is_safe' => ['html']],
        );
    }

    /**
     * Generate a hidden input.
     *
     * Usage in Twig:
     *   {{ inputHidden('user_id', '123') | raw }}
     */
    public static function inputHidden(): ViewFunction
    {
        return new ViewFunction(
            'inputHidden',
            fn (string $name, ?string $value = null, array $options = []) =>
                FormBuilder::hidden($name, $value, $options),
            ['is_safe' => ['html']],
        );
    }

    /**
     * Generate a textarea.
     *
     * Usage in Twig:
     *   {{ textarea('message', { label: 'Message', rows: 5 }) | raw }}
     */
    public static function textarea(): ViewFunction
    {
        return new ViewFunction(
            'textarea',
            fn (string $name, array $options = []) => FormBuilder::textarea($name, $options),
            ['is_safe' => ['html']],
        );
    }

    /**
     * Generate a select dropdown.
     *
     * Usage in Twig:
     *   {{ selectField('country', { 'br': 'Brazil', 'us': 'USA' }, { label: 'Country' }) | raw }}
     */
    public static function selectField(): ViewFunction
    {
        return new ViewFunction(
            'selectField',
            fn (string $name, array $options, array $config = []) =>
                FormBuilder::select($name, $options, $config),
            ['is_safe' => ['html']],
        );
    }

    /**
     * Generate a checkbox.
     *
     * Usage in Twig:
     *   {{ checkbox('remember', { label: 'Remember me' }) | raw }}
     */
    public static function checkbox(): ViewFunction
    {
        return new ViewFunction(
            'checkbox',
            fn (string $name, array $options = []) => FormBuilder::checkbox($name, $options),
            ['is_safe' => ['html']],
        );
    }

    /**
     * Generate a radio button.
     *
     * Usage in Twig:
     *   {{ radio('gender', 'male', { label: 'Male' }) | raw }}
     */
    public static function radio(): ViewFunction
    {
        return new ViewFunction(
            'radio',
            fn (string $name, string $value, array $options = []) =>
                FormBuilder::radio($name, $value, $options),
            ['is_safe' => ['html']],
        );
    }

    /**
     * Generate a submit button.
     *
     * Usage in Twig:
     *   {{ submitButton('Save') | raw }}
     *   {{ submitButton('Delete', { class: 'btn btn-danger' }) | raw }}
     */
    public static function submitButton(): ViewFunction
    {
        return new ViewFunction(
            'submitButton',
            fn (string $text, array $options = []) => FormBuilder::submit($text, $options),
            ['is_safe' => ['html']],
        );
    }

    /**
     * Get a flash message by name (returns message string or null).
     *
     * Usage in Twig:
     *   {% if flash('success') %}
     *       <div class="alert alert-success">{{ flash('success') }}</div>
     *   {% endif %}
     */
    public static function flash(): ViewFunction
    {
        return new ViewFunction(
            'flash',
            function (string $name): ?string {
                $flash = self::showFlashMessage($name);
                return $flash ? $flash['message'] : null;
            },
        );
    }

    /**
     * Get a configuration value.
     *
     * Usage in Twig:
     *   {{ config('app.name') }}
     *   {{ config('app.debug', false) }}
     */
    public static function config(): ViewFunction
    {
        return new ViewFunction(
            'config',
            function (string $key, mixed $default = null): mixed {
                if (class_exists(\Lalaz\Config\Config::class)) {
                    return \Lalaz\Config\Config::get($key, $default);
                }
                return $default;
            },
        );
    }

    /**
     * Return all registered view helper functions.
     *
     * @return array<ViewFunction>
     */
    public static function all(): array
    {
        return [
            self::asset(),
            self::flash(),
            self::flashMessage(),
            self::config(),
            self::route(),
            self::conditional(),
            self::renderIf(),
            self::csrfToken(),
            self::csrfField(),
            self::liveScripts(),
            self::liveStyles(),
            self::live(),
            self::reactiveScripts(),
            self::reactiveStyles(),
            self::reactive(),
            self::component(),
            self::old(),
            self::oldInput(),
            self::error(),
            self::fieldErrors(),
            self::hasError(),
            self::hasErrors(),
            self::allErrors(),
            self::errorBag(),
            // Form helpers
            self::methodField(),
            self::formOpen(),
            self::formClose(),
            self::inputText(),
            self::inputEmail(),
            self::inputPassword(),
            self::inputNumber(),
            self::inputHidden(),
            self::textarea(),
            self::selectField(),
            self::checkbox(),
            self::radio(),
            self::submitButton(),
        ];
    }
}
