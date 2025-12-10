# Form Handling

Complete form handling with validation, CSRF protection, and error display.

---

## Overview

This example demonstrates:

- **FormBuilder** — Generate form elements
- **ErrorBag** — Display validation errors
- **RedirectResponse** — Preserve input on errors
- **CsrfProtection** — Secure forms

---

## Contact Form Example

### Controller

```php
<?php

namespace App\Controllers;

use Lalaz\Core\Controller;
use Lalaz\Http\Request\RequestInterface;
use Lalaz\Http\Response\Response;
use Lalaz\Web\View\ErrorBag;
use App\Mail\ContactNotification;

class ContactController extends Controller
{
    /**
     * Show contact form
     */
    public function show()
    {
        return view('contact/form', [
            'subjects' => [
                'general' => 'General Inquiry',
                'support' => 'Technical Support',
                'sales' => 'Sales Question',
                'feedback' => 'Feedback',
            ],
        ]);
    }
    
    /**
     * Process contact form
     */
    public function submit(RequestInterface $request): Response
    {
        $body = $request->body();
        $errors = new ErrorBag();
        
        // Validate name
        $name = trim($body['name'] ?? '');
        if (empty($name)) {
            $errors->add('name', 'Name is required');
        } elseif (strlen($name) < 2) {
            $errors->add('name', 'Name must be at least 2 characters');
        }
        
        // Validate email
        $email = trim($body['email'] ?? '');
        if (empty($email)) {
            $errors->add('email', 'Email is required');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors->add('email', 'Please enter a valid email address');
        }
        
        // Validate subject
        $subject = $body['subject'] ?? '';
        $validSubjects = ['general', 'support', 'sales', 'feedback'];
        if (empty($subject)) {
            $errors->add('subject', 'Please select a subject');
        } elseif (!in_array($subject, $validSubjects)) {
            $errors->add('subject', 'Invalid subject selected');
        }
        
        // Validate message
        $message = trim($body['message'] ?? '');
        if (empty($message)) {
            $errors->add('message', 'Message is required');
        } elseif (strlen($message) < 10) {
            $errors->add('message', 'Message must be at least 10 characters');
        } elseif (strlen($message) > 5000) {
            $errors->add('message', 'Message cannot exceed 5000 characters');
        }
        
        // Check for errors
        if ($errors->any()) {
            return redirect('/contact')
                ->withErrors($errors->messages())
                ->withInput([
                    'name' => $name,
                    'email' => $email,
                    'subject' => $subject,
                    'message' => $message,
                ]);
        }
        
        // Process form (send email, save to database, etc.)
        $this->sendContactEmail([
            'name' => $name,
            'email' => $email,
            'subject' => $subject,
            'message' => $message,
        ]);
        
        return redirect('/contact')
            ->with('success', 'Thank you for your message! We will respond within 24 hours.');
    }
    
    private function sendContactEmail(array $data): void
    {
        // Send notification email
        // Mail::send(new ContactNotification($data));
    }
}
```

---

### Form Template

