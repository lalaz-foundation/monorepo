# Component System

Building reusable UI components with the Component system.

---

## Overview

The Component system provides:

- **Component** — Base class for UI components
- **ComponentRegistry** — Central component registration
- **ComponentRenderer** — Render components in templates

---

## Creating Components

### Basic Component

```php
<?php

namespace App\Components;

use Lalaz\Web\View\Components\Component;

class AlertComponent extends Component
{
    public function __construct(
        public string $type = 'info',
        public string $message = '',
        public bool $dismissible = false
    ) {}
    
    public function data(): array
    {
        return [
            'type' => $this->type,
            'message' => $this->message,
            'dismissible' => $this->dismissible,
            'icon' => $this->getIcon(),
            'classes' => $this->getClasses(),
        ];
    }
    
    public function template(): string
    {
        return 'components/alert';
    }
    
    private function getIcon(): string
    {
        return match ($this->type) {
            'success' => 'check-circle',
            'warning' => 'exclamation-triangle',
            'danger', 'error' => 'times-circle',
            default => 'info-circle',
        };
    }
    
    private function getClasses(): string
    {
        $classes = ['alert', 'alert-' . $this->type];
        
        if ($this->dismissible) {
            $classes[] = 'alert-dismissible';
        }
        
        return implode(' ', $classes);
    }
}
```

Component template:

```twig
{# resources/views/components/alert.twig #}
<div class="{{ classes }}" role="alert">
    <i class="icon icon-{{ icon }}"></i>
    <span class="alert-message">{{ message }}</span>
    
    {% if dismissible %}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    {% endif %}
</div>
```

---

### Card Component

```php
<?php

namespace App\Components;

use Lalaz\Web\View\Components\Component;

class CardComponent extends Component
{
    public function __construct(
        public string $title = '',
        public ?string $subtitle = null,
        public ?string $image = null,
        public ?string $footer = null,
        public string $body = ''
    ) {}
    
    public function data(): array
    {
        return [
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'image' => $this->image,
            'footer' => $this->footer,
            'body' => $this->body,
            'hasImage' => !empty($this->image),
            'hasFooter' => !empty($this->footer),
        ];
    }
    
    public function template(): string
    {
        return 'components/card';
    }
}
```

Template:

```twig
{# resources/views/components/card.twig #}
<div class="card">
    {% if hasImage %}
        <img src="{{ image }}" class="card-img-top" alt="{{ title }}">
    {% endif %}
    
    <div class="card-body">
        {% if title %}
            <h5 class="card-title">{{ title }}</h5>
        {% endif %}
        
        {% if subtitle %}
            <h6 class="card-subtitle text-muted">{{ subtitle }}</h6>
        {% endif %}
        
        <div class="card-text">
            {{ body|raw }}
        </div>
    </div>
    
    {% if hasFooter %}
        <div class="card-footer">
            {{ footer|raw }}
        </div>
    {% endif %}
</div>
```

---

### Button Component

```php
<?php

namespace App\Components;

use Lalaz\Web\View\Components\Component;

class ButtonComponent extends Component
{
    public function __construct(
        public string $text,
        public string $type = 'button',
        public string $variant = 'primary',
        public string $size = 'md',
        public ?string $href = null,
        public bool $disabled = false,
        public ?string $icon = null,
        public array $attributes = []
    ) {}
    
    public function data(): array
    {
        return [
            'text' => $this->text,
            'type' => $this->type,
            'variant' => $this->variant,
            'size' => $this->size,
            'href' => $this->href,
            'disabled' => $this->disabled,
            'icon' => $this->icon,
            'attributes' => $this->buildAttributes(),
            'isLink' => !empty($this->href),
            'classes' => $this->getClasses(),
        ];
    }
    
    public function template(): string
    {
        return 'components/button';
    }
    
    private function getClasses(): string
    {
        $classes = ['btn', 'btn-' . $this->variant];
        
        if ($this->size !== 'md') {
            $classes[] = 'btn-' . $this->size;
        }
        
        if ($this->disabled) {
            $classes[] = 'disabled';
        }
        
        return implode(' ', $classes);
    }
    
    private function buildAttributes(): string
    {
        $attrs = [];
        foreach ($this->attributes as $key => $value) {
            $attrs[] = sprintf('%s="%s"', $key, htmlspecialchars($value));
        }
        return implode(' ', $attrs);
    }
}
```

