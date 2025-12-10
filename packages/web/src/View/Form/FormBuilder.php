<?php

declare(strict_types=1);

namespace Lalaz\Web\View\Form;

use Lalaz\Web\Http\ViewDataBag;
use Lalaz\Web\Security\CsrfProtection;

/**
 * Form HTML builder with automatic old input and error handling.
 *
 * Provides helper methods to generate form HTML elements that automatically
 * integrate with validation errors and old input repopulation.
 *
 * @package Lalaz\Web\View\Form
 */
class FormBuilder
{
    /**
     * Generate the opening form tag.
     *
     * Automatically includes CSRF token for non-GET forms.
     *
     * @param string $action Form action URL
     * @param string $method HTTP method (GET, POST, PUT, PATCH, DELETE)
     * @param array<string, mixed> $attributes Additional HTML attributes
     * @return string The opening form tag with CSRF field
     */
    public static function open(
        string $action,
        string $method = 'POST',
        array $attributes = []
    ): string {
        $method = strtoupper($method);
        $actualMethod = $method;
        $spoofField = '';

        // HTML forms only support GET and POST
        if (!in_array($method, ['GET', 'POST'], true)) {
            $actualMethod = 'POST';
            $spoofField = self::method($method);
        }

        $attrs = self::buildAttributes(array_merge($attributes, [
            'action' => $action,
            'method' => $actualMethod,
        ]));

        $html = "<form{$attrs}>";

        // Add CSRF for non-GET requests
        if ($actualMethod !== 'GET') {
            $html .= self::csrf();
        }

        // Add method spoofing if needed
        $html .= $spoofField;

        return $html;
    }

    /**
     * Generate the closing form tag.
     *
     * @return string The closing form tag
     */
    public static function close(): string
    {
        return '</form>';
    }