```twig
{# resources/views/contact/form.twig #}
{% extends "layouts/main.twig" %}

{% block title %}Contact Us{% endblock %}

{% block content %}
<div class="container">
    <h1>Contact Us</h1>
    
    {# Success message #}
    {% if flash.success %}
        <div class="alert alert-success">
            <i class="icon-check"></i>
            {{ flash.success }}
        </div>
    {% else %}
        <p class="lead">Have a question? Send us a message!</p>
        
        {# Display general errors #}
        {% if errors.any() %}
            <div class="alert alert-danger">
                <strong>Please correct the following errors:</strong>
                <ul>
                    {% for field, messages in errors.messages() %}
                        {% for message in messages %}
                            <li>{{ message }}</li>
                        {% endfor %}
                    {% endfor %}
                </ul>
            </div>
        {% endif %}
        
        <form action="/contact" method="POST" class="contact-form">
            {{ csrf_field()|raw }}
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="name">Your Name <span class="required">*</span></label>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            value="{{ old('name') }}"
                            class="form-control {{ errors.has('name') ? 'is-invalid' : '' }}"
                            placeholder="John Doe"
                            required
                        >
                        {% if errors.has('name') %}
                            <div class="invalid-feedback">{{ errors.first('name') }}</div>
                        {% endif %}
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            value="{{ old('email') }}"
                            class="form-control {{ errors.has('email') ? 'is-invalid' : '' }}"
                            placeholder="john@example.com"
                            required
                        >
                        {% if errors.has('email') %}
                            <div class="invalid-feedback">{{ errors.first('email') }}</div>
                        {% endif %}
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="subject">Subject <span class="required">*</span></label>
                <select 
                    id="subject" 
                    name="subject" 
                    class="form-control {{ errors.has('subject') ? 'is-invalid' : '' }}"
                    required
                >
                    <option value="">-- Select a subject --</option>
                    {% for value, label in subjects %}
                        <option 
                            value="{{ value }}" 
                            {{ old('subject') == value ? 'selected' : '' }}
                        >
                            {{ label }}
                        </option>
                    {% endfor %}
                </select>
                {% if errors.has('subject') %}
                    <div class="invalid-feedback">{{ errors.first('subject') }}</div>
                {% endif %}
            </div>
            
            <div class="form-group">
                <label for="message">Message <span class="required">*</span></label>
                <textarea 
                    id="message" 
                    name="message" 
                    rows="6"
                    class="form-control {{ errors.has('message') ? 'is-invalid' : '' }}"
                    placeholder="How can we help you?"
                    required
                >{{ old('message') }}</textarea>
                {% if errors.has('message') %}
                    <div class="invalid-feedback">{{ errors.first('message') }}</div>
                {% endif %}
                <small class="form-text text-muted">
                    Minimum 10 characters, maximum 5000 characters.
                </small>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="icon-send"></i> Send Message
                </button>
            </div>
        </form>
    {% endif %}
</div>
{% endblock %}
```

---

## Using FormBuilder

For programmatic form generation:

```php
<?php

use Lalaz\Web\View\FormBuilder;

$form = new FormBuilder();
?>

<?= $form->open('/contact', 'POST', ['class' => 'contact-form']) ?>
    <?= $form->csrf() ?>
    
    <div class="form-group">
        <?= $form->label('name', 'Your Name') ?>
        <?= $form->text('name', old('name'), [
            'class' => 'form-control' . (errors()->has('name') ? ' is-invalid' : ''),
            'required' => true,
            'placeholder' => 'John Doe',
        ]) ?>
    </div>
    
    <div class="form-group">
        <?= $form->label('email', 'Email Address') ?>
        <?= $form->email('email', old('email'), [
            'class' => 'form-control' . (errors()->has('email') ? ' is-invalid' : ''),
            'required' => true,
        ]) ?>
    </div>
    
    <div class="form-group">
        <?= $form->label('subject', 'Subject') ?>
        <?= $form->select('subject', $subjects, old('subject'), [
            'class' => 'form-control',
            'required' => true,
        ]) ?>
    </div>
    
    <div class="form-group">
        <?= $form->label('message', 'Message') ?>
        <?= $form->textarea('message', old('message'), [
            'class' => 'form-control',
            'rows' => 6,
            'required' => true,
        ]) ?>
    </div>
    
    <?= $form->submit('Send Message', ['class' => 'btn btn-primary']) ?>
<?= $form->close() ?>
```

---

## AJAX Form Submission

Handle forms with JavaScript:

