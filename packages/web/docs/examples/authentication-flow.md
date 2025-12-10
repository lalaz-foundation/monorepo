# User Authentication Flow

Complete authentication flow with session management, flash messages, and redirects.

---

## Overview

This example shows a full login/logout flow using:

- **SessionManager** — Store user state
- **CsrfProtection** — Protect login form
- **RedirectResponse** — Handle navigation with flash messages
- **ViewDataBag** — Preserve form input on errors

---

## Login Controller

```php
<?php

namespace App\Controllers;

use Lalaz\Core\Controller;
use Lalaz\Http\Request\RequestInterface;
use Lalaz\Http\Response\Response;
use Lalaz\Web\Http\SessionManager;
use Lalaz\Web\Http\RedirectResponse;
use App\Models\User;

class AuthController extends Controller
{
    private SessionManager $session;
    
    public function __construct()
    {
        $this->session = new SessionManager();
    }
    
    /**
     * Show login form
     */
    public function showLogin()
    {
        // Redirect if already logged in
        if ($this->session->has('user_id')) {
            return redirect('/dashboard');
        }
        
        return view('auth/login');
    }
    
    /**
     * Process login
     */
    public function login(RequestInterface $request): Response
    {
        $body = $request->body();
        $email = $body['email'] ?? '';
        $password = $body['password'] ?? '';
        
        // Validate
        $errors = [];
        if (empty($email)) {
            $errors['email'] = 'Email is required';
        }
        if (empty($password)) {
            $errors['password'] = 'Password is required';
        }
        
        if (!empty($errors)) {
            return redirect('/login')
                ->withErrors($errors)
                ->withInput(['email' => $email]);
        }
        
        // Authenticate
        $user = User::where('email', $email)->first();
        
        if (!$user || !password_verify($password, $user->password)) {
            return redirect('/login')
                ->withErrors(['email' => 'Invalid credentials'])
                ->withInput(['email' => $email]);
        }
        
        // Regenerate session for security
        $this->session->regenerate();
        
        // Store user data
        $this->session->set('user_id', $user->id);
        $this->session->set('user_name', $user->name);
        $this->session->set('logged_in_at', time());
        
        // Flash success message
        $this->session->flash('success', 'Welcome back, ' . $user->name . '!');
        
        // Redirect to intended URL or dashboard
        $intended = $this->session->pull('intended_url', '/dashboard');
        
        return redirect($intended);
    }
    
    /**
     * Logout user
     */
    public function logout(): Response
    {
        $this->session->destroy();
        
        return redirect('/login')
            ->with('success', 'You have been logged out.');
    }
}
```

---

## Login Template

```twig
{# resources/views/auth/login.twig #}
{% extends "layouts/main.twig" %}

{% block title %}Login{% endblock %}

{% block content %}
<div class="login-container">
    <h1>Login</h1>
    
    {# Flash messages #}
    {% if flash.success %}
        <div class="alert alert-success">
            {{ flash.success }}
        </div>
    {% endif %}
    
    {% if flash.error %}
        <div class="alert alert-danger">
            {{ flash.error }}
        </div>
    {% endif %}
    
    <form action="/login" method="POST" class="login-form">
        {{ csrf_field()|raw }}
        
        <div class="form-group">
            <label for="email">Email Address</label>
            <input 
                type="email" 
                id="email" 
                name="email" 
                value="{{ old('email') }}"
                class="form-control {{ errors.has('email') ? 'is-invalid' : '' }}"
                required
                autofocus
            >
            {% if errors.has('email') %}
                <div class="invalid-feedback">
                    {{ errors.first('email') }}
                </div>
            {% endif %}
        </div>
        
        <div class="form-group">
            <label for="password">Password</label>
            <input 
                type="password" 
                id="password" 
                name="password" 
                class="form-control {{ errors.has('password') ? 'is-invalid' : '' }}"
                required
            >
            {% if errors.has('password') %}
                <div class="invalid-feedback">
                    {{ errors.first('password') }}
                </div>
            {% endif %}
        </div>
        
        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="remember" value="1">
                Remember me
            </label>
        </div>
        
        <button type="submit" class="btn btn-primary">
            Login
        </button>
        
        <p class="mt-3">
            <a href="/forgot-password">Forgot your password?</a>
        </p>
    </form>
</div>
{% endblock %}
```

