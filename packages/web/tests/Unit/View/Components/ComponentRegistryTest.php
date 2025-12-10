<?php

declare(strict_types=1);

namespace Lalaz\Web\Tests\Unit\View\Components;

use Lalaz\Web\Tests\Common\WebUnitTestCase;
use Lalaz\Web\View\Components\Component;
use Lalaz\Web\View\Components\ComponentRegistry;

/**
 * Unit tests for ComponentRegistry.
 *
 * @covers \Lalaz\Web\View\Components\ComponentRegistry
 */
class ComponentRegistryTest extends WebUnitTestCase
{
    public function test_get_template_path_returns_configured_path(): void
    {
        $registry = new ComponentRegistry('App\\Components', '/custom/path');

        $this->assertSame('/custom/path', $registry->getTemplatePath());
    }

    public function test_get_namespace_returns_configured_namespace(): void
    {
        $registry = new ComponentRegistry('Custom\\Namespace');

        $this->assertSame('Custom\\Namespace', $registry->getNamespace());
    }

    public function test_resolve_class_returns_null_for_non_existent_class(): void
    {
        $registry = new ComponentRegistry('App\\NonExistent');

        $result = $registry->resolveClass('some-component');

        $this->assertNull($result);
    }

    public function test_resolve_class_caches_results(): void
    {
        $registry = new ComponentRegistry('App\\NonExistent');

        // First call
        $result1 = $registry->resolveClass('missing');
        // Second call (should be cached)
        $result2 = $registry->resolveClass('missing');

        $this->assertNull($result1);
        $this->assertNull($result2);
    }

    public function test_resolve_template_returns_lowercase_name(): void
    {
        $registry = new ComponentRegistry();

        $template = $registry->resolveTemplate('Alert');

        $this->assertSame('alert', $template);
    }

    public function test_resolve_template_preserves_path_separators(): void
    {
        $registry = new ComponentRegistry();

        $template = $registry->resolveTemplate('forms/Input');

        $this->assertSame('forms/input', $template);
    }

    public function test_resolve_template_uses_component_custom_template(): void
    {
        $registry = new ComponentRegistry();
        $component = new class extends Component {
            public function template(): ?string
            {
                return 'custom/template/path';
            }
        };

        $template = $registry->resolveTemplate('anything', $component);

        $this->assertSame('custom/template/path', $template);
    }

    public function test_resolve_full_template_path_returns_complete_path(): void
    {
        $registry = new ComponentRegistry('App\\Components', '/app/components');

        $path = $registry->resolveFullTemplatePath('alert');

        $this->assertSame('/app/components/alert.twig', $path);
    }

    public function test_resolve_full_template_path_handles_nested_components(): void
    {
        $registry = new ComponentRegistry('App\\Components', '/app/components');

        $path = $registry->resolveFullTemplatePath('forms/input');

        $this->assertSame('/app/components/forms/input.twig', $path);
    }

    public function test_clear_cache_resets_caches(): void
    {
        $registry = new ComponentRegistry('App\\Components', '/path');

        // Populate cache
        $registry->resolveClass('something');
        $registry->resolveTemplate('something');

        // Clear cache
        $registry->clearCache();

        // Should work without errors after clearing
        $this->assertNull($registry->resolveClass('something'));
    }

    public function test_register_manually_registers_component(): void
    {
        $registry = new ComponentRegistry();

        // Create an anonymous class that extends Component
        $componentClass = get_class(new class extends Component {});

        $registry->register('custom-name', $componentClass);

        $resolved = $registry->resolveClass('custom-name');

        $this->assertSame($componentClass, $resolved);
    }

    public function test_template_exists_returns_false_for_missing_template(): void
    {
        $registry = new ComponentRegistry('App\\Components', '/nonexistent/path');

        $this->assertFalse($registry->templateExists('missing'));
    }
}
