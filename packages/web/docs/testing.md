```markdown
# Testing Guide

This guide explains how to test the Lalaz Web package and write tests for your web applications.

---

## Running Tests

### Quick Start

```bash
# Navigate to the package
cd packages/web

# Run all tests
./vendor/bin/phpunit

# Run unit tests only
./vendor/bin/phpunit --testsuite=Unit

# Run integration tests only
./vendor/bin/phpunit --testsuite=Integration

# Run specific test file
./vendor/bin/phpunit tests/Unit/Http/SessionManagerTest.php

# Run with coverage report
./vendor/bin/phpunit --coverage-html coverage/
```

### Test Structure

```
tests/
├── Unit/
│   ├── Http/
│   │   ├── SessionManagerTest.php
│   │   ├── CookiePolicyTest.php
│   │   ├── HttpEnvironmentTest.php
│   │   └── RedirectResponseTest.php
│   ├── Security/
│   │   ├── CsrfProtectionTest.php
│   │   ├── FingerprintTest.php
│   │   ├── CsrfMiddlewareTest.php
│   │   └── SecurityHeadersMiddlewareTest.php
│   └── View/
│       ├── ErrorBagTest.php
│       ├── ViewContextTest.php
│       ├── ViewFunctionTest.php
│       ├── FormBuilderTest.php
│       └── Components/
│           ├── ComponentTest.php
│           └── ComponentRegistryTest.php
├── Common/
│   ├── WebUnitTestCase.php
│   └── WebIntegrationTestCase.php
├── bootstrap.php
└── TestCase.php
```

---

## Writing Tests

### Using the Base Test Case

All tests should extend `WebUnitTestCase`:

```php
<?php

namespace Lalaz\Web\Tests\Unit\Http;

use Lalaz\Web\Tests\Common\WebUnitTestCase;
use Lalaz\Web\Http\SessionManager;

class SessionManagerTest extends WebUnitTestCase
{
    private SessionManager $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->session = new SessionManager();
    }

    public function test_can_set_and_get_value(): void
    {
        $this->session->set('key', 'value');
        
        $this->assertSame('value', $this->session->get('key'));
    }
}
```

### Testing Session Functionality

```php
<?php

use Lalaz\Web\Tests\Common\WebUnitTestCase;

class SessionTest extends WebUnitTestCase
{
    public function test_flash_data_is_only_available_once(): void
    {
        $session = new SessionManager();
        
        $session->flash('message', 'Hello');
        
        // First access returns the value
        $this->assertSame('Hello', $session->getFlash('message'));
        
        // Second access returns null
        $this->assertNull($session->getFlash('message'));
    }

    public function test_session_regeneration(): void
    {
        $session = new SessionManager();
        $session->set('user_id', 123);
        
        $oldId = session_id();
        $session->regenerate();
        $newId = session_id();
        
        // Session ID changed
        $this->assertNotSame($oldId, $newId);
        
        // Data preserved
        $this->assertSame(123, $session->get('user_id'));
    }
}
```

### Testing CSRF Protection

```php
<?php

use Lalaz\Web\Tests\Common\WebUnitTestCase;
use Lalaz\Web\Security\CsrfProtection;

class CsrfProtectionTest extends WebUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset cookies for each test
        $_COOKIE = [];
    }

    public function test_token_generation(): void
    {
        $token = CsrfProtection::token();
        
        $this->assertNotEmpty($token);
        $this->assertIsString($token);
    }

    public function test_token_validation(): void
    {
        $token = CsrfProtection::token();
        
        // Valid token
        $body = ['_token' => $token];
        $this->assertTrue(CsrfProtection::validateToken($body, []));
        
        // Invalid token
        $body = ['_token' => 'invalid'];
        $this->assertFalse(CsrfProtection::validateToken($body, []));
    }

    public function test_token_validation_from_header(): void
    {
        $token = CsrfProtection::token();
        
        $headers = ['X-CSRF-TOKEN' => $token];
        $this->assertTrue(CsrfProtection::validateToken([], $headers));
    }
}
```

### Testing Error Bag

```php
<?php

use Lalaz\Web\Tests\Common\WebUnitTestCase;
use Lalaz\Web\View\ErrorBag;

class ErrorBagTest extends WebUnitTestCase
{
    public function test_add_and_retrieve_errors(): void
    {
        $errors = new ErrorBag();
        
        $errors->add('email', 'Invalid email format');
        $errors->add('email', 'Email is required');
        
        $this->assertTrue($errors->has('email'));
        $this->assertSame('Invalid email format', $errors->first('email'));
        $this->assertCount(2, $errors->all('email'));
    }

    public function test_from_array(): void
    {
        $errors = new ErrorBag();
        
        $errors->fromArray([
            'name' => ['Name is required'],
            'email' => ['Invalid email', 'Email taken'],
        ]);
        
        $this->assertTrue($errors->any());
        $this->assertSame(3, $errors->count());
    }

    public function test_empty_error_bag(): void
    {
        $errors = new ErrorBag();
        
        $this->assertTrue($errors->isEmpty());
        $this->assertFalse($errors->any());
        $this->assertNull($errors->first('missing'));
    }
}
```

### Testing View Context

```php
<?php

use Lalaz\Web\Tests\Common\WebUnitTestCase;
use Lalaz\Web\View\ViewContext;

