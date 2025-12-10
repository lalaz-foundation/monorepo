# Lalaz Web

Views, templates, sessions, CSRF protection, components, and rendering utilities for the Lalaz Framework.

## Installation

```bash
composer require lalaz/web
```

## Features

- **View Responses** - Return-based view rendering with fluent API
- **Template Engines** - Twig integration out of the box
- **Components** - Reusable UI components with props
- **View Composers** - Automatic data injection into views
- **CSRF Protection** - Built-in CSRF token handling
- **Session Management** - Secure session handling with fingerprinting
- **Flash Messages** - Easy flash message management
- **Form Validation UX** - Old input and error bag support
- **Method Spoofing** - PUT/PATCH/DELETE support via `_method` field
- **Form Helpers** - Auto-generated form HTML with validation integration

## Quick Start

### View Responses

Return views from your controllers:

```php
use function view;

class UserController
{
    public function index(): ViewResponse
    {
        return view('users/index', ['users' => User::all()]);
    }

    public function show(int $id): ViewResponse
    {
        return view('users/show', ['user' => User::find($id)])
            ->layout('layouts/admin');
    }
}
```

### Redirects with Flash Data

```php
use function redirect;
use function back;

class UserController
{
    public function store(Request $request): RedirectResponse
    {
        $validator = new Validator($request->post());
        
        if (!$validator->validate(['email' => 'required|email'])) {
            return back()
                ->withInput()
                ->withErrors($validator->errors());
        }

        User::create($request->post());
        
        return redirect('/users')
            ->with('success', 'User created successfully!');
    }
}
```

### Components

Create reusable components:

```php
// app/View/Components/Alert.php
namespace App\View\Components;

use Lalaz\Web\View\Components\Component;

class Alert extends Component
{
    public function __construct(
        public string $type = 'info',
        public string $message = ''
    ) {}

    public function render(): string
    {
        return 'components/alert'; // Template path
    }
}
```

Use in Twig templates:

```twig
{{ component('alert', { type: 'success', message: 'Done!' }) }}
```

### View Composers

Inject data into views automatically:

```php
// app/View/Composers/NavigationComposer.php
namespace App\View\Composers;

use Lalaz\Web\View\Composer;

class NavigationComposer extends Composer
{
    protected array $views = ['layouts/*', 'partials/navigation'];

    public function compose(array &$data): void
    {
        $data['navigation'] = $this->getNavigationItems();
    }
}
```

### Old Input & Validation Errors

In your Twig templates:

```twig
<form method="POST" action="/users">
    {{ csrfField() | raw }}
    
    {% if hasErrors() %}
        <div class="alert alert-danger">
            <ul>
                {% for msg in allErrors() %}
                    <li>{{ msg }}</li>
                {% endfor %}
            </ul>
        </div>
    {% endif %}
    
    <div class="form-group">
        <label>Email</label>
        <input type="email" 
               name="email" 
               value="{{ old('email') }}"
               class="{% if hasError('email') %}is-invalid{% endif %}">
        {% if hasError('email') %}
            <span class="error">{{ error('email') }}</span>
        {% endif %}
    </div>
    
    <button type="submit">Submit</button>
</form>
```

### Method Spoofing

HTML forms only support GET and POST. Use method spoofing to send PUT, PATCH, or DELETE requests:

**1. Register the middleware (in your bootstrap or routes):**

```php
use Lalaz\Web\Http\Middlewares\MethodSpoofingMiddleware;

$router->middleware(MethodSpoofingMiddleware::class);
```

**2. Use in forms:**

```twig
{# Manual hidden field #}
<form method="POST" action="/users/5">
    {{ csrfField() | raw }}
    {{ methodField('DELETE') | raw }}
    <button>Delete User</button>
</form>

{# Or using formOpen helper (auto-adds method field) #}
{{ formOpen('/users/5', 'DELETE') | raw }}
    <button>Delete User</button>
{{ formClose() | raw }}
```

The middleware also supports the `X-HTTP-Method-Override` header for API clients.

### Form Helpers

Generate form HTML with automatic old input and error integration:

```twig
{{ formOpen('/users', 'POST', { class: 'form', id: 'user-form' }) | raw }}
    
    {{ inputText('name', { label: 'Full Name', required: true, placeholder: 'John Doe' }) | raw }}
    
    {{ inputEmail('email', { label: 'Email Address', required: true }) | raw }}
    
    {{ inputPassword('password', { label: 'Password', required: true }) | raw }}
    
    {{ textarea('bio', { label: 'Biography', rows: 4 }) | raw }}
    
    {{ select('country', { label: 'Country', placeholder: 'Select a country' }, {
        us: 'United States',
        uk: 'United Kingdom',
        ca: 'Canada'
    }) | raw }}
    
    {{ checkbox('newsletter', { label: 'Subscribe to newsletter' }) | raw }}
    
    {{ submitButton('Create User') | raw }}
    
{{ formClose() | raw }}
```

**Edit forms with PUT method:**

```twig
{{ formOpen('/users/5', 'PUT') | raw }}
    {{ inputText('name', { label: 'Name', value: user.name }) | raw }}
    {{ inputEmail('email', { label: 'Email', value: user.email }) | raw }}
    {{ submitButton('Update') | raw }}
{{ formClose() | raw }}
```

Form helpers automatically:
- Populate fields with old input after validation failures
- Add error CSS classes to invalid fields
- Display error messages below fields
- Handle method spoofing for PUT/PATCH/DELETE

