```markdown
# View System Overview

The View module provides a complete templating system built on Twig, including reusable components, form builders, and error handling.

---

## Components

| Component | Description |
|-----------|-------------|
| [Template Engine](./template-engine.md) | Twig integration for rendering views |
| [View Context](./view-context.md) | Global shared view data |
| [Form Builder](./form-builder.md) | HTML form generation |
| [Error Bag](./error-bag.md) | Validation error management |
| [View Functions](./view-functions.md) | Custom Twig functions |
| [Components](./components.md) | Reusable UI components |

---

## Quick Reference

### Rendering Views

```php
use Lalaz\Web\View\View;

// Simple view
return View::make('posts/index');

// With data
return View::make('posts/show', [
    'post' => $post,
    'comments' => $comments,
]);
```

### View Context (Global Data)

```php
use Lalaz\Web\View\ViewContext;

// Share data across all views
ViewContext::set('user', $currentUser);
ViewContext::set('notifications', Notification::unread());

// Access in templates
{{ user.name }}
```

### Form Builder

```php
use Lalaz\Web\View\FormBuilder;

$form = new FormBuilder();

echo $form->open('/posts', 'POST');
echo $form->csrf();
echo $form->text('title', old('title'));
echo $form->submit('Create');
echo $form->close();
```

### Error Bag

```php
use Lalaz\Web\View\ErrorBag;

$errors = errors();