    /**
     * Generate a CSRF token hidden field.
     *
     * @return string The CSRF hidden input
     */
    public static function csrf(): string
    {
        $token = CsrfProtection::getToken();
        $fieldName = CsrfProtection::getTokenFieldName();

        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            self::escape($fieldName),
            self::escape($token)
        );
    }

    /**
     * Generate a method spoofing hidden field.
     *
     * @param string $method The HTTP method (PUT, PATCH, DELETE)
     * @return string The method hidden input
     */
    public static function method(string $method): string
    {
        return sprintf(
            '<input type="hidden" name="_method" value="%s">',
            self::escape(strtoupper($method))
        );
    }

    /**
     * Generate a text input field.
     *
     * @param string $name Field name
     * @param array<string, mixed> $options Input options
     * @return string The input HTML
     */
    public static function text(string $name, array $options = []): string
    {
        return self::input('text', $name, $options);
    }

    /**
     * Generate an email input field.
     *
     * @param string $name Field name
     * @param array<string, mixed> $options Input options
     * @return string The input HTML
     */
    public static function email(string $name, array $options = []): string
    {
        return self::input('email', $name, $options);
    }

    /**
     * Generate a password input field.
     *
     * Note: Password fields never repopulate from old input for security.
     *
     * @param string $name Field name
     * @param array<string, mixed> $options Input options
     * @return string The input HTML
     */
    public static function password(string $name, array $options = []): string
    {
        // Never repopulate password fields
        $options['value'] = '';
        return self::input('password', $name, $options);
    }

    /**
     * Generate a number input field.
     *
     * @param string $name Field name
     * @param array<string, mixed> $options Input options
     * @return string The input HTML
     */
    public static function number(string $name, array $options = []): string
    {
        return self::input('number', $name, $options);
    }

    /**
     * Generate a hidden input field.
     *
     * @param string $name Field name
     * @param string|null $value Field value
     * @param array<string, mixed> $options Input options
     * @return string The input HTML
     */
    public static function hidden(string $name, ?string $value = null, array $options = []): string
    {
        $options['value'] = $value;
        return self::input('hidden', $name, $options);
    }

    /**
     * Generate a textarea field.
     *
     * @param string $name Field name
     * @param array<string, mixed> $options Textarea options
     * @return string The textarea HTML
     */
    public static function textarea(string $name, array $options = []): string
    {
        $label = $options['label'] ?? null;
        $value = $options['value'] ?? ViewDataBag::getOldInput($name, '');
        $rows = $options['rows'] ?? 3;
        $required = $options['required'] ?? false;
        $placeholder = $options['placeholder'] ?? '';
        $class = $options['class'] ?? '';
        $id = $options['id'] ?? $name;
        $errorClass = $options['errorClass'] ?? 'is-invalid';
        $wrapperClass = $options['wrapperClass'] ?? 'form-group';
        $showError = $options['showError'] ?? true;

        // Add error class if field has errors
        $hasError = ViewDataBag::hasErrors($name);
        if ($hasError && $errorClass) {
            $class = trim($class . ' ' . $errorClass);
        }

        $html = '';

        // Wrapper
        if ($wrapperClass) {
            $html .= sprintf('<div class="%s">', self::escape($wrapperClass));
        }

        // Label
        if ($label !== null) {
            $html .= self::label($name, $label, $required);
        }

        // Textarea attributes
        $attrs = self::buildAttributes([
            'name' => $name,
            'id' => $id,
            'class' => $class ?: null,
            'rows' => $rows,
            'placeholder' => $placeholder ?: null,
            'required' => $required ?: null,
        ]);

        $html .= sprintf('<textarea%s>%s</textarea>', $attrs, self::escape((string) $value));

        // Error message
        if ($showError && $hasError) {
            $html .= self::errorMessage($name);
        }

        // Close wrapper
        if ($wrapperClass) {
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Generate a select dropdown.
     *
     * @param string $name Field name
     * @param array<string|int, string> $options Select options (value => label)
     * @param array<string, mixed> $config Select configuration
     * @return string The select HTML
     */
    public static function select(string $name, array $options, array $config = []): string
    {
        $label = $config['label'] ?? null;
        $selected = $config['selected'] ?? ViewDataBag::getOldInput($name);
        $placeholder = $config['placeholder'] ?? null;
        $required = $config['required'] ?? false;
        $class = $config['class'] ?? '';
        $id = $config['id'] ?? $name;
        $errorClass = $config['errorClass'] ?? 'is-invalid';
        $wrapperClass = $config['wrapperClass'] ?? 'form-group';
        $showError = $config['showError'] ?? true;

        // Add error class if field has errors
        $hasError = ViewDataBag::hasErrors($name);
        if ($hasError && $errorClass) {
            $class = trim($class . ' ' . $errorClass);
        }

        $html = '';

        // Wrapper
        if ($wrapperClass) {
            $html .= sprintf('<div class="%s">', self::escape($wrapperClass));
        }

        // Label
        if ($label !== null) {
            $html .= self::label($name, $label, $required);
        }

        // Select attributes
        $attrs = self::buildAttributes([
            'name' => $name,
            'id' => $id,
            'class' => $class ?: null,
            'required' => $required ?: null,
        ]);

        $html .= sprintf('<select%s>', $attrs);

        // Placeholder option
        if ($placeholder !== null) {
            $html .= sprintf('<option value="">%s</option>', self::escape($placeholder));
        }

        // Options
        foreach ($options as $value => $optionLabel) {
            $isSelected = (string) $value === (string) $selected;
            $selectedAttr = $isSelected ? ' selected' : '';
            $html .= sprintf(
                '<option value="%s"%s>%s</option>',
                self::escape((string) $value),
                $selectedAttr,
                self::escape($optionLabel)
            );
        }

        $html .= '</select>';

        // Error message
        if ($showError && $hasError) {
            $html .= self::errorMessage($name);
        }

        // Close wrapper
        if ($wrapperClass) {
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Generate a checkbox field.
     *
     * @param string $name Field name
     * @param array<string, mixed> $options Checkbox options
     * @return string The checkbox HTML
     */
    public static function checkbox(string $name, array $options = []): string
    {
        $label = $options['label'] ?? null;
        $value = $options['value'] ?? '1';
        $checked = $options['checked'] ?? (bool) ViewDataBag::getOldInput($name, false);
        $class = $options['class'] ?? '';
        $id = $options['id'] ?? $name;
        $wrapperClass = $options['wrapperClass'] ?? 'form-check';

        $html = '';

        // Wrapper
        if ($wrapperClass) {
            $html .= sprintf('<div class="%s">', self::escape($wrapperClass));
        }

        // Checkbox attributes
        $attrs = self::buildAttributes([
            'type' => 'checkbox',
            'name' => $name,
            'id' => $id,
            'value' => $value,
            'class' => $class ?: null,
            'checked' => $checked ?: null,
        ]);

        $html .= sprintf('<input%s>', $attrs);

        // Label after checkbox
        if ($label !== null) {
            $html .= sprintf(
                '<label for="%s">%s</label>',
                self::escape($id),
                self::escape($label)
            );
        }

        // Close wrapper
        if ($wrapperClass) {
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Generate a radio button.
     *
     * @param string $name Field name
     * @param string $value Radio value
     * @param array<string, mixed> $options Radio options
     * @return string The radio HTML
     */
    public static function radio(string $name, string $value, array $options = []): string
    {
        $label = $options['label'] ?? null;
        $checked = $options['checked'] ?? (ViewDataBag::getOldInput($name) === $value);
        $class = $options['class'] ?? '';
        $id = $options['id'] ?? "{$name}_{$value}";
        $wrapperClass = $options['wrapperClass'] ?? 'form-check';

        $html = '';

        // Wrapper
        if ($wrapperClass) {
            $html .= sprintf('<div class="%s">', self::escape($wrapperClass));
        }

        // Radio attributes
        $attrs = self::buildAttributes([
            'type' => 'radio',
            'name' => $name,
            'id' => $id,
            'value' => $value,
            'class' => $class ?: null,
            'checked' => $checked ?: null,
        ]);

        $html .= sprintf('<input%s>', $attrs);

        // Label after radio
        if ($label !== null) {
            $html .= sprintf(
                '<label for="%s">%s</label>',
                self::escape($id),
                self::escape($label)
            );
        }

        // Close wrapper
        if ($wrapperClass) {
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Generate a submit button.
     *
     * @param string $text Button text
     * @param array<string, mixed> $options Button options
     * @return string The button HTML
     */
    public static function submit(string $text, array $options = []): string
    {
        $class = $options['class'] ?? 'btn btn-primary';
        $disabled = $options['disabled'] ?? false;
        $name = $options['name'] ?? null;

        $attrs = self::buildAttributes([
            'type' => 'submit',
            'class' => $class ?: null,
            'name' => $name,
            'disabled' => $disabled ?: null,
        ]);

        return sprintf('<button%s>%s</button>', $attrs, self::escape($text));
    }

    /**
     * Generate a button.
     *
     * @param string $text Button text
     * @param array<string, mixed> $options Button options
     * @return string The button HTML
     */
    public static function button(string $text, array $options = []): string
    {
        $type = $options['type'] ?? 'button';
        $class = $options['class'] ?? 'btn';
        $disabled = $options['disabled'] ?? false;

        $attrs = self::buildAttributes([
            'type' => $type,
            'class' => $class ?: null,
            'disabled' => $disabled ?: null,
        ]);

        return sprintf('<button%s>%s</button>', $attrs, self::escape($text));
    }

    /**
     * Generate a label element.
     *
     * @param string $for The input ID to associate with
     * @param string $text Label text
     * @param bool $required Whether to show required indicator
     * @return string The label HTML
     */
    public static function label(string $for, string $text, bool $required = false): string
    {
        $requiredMark = $required ? '<span class="text-danger">*</span>' : '';

        return sprintf(
            '<label for="%s">%s %s</label>',
            self::escape($for),
            self::escape($text),
            $requiredMark
        );
    }

    /**
     * Generate an error message span.
     *
     * @param string $field Field name
     * @param string $class CSS class for error span
     * @return string The error message HTML
     */
    public static function errorMessage(string $field, string $class = 'invalid-feedback'): string
    {
        $error = ViewDataBag::getFirstError($field);

        if ($error === null) {
            return '';
        }

        return sprintf(
            '<div class="%s">%s</div>',
            self::escape($class),
            self::escape($error)
        );
    }

    /**
     * Generate a generic input field.
     *
     * @param string $type Input type
     * @param string $name Field name
     * @param array<string, mixed> $options Input options
     * @return string The input HTML
     */
    protected static function input(string $type, string $name, array $options = []): string
    {
        $label = $options['label'] ?? null;
        $value = $options['value'] ?? ViewDataBag::getOldInput($name, $options['default'] ?? '');
        $required = $options['required'] ?? false;
        $placeholder = $options['placeholder'] ?? '';
        $class = $options['class'] ?? '';
        $id = $options['id'] ?? $name;
        $errorClass = $options['errorClass'] ?? 'is-invalid';
        $wrapperClass = $options['wrapperClass'] ?? 'form-group';
        $showError = $options['showError'] ?? true;
        $readonly = $options['readonly'] ?? false;
        $disabled = $options['disabled'] ?? false;
        $autocomplete = $options['autocomplete'] ?? null;
        $min = $options['min'] ?? null;
        $max = $options['max'] ?? null;
        $step = $options['step'] ?? null;

        // Add error class if field has errors
        $hasError = ViewDataBag::hasErrors($name);
        if ($hasError && $errorClass) {
            $class = trim($class . ' ' . $errorClass);
        }

        $html = '';

        // Wrapper
        if ($wrapperClass) {
            $html .= sprintf('<div class="%s">', self::escape($wrapperClass));
        }

        // Label
        if ($label !== null) {
            $html .= self::label($name, $label, $required);
        }

        // Input attributes
        $attrs = self::buildAttributes([
            'type' => $type,
            'name' => $name,
            'id' => $id,
            'value' => $value,
            'class' => $class ?: null,
            'placeholder' => $placeholder ?: null,
            'required' => $required ?: null,
            'readonly' => $readonly ?: null,
            'disabled' => $disabled ?: null,
            'autocomplete' => $autocomplete,
            'min' => $min,
            'max' => $max,
            'step' => $step,
        ]);

        $html .= sprintf('<input%s>', $attrs);

        // Error message
        if ($showError && $hasError) {
            $html .= self::errorMessage($name);
        }

        // Close wrapper
        if ($wrapperClass) {
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Build HTML attributes string from array.
     *
     * @param array<string, mixed> $attributes
     * @return string
     */
    protected static function buildAttributes(array $attributes): string
    {
        $html = '';

        foreach ($attributes as $key => $value) {
            if ($value === null || $value === false) {
                continue;
            }

            if ($value === true) {
                // Boolean attributes like required, disabled, checked
                $html .= ' ' . self::escape($key);
            } else {
                $html .= sprintf(' %s="%s"', self::escape($key), self::escape((string) $value));
            }
        }

        return $html;
    }

    /**
     * Escape HTML special characters.
     *
     * @param string $value
     * @return string
     */
    protected static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