## Template Functions

Available in Twig templates:

| Function | Description |
|----------|-------------|
| `view('template', data)` | Render a view |
| `partial('template', data)` | Render partial (no layout) |
| `redirect(url)` | Create redirect response |
| `back()` | Redirect to previous URL |
| `old('field', default)` | Get old input value |
| `error('field')` | Get first error for field |
| `fieldErrors('field')` | Get all errors for field |
| `hasError('field')` | Check if field has errors |
| `hasErrors()` | Check if any errors exist |
| `allErrors()` | Get all error messages |
| `errorBag()` | Get ErrorBag instance |
| `csrfToken()` | Get CSRF token value |
| `csrfField()` | Render CSRF hidden input |
| `methodField('PUT')` | Render method spoofing hidden input |
| `formOpen(action, method, attrs)` | Open form tag with CSRF |
| `formClose()` | Close form tag |
| `inputText(name, options)` | Text input with label/error |
| `inputEmail(name, options)` | Email input with label/error |
| `inputPassword(name, options)` | Password input with label/error |
| `inputNumber(name, options)` | Number input with label/error |
| `inputHidden(name, value)` | Hidden input field |
| `textarea(name, options)` | Textarea with label/error |
| `select(name, options, choices)` | Select dropdown with label/error |
| `checkbox(name, options)` | Checkbox with label |
| `radio(name, value, options)` | Radio button with label |
| `submitButton(text, options)` | Submit button |
| `asset('path')` | Resolve Vite asset path |
| `route('name')` | Generate route URL |
| `component('name', props)` | Render a component |
| `showFlashMessage('key')` | Display flash message |

## CLI Commands

Generate boilerplate with craft commands:

```bash
# Create a view template
php lalaz craft:view users/index

# Create a component
php lalaz craft:component Alert --props="type,message"

# Create a view composer
php lalaz craft:composer NavigationComposer --views="layouts/*"
```

## API Reference

### ViewResponse

```php
$response = view('template', $data, $statusCode)
    ->layout('layouts/main')
    ->with('key', 'value')
    ->header('X-Custom', 'value')
    ->status(201);
```

### RedirectResponse

```php
$response = redirect('/url')
    ->withInput()                      // Flash all input
    ->withInput(['email', 'name'])     // Flash specific fields
    ->withInputExcept(['password'])    // Flash except fields
    ->withErrors($errors)              // Flash validation errors
    ->with('key', 'value')             // Flash custom data
    ->status(301);                     // Change status code
```

### ErrorBag

```php
$bag = ErrorBag::fromArray($validatorErrors);

$bag->has('email');           // Check if field has errors
$bag->has();                  // Check if any errors
$bag->first('email');         // First error for field
$bag->first();                // First error overall
$bag->get('email');           // All errors for field
$bag->all();                  // All errors as flat array
$bag->count();                // Total error count
$bag->isEmpty();              // Check if empty
$bag->keys();                 // Field names with errors
$bag->toArray();              // Convert to array
```

### FormBuilder

Use directly in PHP or via Twig helpers:

```php
use Lalaz\Web\View\Form\FormBuilder;

$form = new FormBuilder();

// Method spoofing field
$form->method('DELETE');  // <input type="hidden" name="_method" value="DELETE">

// CSRF field
$form->csrf($token);      // <input type="hidden" name="_token" value="...">

// Open/close form
$form->open('/users', 'POST', ['class' => 'form']);
$form->close();

// Input fields
$form->text('name', ['label' => 'Name', 'required' => true]);
$form->email('email', ['label' => 'Email']);
$form->password('password', ['label' => 'Password']);
$form->number('age', ['label' => 'Age', 'min' => 0, 'max' => 120]);
$form->hidden('user_id', 123);
$form->textarea('bio', ['label' => 'Bio', 'rows' => 5]);

// Select dropdown
$form->select('country', [
    'label' => 'Country',
    'placeholder' => 'Choose...'
], [
    'us' => 'United States',
    'uk' => 'United Kingdom'
]);

// Checkboxes and radios
$form->checkbox('agree', ['label' => 'I agree to terms']);
$form->radio('plan', 'basic', ['label' => 'Basic Plan']);
$form->radio('plan', 'premium', ['label' => 'Premium Plan']);

// Buttons
$form->submit('Save');
$form->button('Cancel', ['type' => 'button', 'class' => 'btn-secondary']);
```

**Input Options:**

| Option | Description |
|--------|-------------|
| `label` | Label text |
| `required` | Show required indicator |
| `placeholder` | Input placeholder text |
| `class` | CSS class for wrapper |
| `inputClass` | CSS class for input element |
| `value` | Default value (overridden by old input) |
| `id` | Custom ID (defaults to name) |
| `min`, `max`, `step` | Number input constraints |
| `rows` | Textarea rows |

### MethodSpoofingMiddleware

```php
use Lalaz\Web\Http\Middlewares\MethodSpoofingMiddleware;

// Global middleware
$router->middleware(MethodSpoofingMiddleware::class);

// Or per-route group
$router->group(['middleware' => MethodSpoofingMiddleware::class], function ($router) {
    $router->put('/users/:id', [UserController::class, 'update']);
    $router->delete('/users/:id', [UserController::class, 'destroy']);
});
```

Supports:
- Hidden `_method` form field
- `X-HTTP-Method-Override` header
- Allowed methods: PUT, PATCH, DELETE

## License

MIT