Template:

```twig
{# resources/views/components/button.twig #}
{% if isLink %}
    <a href="{{ href }}" class="{{ classes }}" {{ attributes|raw }}>
        {% if icon %}<i class="icon icon-{{ icon }}"></i> {% endif %}
        {{ text }}
    </a>
{% else %}
    <button type="{{ type }}" class="{{ classes }}" {{ disabled ? 'disabled' : '' }} {{ attributes|raw }}>
        {% if icon %}<i class="icon icon-{{ icon }}"></i> {% endif %}
        {{ text }}
    </button>
{% endif %}
```

---

## Registering Components

### Service Provider

```php
<?php

namespace App\Providers;

use Lalaz\Core\ServiceProvider;
use Lalaz\Web\View\Components\ComponentRegistry;
use App\Components\AlertComponent;
use App\Components\CardComponent;
use App\Components\ButtonComponent;
use App\Components\ModalComponent;
use App\Components\PaginationComponent;

class ComponentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register components
        ComponentRegistry::register('alert', AlertComponent::class);
        ComponentRegistry::register('card', CardComponent::class);
        ComponentRegistry::register('button', ButtonComponent::class);
        ComponentRegistry::register('modal', ModalComponent::class);
        ComponentRegistry::register('pagination', PaginationComponent::class);
    }
}
```

### Configuration File

```php
<?php
// config/components.php

return [
    'components' => [
        'alert' => \App\Components\AlertComponent::class,
        'card' => \App\Components\CardComponent::class,
        'button' => \App\Components\ButtonComponent::class,
        'modal' => \App\Components\ModalComponent::class,
        'pagination' => \App\Components\PaginationComponent::class,
        'breadcrumb' => \App\Components\BreadcrumbComponent::class,
        'navbar' => \App\Components\NavbarComponent::class,
    ],
];
```

Load from configuration:

```php
<?php

$components = config('components.components');

foreach ($components as $name => $class) {
    ComponentRegistry::register($name, $class);
}
```

---

## Using Components

### In Templates

```twig
{# Using the component function #}
{% set alert = component('alert', {
    type: 'success',
    message: 'Your changes have been saved!',
    dismissible: true
}) %}
{{ alert.render()|raw }}

{# Card component #}
{% set card = component('card', {
    title: 'User Profile',
    subtitle: 'Account Settings',
    body: userInfo,
    footer: '<a href="/profile/edit" class="btn btn-primary">Edit</a>'
}) %}
{{ card.render()|raw }}

{# Button component #}
{% set saveBtn = component('button', {
    text: 'Save Changes',
    type: 'submit',
    variant: 'success',
    icon: 'check'
}) %}
{{ saveBtn.render()|raw }}
```

### In PHP

```php
<?php

use Lalaz\Web\View\Components\ComponentRenderer;

$renderer = new ComponentRenderer();

// Render alert
echo $renderer->render('alert', [
    'type' => 'warning',
    'message' => 'Please verify your email address.',
]);

// Render card with product data
$product = Product::find(1);

echo $renderer->render('card', [
    'title' => $product->name,
    'subtitle' => '$' . $product->price,
    'image' => $product->image_url,
    'body' => $product->description,
]);
```

---

## Advanced Components

### Modal Component

```php
<?php

namespace App\Components;

use Lalaz\Web\View\Components\Component;

class ModalComponent extends Component
{
    public function __construct(
        public string $id,
        public string $title,
        public string $body = '',
        public string $size = 'md',
        public bool $centered = false,
        public bool $scrollable = false,
        public ?string $footer = null
    ) {}
    
    public function data(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'footer' => $this->footer,
            'dialogClasses' => $this->getDialogClasses(),
            'hasFooter' => !empty($this->footer),
        ];
    }
    
    public function template(): string
    {
        return 'components/modal';
    }
    
    private function getDialogClasses(): string
    {
        $classes = ['modal-dialog'];
        
        if ($this->size !== 'md') {
            $classes[] = 'modal-' . $this->size;
        }
        
        if ($this->centered) {
            $classes[] = 'modal-dialog-centered';
        }
        
        if ($this->scrollable) {
            $classes[] = 'modal-dialog-scrollable';
        }
        
        return implode(' ', $classes);
    }
}
```

