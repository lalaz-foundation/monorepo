# Examples

Practical examples and use cases for the Lalaz Web package.

---

## Example Categories

### Session & State

- [User Authentication Flow](./authentication-flow.md) — Login with session management
- [Shopping Cart](./shopping-cart.md) — Session-based cart with flash messages
- [Multi-Step Forms](./multi-step-forms.md) — Wizard pattern with state persistence

### Views & Templates

- [Layout System](./layout-system.md) — Master layouts with Twig inheritance
- [Form Handling](./form-handling.md) — Complete form with validation
- [Component System](./component-system.md) — Reusable UI components

### Security

- [CSRF Protection](./csrf-protection.md) — Protecting forms and AJAX
- [Security Headers](./security-headers.md) — Configuring middleware profiles
- [Session Security](./session-security.md) — Fingerprinting and hijacking prevention

---

## Quick Examples

### Basic View Rendering

```php
// Controller action
public function index(): ViewResponse
{
    $products = Product::all();
    
    return view('products/index', [
        'products' => $products,
        'title' => 'All Products',
    ]);
}
```

### Form with Validation

```php
public function store(RequestInterface $request): Response
{
    // Validate
    $errors = $this->validate($request);
    
    if ($errors) {
        return redirect('/form')
            ->withErrors($errors)
            ->withInput();
    }
    
    // Process
    User::create($request->body());
    
    return redirect('/users')
        ->with('success', 'User created!');
}
```

### Protected Form Template

```twig
<form action="/users" method="POST">
    {{ csrf_field()|raw }}
    
    <div class="form-group">
        <label for="email">Email</label>
        <input type="email" 
               name="email" 
               value="{{ old('email') }}"
               class="{{ errors.has('email') ? 'is-invalid' : '' }}">
        {% if errors.has('email') %}
            <span class="error">{{ errors.first('email') }}</span>
        {% endif %}
    </div>
    
    <button type="submit">Create</button>
</form>
```

### Component Usage

```php
// Define component
class AlertComponent extends Component
{
    public function __construct(
        public string $type = 'info',
        public string $message = ''
    ) {}
    
    public function template(): string
    {
        return 'components/alert';
    }
}

// In template
{% set alert = component('alert', {type: 'success', message: 'Saved!'}) %}
{{ alert.render()|raw }}
```

### Security Middleware

```php
use Lalaz\Web\Security\Middlewares\SecurityHeadersMiddleware;
use Lalaz\Web\Security\Middlewares\CsrfMiddleware;

// routes.php
Router::middleware([
    SecurityHeadersMiddleware::recommended(),
    new CsrfMiddleware(['/api/*', '/webhooks/*']),
]);
```

---

## Full Application Example

See the [sandbox/fullstack](../../../sandbox/fullstack) directory for a complete application demonstrating:

- MVC architecture with views
- User authentication with sessions
- Form handling with CSRF protection
- Flash messages and redirects
- View components
- Security headers

---

## See Also

- [Quick Start](../quick-start.md) — 5-minute introduction
- [Core Concepts](../concepts.md) — Understanding the architecture
- [API Reference](../api-reference.md) — Complete API documentation
