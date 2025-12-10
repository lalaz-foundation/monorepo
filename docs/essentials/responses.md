# Responses

Lalaz provides several ways to send HTTP responses from your controllers. You can return arrays (automatically converted to JSON), use the `Response` object for full control, or use helper functions for common response types.

## Returning Arrays

The simplest way to return JSON is to return an array from your controller:

```php
public function index(): array
{
    return [
        'users' => [],
        'count' => 0,
    ];
}
```

This automatically sends a JSON response with status code 200 and `Content-Type: application/json`.

## JSON Helper Functions

Use the global helper functions for JSON responses:

### `json()`

Create a JSON response with optional status code:

```php
use function json;

public function index(): mixed
{
    return json(['users' => []], 200);
}

public function store(): mixed
{
    return json(['id' => 1, 'name' => 'John'], 201);
}
```

### `json_success()`

Create a standardized success response:

```php
use function json_success;

public function update(): mixed
{
    return json_success(['user' => $user], 'User updated successfully');
}
// Returns: {"success": true, "message": "User updated successfully", "data": {"user": {...}}}
```

### `json_error()`

Create a standardized error response:

```php
use function json_error;

public function store(): mixed
{
    return json_error('Validation failed', 422, [
        'email' => 'Invalid email address',
        'name' => 'Name is required',
    ]);
}
// Returns: {"success": false, "message": "Validation failed", "errors": {"email": "...", "name": "..."}}
```

## Using the Response Object

For more control, inject the `Response` object:

```php
use Lalaz\Web\Http\Response;

public function index(Response $response): void
{
    $response->json(['users' => []], 200);
}
```

### Setting Status Code

```php
$response->status(201);
$response->status(404);
$response->status(500);
```

### Setting Headers

```php
// Single header
$response->header('X-Custom-Header', 'value');

// Multiple headers
$response->withHeaders([
    'X-Custom-Header' => 'value',
    'X-Another-Header' => 'another-value',
]);
```

### JSON Response

```php
$response->json(['key' => 'value'], 200);
$response->json(['error' => 'Not found'], 404);
```

### Send Plain Content

```php
$response->send(
    content: '<h1>Hello World</h1>',
    statusCode: 200,
    headers: ['X-Custom' => 'value'],
    contentType: 'text/html'
);
```

### No Content Response

For responses that should have no body (like successful deletions):

```php
public function destroy(Response $response): void
{
    // Delete the resource...
    $response->noContent();  // 204 No Content
}
```

### Created Response

For newly created resources:

```php
public function store(Response $response): void
{
    // Create the resource...
    $response->created(['id' => 1, 'name' => 'New Resource']);  // 201 Created
}
```

## Redirects

Redirect users to another URL:

```php
public function login(Response $response): void
{
    $response->redirect('/dashboard');
}

// With status code
$response->redirect('/dashboard', 302);  // Temporary redirect
$response->redirect('/new-url', 301);    // Permanent redirect
```

::: warning Security
Redirects are validated to prevent open redirect vulnerabilities. Only redirects to the same host or relative paths are allowed by default.
:::

## File Downloads

Send a file for download:

```php
public function download(Response $response): void
{
    $response->download(
        filePath: '/path/to/report.pdf',
        fileName: 'monthly-report.pdf',
        headers: ['X-Custom-Header' => 'value']
    );
}
```

The file is streamed efficiently without loading it entirely into memory.

## Streaming Responses

For large responses or real-time data, use streaming:

```php
public function export(Response $response): void
{
    $response->stream(function (callable $write): void {
        $write("data: Starting export...\n\n");
        
        foreach ($this->getLargeDataset() as $row) {
            $write(json_encode($row) . "\n");
        }
        
        $write("data: Export complete\n\n");
    }, 200, [
        'Content-Type' => 'text/event-stream',
        'Cache-Control' => 'no-cache',
    ]);
}
```

## JsonResponse Class

The `JsonResponse` class provides a fluent interface for building JSON responses:

```php
use Lalaz\Web\Http\JsonResponse;

// Basic usage
return new JsonResponse(['users' => []], 200);

// Using static factory
return JsonResponse::create(['users' => []], 200);

// Success response
return JsonResponse::success(['user' => $user], 'Created successfully');

// Error response
return JsonResponse::error('Not found', 404);
return JsonResponse::error('Validation failed', 422, ['email' => 'Invalid']);
```

### Fluent Methods

```php
return JsonResponse::create(['user' => $user])
    ->status(201)
    ->header('X-Custom', 'value')
    ->with('meta', ['version' => '1.0'])
    ->pretty();  // Enable pretty printing
```

### Adding Data

```php
$response = JsonResponse::create(['user' => $user]);

// Add single value
$response->with('timestamp', time());

// Add multiple values
$response->with([
    'timestamp' => time(),
    'version' => '1.0',
]);
```

## Common Response Patterns

### Success with Data

```php
public function show(Request $request): array
{
    $id = $request->routeParam('id');
    $user = $this->findUser($id);
    
    return ['user' => $user];
}
```

### Created Resource

```php
public function store(Request $request): mixed
{
    $data = $request->json();
    $user = $this->createUser($data);
    
    return json(['user' => $user], 201);
}
```

### Validation Error

```php
public function store(Request $request): mixed
{
    $errors = $this->validate($request->json());
    
    if (!empty($errors)) {
        return json_error('Validation failed', 422, $errors);
    }
    
    // Continue with creation...
}
```

### Not Found

```php
public function show(Request $request): mixed
{
    $id = $request->routeParam('id');
    $user = $this->findUser($id);
    
    if (!$user) {
        return json_error('User not found', 404);
    }
    
    return ['user' => $user];
}
```

### No Content (Delete)

```php
public function destroy(Request $request, Response $response): void
{
    $id = $request->routeParam('id');
    $this->deleteUser($id);
    
    $response->noContent();
}
```

## HTTP Status Codes Reference

| Code | Constant | Description |
|------|----------|-------------|
| 200 | OK | Successful request |
| 201 | Created | Resource created |
| 204 | No Content | Success with no body |
| 301 | Moved Permanently | Permanent redirect |
| 302 | Found | Temporary redirect |
| 400 | Bad Request | Invalid request |
| 401 | Unauthorized | Authentication required |
| 403 | Forbidden | Access denied |
| 404 | Not Found | Resource not found |
| 422 | Unprocessable Entity | Validation error |
| 500 | Internal Server Error | Server error |

## Response Method Summary

| Method | Description |
|--------|-------------|
| `status($code)` | Set HTTP status code |
| `header($name, $value)` | Add a response header |
| `withHeaders($headers)` | Add multiple headers |
| `json($data, $code)` | Send JSON response |
| `send($content, $code, $headers, $type)` | Send plain content |
| `noContent()` | Send 204 No Content |
| `created($data)` | Send 201 Created with JSON |
| `redirect($url, $code)` | Redirect to URL |
| `download($path, $name, $headers)` | Send file download |
| `stream($callback, $code, $headers)` | Send streaming response |

## Next Steps

- [Requests](/essentials/requests) - Accessing request data
- [Controllers](/essentials/controllers) - Controller patterns
- [Routing](/essentials/routing) - Route definitions