Template:

```twig
{# resources/views/components/modal.twig #}
<div class="modal fade" id="{{ id }}" tabindex="-1" aria-labelledby="{{ id }}Label" aria-hidden="true">
    <div class="{{ dialogClasses }}">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="{{ id }}Label">{{ title }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                {{ body|raw }}
            </div>
            {% if hasFooter %}
                <div class="modal-footer">
                    {{ footer|raw }}
                </div>
            {% endif %}
        </div>
    </div>
</div>
```

Usage:

```twig
{# Confirmation modal #}
{% set deleteModal = component('modal', {
    id: 'deleteModal',
    title: 'Confirm Delete',
    body: '<p>Are you sure you want to delete this item?</p>',
    size: 'sm',
    centered: true,
    footer: '
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
    '
}) %}
{{ deleteModal.render()|raw }}

{# Trigger button #}
<button type="button" class="btn btn-danger" data-toggle="modal" data-target="#deleteModal">
    Delete Item
</button>
```

---

### Pagination Component

```php
<?php

namespace App\Components;

use Lalaz\Web\View\Components\Component;

class PaginationComponent extends Component
{
    public function __construct(
        public int $currentPage,
        public int $totalPages,
        public string $baseUrl,
        public int $showPages = 5
    ) {}
    
    public function data(): array
    {
        return [
            'currentPage' => $this->currentPage,
            'totalPages' => $this->totalPages,
            'baseUrl' => $this->baseUrl,
            'pages' => $this->getVisiblePages(),
            'hasPrevious' => $this->currentPage > 1,
            'hasNext' => $this->currentPage < $this->totalPages,
            'previousUrl' => $this->getPageUrl($this->currentPage - 1),
            'nextUrl' => $this->getPageUrl($this->currentPage + 1),
        ];
    }
    
    public function template(): string
    {
        return 'components/pagination';
    }
    
    private function getVisiblePages(): array
    {
        $pages = [];
        $half = floor($this->showPages / 2);
        
        $start = max(1, $this->currentPage - $half);
        $end = min($this->totalPages, $start + $this->showPages - 1);
        
        // Adjust start if we're near the end
        if ($end - $start < $this->showPages - 1) {
            $start = max(1, $end - $this->showPages + 1);
        }
        
        for ($i = $start; $i <= $end; $i++) {
            $pages[] = [
                'number' => $i,
                'url' => $this->getPageUrl($i),
                'active' => $i === $this->currentPage,
            ];
        }
        
        return $pages;
    }
    
    private function getPageUrl(int $page): string
    {
        $separator = str_contains($this->baseUrl, '?') ? '&' : '?';
        return $this->baseUrl . $separator . 'page=' . $page;
    }
}
```

---

## Testing Components

```php
<?php

namespace Tests\Unit\Components;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Components\AlertComponent;
use App\Components\ButtonComponent;

class ComponentsTest extends TestCase
{
    #[Test]
    public function alert_component_returns_correct_data(): void
    {
        $component = new AlertComponent(
            type: 'success',
            message: 'Test message',
            dismissible: true
        );
        
        $data = $component->data();
        
        $this->assertEquals('success', $data['type']);
        $this->assertEquals('Test message', $data['message']);
        $this->assertTrue($data['dismissible']);
        $this->assertEquals('check-circle', $data['icon']);
        $this->assertStringContainsString('alert-success', $data['classes']);
        $this->assertStringContainsString('alert-dismissible', $data['classes']);
    }
    
    #[Test]
    public function button_component_generates_link_when_href_provided(): void
    {
        $component = new ButtonComponent(
            text: 'Click Me',
            href: '/some-url',
            variant: 'primary'
        );
        
        $data = $component->data();
        
        $this->assertTrue($data['isLink']);
        $this->assertEquals('/some-url', $data['href']);
    }
    
    #[Test]
    public function button_component_generates_button_without_href(): void
    {
        $component = new ButtonComponent(
            text: 'Submit',
            type: 'submit'
        );
        
        $data = $component->data();
        
        $this->assertFalse($data['isLink']);
        $this->assertEquals('submit', $data['type']);
    }
}
```

---

## See Also

- [View Module](../view/index.md) — View system overview
- [Layout System](./layout-system.md) — Template inheritance
- [API Reference](../api-reference.md) — Component classes