```javascript
// contact.js
document.querySelector('.contact-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const form = e.target;
    const formData = new FormData(form);
    const submitBtn = form.querySelector('[type="submit"]');
    
    // Disable button
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner"></span> Sending...';
    
    try {
        const response = await fetch('/api/contact', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('[name="_csrf_token"]').value,
                'Accept': 'application/json',
            },
            body: formData,
        });
        
        const data = await response.json();
        
        if (response.ok) {
            // Success
            showAlert('success', data.message);
            form.reset();
        } else {
            // Validation errors
            displayErrors(data.errors);
        }
    } catch (error) {
        showAlert('error', 'An error occurred. Please try again.');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="icon-send"></i> Send Message';
    }
});

function displayErrors(errors) {
    // Clear previous errors
    document.querySelectorAll('.is-invalid').forEach(el => {
        el.classList.remove('is-invalid');
    });
    document.querySelectorAll('.invalid-feedback').forEach(el => {
        el.remove();
    });
    
    // Display new errors
    Object.entries(errors).forEach(([field, messages]) => {
        const input = document.querySelector(`[name="${field}"]`);
        if (input) {
            input.classList.add('is-invalid');
            const feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            feedback.textContent = messages[0];
            input.parentNode.appendChild(feedback);
        }
    });
}
```

API endpoint for AJAX:

```php
// In API controller
public function apiSubmit(RequestInterface $request): Response
{
    $errors = $this->validate($request);
    
    if ($errors->any()) {
        return response()->json([
            'success' => false,
            'errors' => $errors->messages(),
        ], 422);
    }
    
    // Process...
    
    return response()->json([
        'success' => true,
        'message' => 'Thank you for your message!',
    ]);
}
```

---

## Multi-Field Validation

Using Validator package integration:

```php
<?php

use Lalaz\Validator\Validator;

class ContactController extends Controller
{
    public function submit(RequestInterface $request): Response
    {
        $validator = new Validator($request->body(), [
            'name' => 'required|string|min:2|max:100',
            'email' => 'required|email',
            'subject' => 'required|in:general,support,sales,feedback',
            'message' => 'required|string|min:10|max:5000',
            'phone' => 'nullable|phone',
        ]);
        
        if ($validator->fails()) {
            return redirect('/contact')
                ->withErrors($validator->errors())
                ->withInput();
        }
        
        // Process validated data
        $data = $validator->validated();
        
        return redirect('/contact')->with('success', 'Message sent!');
    }
}
```

---

## File Upload Form

Handling file uploads:

```php
// Controller
public function uploadAvatar(RequestInterface $request): Response
{
    $file = $request->file('avatar');
    
    $errors = new ErrorBag();
    
    if (!$file || $file->error !== UPLOAD_ERR_OK) {
        $errors->add('avatar', 'Please select a file to upload');
    } elseif ($file->size > 2 * 1024 * 1024) {
        $errors->add('avatar', 'File size cannot exceed 2MB');
    } elseif (!in_array($file->type, ['image/jpeg', 'image/png', 'image/gif'])) {
        $errors->add('avatar', 'Only JPEG, PNG, and GIF images are allowed');
    }
    
    if ($errors->any()) {
        return redirect('/profile/avatar')
            ->withErrors($errors->messages());
    }
    
    // Save file
    $filename = uniqid() . '.' . pathinfo($file->name, PATHINFO_EXTENSION);
    move_uploaded_file($file->tmp_name, storage_path('avatars/' . $filename));
    
    return redirect('/profile')
        ->with('success', 'Avatar updated!');
}
```

Template:

```twig
<form action="/profile/avatar" method="POST" enctype="multipart/form-data">
    {{ csrf_field()|raw }}
    
    <div class="form-group">
        <label for="avatar">Profile Picture</label>
        <input 
            type="file" 
            id="avatar" 
            name="avatar"
            accept="image/jpeg,image/png,image/gif"
            class="form-control-file {{ errors.has('avatar') ? 'is-invalid' : '' }}"
        >
        {% if errors.has('avatar') %}
            <div class="invalid-feedback">{{ errors.first('avatar') }}</div>
        {% endif %}
        <small class="form-text text-muted">
            Max 2MB. Accepted formats: JPEG, PNG, GIF.
        </small>
    </div>
    
    <button type="submit" class="btn btn-primary">Upload</button>
</form>
```

---

## See Also

- [Multi-Step Forms](./multi-step-forms.md) — Wizard pattern implementation
- [CSRF Protection](./csrf-protection.md) — Security for forms
- [View Module](../view/index.md) — View system overview