$errors->add('email', 'Invalid email');
$errors->has('email');           // true
$errors->first('email');         // 'Invalid email'
$errors->all('email');           // ['Invalid email']
```

### Components

```php
// Define a component
class Alert extends Component
{
    public function __construct(
        public string $type = 'info',
        public string $message = ''
    ) {}
}
```

```twig
{# Use in templates #}
{% component 'Alert', {type: 'success', message: 'Saved!'} %}
```

---

## Architecture

```
┌─────────────────────────────────────────────────┐
│                View Module                       │
├─────────────────────────────────────────────────┤
│                                                  │
│  ┌──────────────────────────────────────────┐   │
│  │            Template Engine                │   │
│  │                 (Twig)                    │   │
│  │                                           │   │
│  │  - render()                               │   │
│  │  - share()                                │   │
│  │  - addFunction()                          │   │
│  └─────────────────┬────────────────────────┘   │
│                    │                             │
│     ┌──────────────┼──────────────┐             │
│     ▼              ▼              ▼             │
│  ┌────────┐  ┌──────────┐  ┌────────────┐      │
│  │ View   │  │  View    │  │ View       │      │
│  │Context │  │Functions │  │ Helpers    │      │
│  └────────┘  └──────────┘  └────────────┘      │
│                                                  │
│  ┌──────────────────────────────────────────┐   │
│  │              Form Builder                 │   │
│  │                                           │   │
│  │  - open/close                             │   │
│  │  - text/textarea/select                   │   │
│  │  - csrf                                   │   │
│  └──────────────────────────────────────────┘   │
│                                                  │
│  ┌──────────────┐  ┌────────────────────────┐   │
│  │   ErrorBag   │  │      Components        │   │
│  │              │  │                        │   │
│  │  - add()     │  │  - Component base      │   │
│  │  - has()     │  │  - ComponentRegistry   │   │
│  │  - first()   │  │  - ComponentRenderer   │   │
│  └──────────────┘  └────────────────────────┘   │
│                                                  │
└─────────────────────────────────────────────────┘
```

---

## Template Structure

### Recommended Layout

```
resources/
└── views/
    ├── layouts/
    │   ├── app.twig          # Main layout
    │   └── auth.twig         # Auth pages layout
    ├── components/
    │   ├── alert.twig
    │   ├── button.twig
    │   └── card.twig
    ├── partials/
    │   ├── header.twig
    │   ├── footer.twig
    │   └── sidebar.twig
    ├── pages/
    │   ├── home.twig
    │   └── about.twig
    └── posts/
        ├── index.twig
        ├── show.twig
        ├── create.twig
        └── edit.twig
```

### Base Layout

```twig
{# layouts/app.twig #}
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{% block title %}My App{% endblock %}</title>
    {% block head %}{% endblock %}
</head>
<body>
    {% include 'partials/header.twig' %}
    
    <main>
        {% if flash('success') %}
            {% component 'Alert', {type: 'success', message: flash('success')} %}
        {% endif %}
        
        {% if flash('error') %}
            {% component 'Alert', {type: 'danger', message: flash('error')} %}
        {% endif %}
        
        {% block content %}{% endblock %}
    </main>
    
    {% include 'partials/footer.twig' %}
    
    {% block scripts %}{% endblock %}
</body>
</html>
```

### Page Template

```twig
{# posts/create.twig #}
{% extends 'layouts/app.twig' %}

{% block title %}Create Post{% endblock %}

{% block content %}
<div class="container">
    <h1>Create New Post</h1>
    
    <form action="/posts" method="POST">
        {{ csrf_field() }}
        
        <div class="form-group">
            <label for="title">Title</label>
            <input 
                type="text" 
                id="title" 
                name="title" 
                value="{{ old('title') }}"
                class="form-control {% if errors.has('title') %}is-invalid{% endif %}"
            >
            {% if errors.has('title') %}
                <div class="invalid-feedback">
                    {{ errors.first('title') }}
                </div>
            {% endif %}
        </div>
        
        <div class="form-group">
            <label for="content">Content</label>
            <textarea 
                id="content" 
                name="content"
                class="form-control {% if errors.has('content') %}is-invalid{% endif %}"
                rows="10"
            >{{ old('content') }}</textarea>
            {% if errors.has('content') %}
                <div class="invalid-feedback">
                    {{ errors.first('content') }}
                </div>
            {% endif %}
        </div>
        
        <button type="submit" class="btn btn-primary">Create Post</button>
        <a href="/posts" class="btn btn-secondary">Cancel</a>
    </form>
</div>
{% endblock %}
```

---

## Common Patterns

### Form with Validation

```php
// Controller
public function store(Request $request)
{
    $validator = new Validator($request->all(), [
        'title' => 'required|min:3',
        'content' => 'required',
    ]);

    if ($validator->fails()) {
        return back()
            ->withInput()
            ->withErrors($validator->errors());
    }

    Post::create($request->only(['title', 'content']));
    flash('success', 'Post created!');
    
    return redirect('/posts');
}
```

```twig
{# Template #}
<input 
    type="text" 
    name="title" 
    value="{{ old('title') }}"
    class="{% if errors.has('title') %}error{% endif %}"
>
```

### Reusable Components

```php
// app/View/Components/Alert.php
class Alert extends Component
{
    public function __construct(
        public string $type = 'info',
        public string $message = '',
        public bool $dismissible = true
    ) {}
}
```

```twig
{# components/alert.twig #}
<div class="alert alert-{{ type }}{% if dismissible %} alert-dismissible{% endif %}">
    {{ message }}
    {% if dismissible %}
        <button type="button" class="close">&times;</button>
    {% endif %}
</div>
```

### Dynamic Navigation

```php
// In service provider or middleware
ViewContext::set('navigation', [
    ['url' => '/', 'label' => 'Home'],
    ['url' => '/about', 'label' => 'About'],
    ['url' => '/contact', 'label' => 'Contact'],
]);
```

```twig
{# partials/nav.twig #}
<nav>
    {% for item in navigation %}
        <a href="{{ item.url }}" 
           class="{% if request.path == item.url %}active{% endif %}">
            {{ item.label }}
        </a>
    {% endfor %}
</nav>
```

---

## Twig Cheat Sheet

### Variables

```twig
{{ variable }}
{{ object.property }}
{{ array['key'] }}
{{ object.method() }}
```

### Filters

```twig
{{ name|upper }}
{{ text|slice(0, 100) }}
{{ number|number_format(2) }}
{{ date|date('Y-m-d') }}
{{ html|raw }}
```

### Conditionals

```twig
{% if condition %}
    ...
{% elseif other %}
    ...
{% else %}
    ...
{% endif %}

{# Ternary #}
{{ condition ? 'yes' : 'no' }}

{# Null coalescing #}
{{ value ?? 'default' }}
```

### Loops

```twig
{% for item in items %}
    {{ loop.index }}   {# 1, 2, 3... #}
    {{ loop.index0 }}  {# 0, 1, 2... #}
    {{ loop.first }}   {# true on first #}
    {{ loop.last }}    {# true on last #}
{% else %}
    No items found
{% endfor %}
```

### Includes

```twig
{% include 'partials/header.twig' %}
{% include 'partials/card.twig' with {'title': 'Hello'} %}
{% include 'partials/sidebar.twig' ignore missing %}
```

### Inheritance

```twig
{# layout.twig #}
{% block content %}{% endblock %}

{# page.twig #}
{% extends 'layout.twig' %}
{% block content %}
    {{ parent() }}
    Additional content
{% endblock %}
```

---

## See Also

- [Template Engine](./template-engine.md) — Twig configuration
- [Components](./components.md) — Building reusable UI
- [Form Builder](./form-builder.md) — HTML form generation
- [Helpers](../helpers.md) — Available helper functions

```