---

## Auth Middleware

Protect routes requiring authentication:

```php
<?php

namespace App\Middleware;

use Lalaz\Http\Request\RequestInterface;
use Lalaz\Http\Response\Response;
use Lalaz\Web\Http\SessionManager;

class AuthMiddleware
{
    public function handle(RequestInterface $request, callable $next): Response
    {
        $session = new SessionManager();
        
        if (!$session->has('user_id')) {
            // Store intended URL for redirect after login
            $session->set('intended_url', $request->uri());
            
            return redirect('/login')
                ->with('error', 'Please login to continue.');
        }
        
        return $next($request);
    }
}
```

---

## Routes Configuration

```php
<?php
// routes.php

use Lalaz\Routing\Router;
use App\Controllers\AuthController;
use App\Middleware\AuthMiddleware;

// Public auth routes
Router::get('/login', [AuthController::class, 'showLogin']);
Router::post('/login', [AuthController::class, 'login']);
Router::post('/logout', [AuthController::class, 'logout']);

// Protected routes
Router::group(['middleware' => AuthMiddleware::class], function () {
    Router::get('/dashboard', [DashboardController::class, 'index']);
    Router::get('/profile', [ProfileController::class, 'show']);
});
```

---

## Session Security Enhancements

Add fingerprinting and idle timeout:

```php
<?php

namespace App\Middleware;

use Lalaz\Http\Request\RequestInterface;
use Lalaz\Http\Response\Response;
use Lalaz\Web\Http\SessionManager;
use Lalaz\Web\Security\Fingerprint;

class SecureSessionMiddleware
{
    private const IDLE_TIMEOUT = 1800; // 30 minutes
    
    public function handle(RequestInterface $request, callable $next): Response
    {
        $session = new SessionManager();
        
        // Check if logged in
        if (!$session->has('user_id')) {
            return $next($request);
        }
        
        // Check idle timeout
        $lastActivity = $session->get('last_activity', 0);
        if (time() - $lastActivity > self::IDLE_TIMEOUT) {
            $session->destroy();
            
            return redirect('/login')
                ->with('warning', 'Session expired due to inactivity.');
        }
        
        // Validate fingerprint
        $storedFingerprint = $session->get('fingerprint');
        if ($storedFingerprint && !Fingerprint::validate($storedFingerprint)) {
            $session->destroy();
            
            return redirect('/login')
                ->with('error', 'Session invalidated for security.');
        }
        
        // Update activity
        $session->set('last_activity', time());
        
        return $next($request);
    }
}
```

Store fingerprint on login:

```php
// In AuthController::login() after successful authentication
use Lalaz\Web\Security\Fingerprint;

$this->session->set('fingerprint', Fingerprint::generate());
```

---

## Testing Auth Flow

```php
<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Models\User;

class AuthenticationTest extends TestCase
{
    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
        ]);
        
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);
        
        $response->assertRedirect('/dashboard');
        $this->assertTrue(session()->has('user_id'));
    }
    
    public function test_user_cannot_login_with_invalid_password(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
        ]);
        
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'wrong',
        ]);
        
        $response->assertRedirect('/login');
        $response->assertHasError('email');
        $this->assertFalse(session()->has('user_id'));
    }
    
    public function test_user_can_logout(): void
    {
        $this->login();
        
        $response = $this->post('/logout');
        
        $response->assertRedirect('/login');
        $this->assertFalse(session()->has('user_id'));
    }
}
```

---

## See Also

- [Session Security](./session-security.md) — Advanced session protection
- [CSRF Protection](./csrf-protection.md) — Protecting forms
- [Security Module](../security/index.md) — Security features overview