class ViewContextTest extends WebUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ViewContext::reset();
    }

    public function test_set_and_get_values(): void
    {
        ViewContext::set('title', 'My Page');
        
        $this->assertSame('My Page', ViewContext::get('title'));
    }

    public function test_callable_values_resolved(): void
    {
        $counter = 0;
        ViewContext::set('count', fn() => ++$counter);
        
        $this->assertSame(1, ViewContext::get('count'));
        $this->assertSame(2, ViewContext::get('count'));
    }

    public function test_default_values(): void
    {
        $this->assertNull(ViewContext::get('missing'));
        $this->assertSame('default', ViewContext::get('missing', 'default'));
    }
}
```

### Testing Form Builder

```php
<?php

use Lalaz\Web\Tests\Common\WebUnitTestCase;
use Lalaz\Web\View\FormBuilder;

class FormBuilderTest extends WebUnitTestCase
{
    private FormBuilder $form;

    protected function setUp(): void
    {
        parent::setUp();
        $this->form = new FormBuilder();
    }

    public function test_open_form(): void
    {
        $html = $this->form->open('/posts', 'POST', ['class' => 'form']);
        
        $this->assertStringContainsString('action="/posts"', $html);
        $this->assertStringContainsString('method="POST"', $html);
        $this->assertStringContainsString('class="form"', $html);
    }

    public function test_text_input(): void
    {
        $html = $this->form->text('title', 'Hello', ['class' => 'input']);
        
        $this->assertStringContainsString('type="text"', $html);
        $this->assertStringContainsString('name="title"', $html);
        $this->assertStringContainsString('value="Hello"', $html);
        $this->assertStringContainsString('class="input"', $html);
    }

    public function test_select_dropdown(): void
    {
        $options = ['draft' => 'Draft', 'published' => 'Published'];
        $html = $this->form->select('status', $options, 'draft');
        
        $this->assertStringContainsString('<select', $html);
        $this->assertStringContainsString('name="status"', $html);
        $this->assertStringContainsString('selected', $html);
    }
}
```

### Testing Components

```php
<?php

use Lalaz\Web\Tests\Common\WebUnitTestCase;
use Lalaz\Web\View\Components\Component;

class ComponentTest extends WebUnitTestCase
{
    public function test_component_data(): void
    {
        $component = new class extends Component {
            public function __construct(
                public string $title = '',
                public string $content = ''
            ) {}
        };
        
        $component = new $component(title: 'Hello', content: 'World');
        
        $data = $component->data();
        
        $this->assertSame('Hello', $data['title']);
        $this->assertSame('World', $data['content']);
    }

    public function test_custom_template(): void
    {
        $component = new class extends Component {
            public function template(): ?string
            {
                return 'custom/template';
            }
        };
        
        $this->assertSame('custom/template', $component->template());
    }
}
```

---

## Test Helpers

### WebUnitTestCase Features

The `WebUnitTestCase` provides useful helpers:

```php
<?php

use Lalaz\Web\Tests\Common\WebUnitTestCase;

class MyTest extends WebUnitTestCase
{
    public function test_example(): void
    {
        // Assert HTML contains string
        $this->assertHtmlContains($html, 'expected text');
        
        // Assert HTML has element
        $this->assertHtmlHasElement($html, 'input[type="text"]');
        
        // Create fake session
        $session = $this->createFakeSession([
            'user_id' => 123,
        ]);
    }
}
```

### Mocking HTTP Requests

```php
<?php

use Lalaz\Web\Tests\Common\WebUnitTestCase;

class RequestTest extends WebUnitTestCase
{
    public function test_with_mocked_request(): void
    {
        // Set up superglobals
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTPS'] = 'on';
        $_POST = ['title' => 'Test'];
        
        // Test your code
        $this->assertTrue(is_secure());
    }
}
```

---

## Testing Tips

### 1. Reset State Between Tests

```php
protected function setUp(): void
{
    parent::setUp();
    
    // Reset singletons
    ViewContext::reset();
    
    // Clear superglobals
    $_SESSION = [];
    $_COOKIE = [];
    $_POST = [];
}
```

### 2. Test Edge Cases

```php
public function test_empty_values(): void
{
    $errors = new ErrorBag();
    
    // Empty string
    $errors->add('field', '');
    $this->assertTrue($errors->has('field'));
    
    // Null handling
    $this->assertNull($errors->first('nonexistent'));
}
```

### 3. Use Data Providers

```php
/**
 * @dataProvider validationProvider
 */
public function test_validation(string $field, array $errors, bool $expected): void
{
    $bag = new ErrorBag();
    $bag->fromArray([$field => $errors]);
    
    $this->assertSame($expected, $bag->has($field));
}

public static function validationProvider(): array
{
    return [
        'single error' => ['email', ['Invalid'], true],
        'multiple errors' => ['email', ['Invalid', 'Required'], true],
        'no errors' => ['email', [], false],
    ];
}
```

### 4. Test Security Features

```php
public function test_csrf_prevents_forgery(): void
{
    $token = CsrfProtection::token();
    
    // Simulate attack with wrong token
    $attackBody = ['_token' => 'attacker_token'];
    
    $this->assertFalse(
        CsrfProtection::validateToken($attackBody, []),
        'CSRF should reject invalid tokens'
    );
}
```

---

## Code Coverage

Generate a coverage report:

```bash
# HTML report
./vendor/bin/phpunit --coverage-html coverage/

# Text summary
./vendor/bin/phpunit --coverage-text
```

View the HTML report at `coverage/index.html`.

---

## Continuous Integration

Example GitHub Actions workflow:

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          coverage: xdebug
      
      - name: Install Dependencies
        run: composer install --no-progress
        working-directory: packages/web
      
      - name: Run Tests
        run: ./vendor/bin/phpunit --coverage-text
        working-directory: packages/web
```

---

## See Also

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Core Concepts](./concepts.md) — Understanding the package architecture
- [API Reference](./api-reference.md) — Complete method reference

```
