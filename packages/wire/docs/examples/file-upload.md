# File Upload Examples

Uploading files with Wire HTTP client.

## Simple File Upload

```php
use Lalaz\Wire\HttpClient\HttpClientBuilder;

$client = HttpClientBuilder::create('https://api.example.com')
    ->timeout(120)  // Longer timeout for uploads
    ->build();

// Read file content
$fileContent = file_get_contents('/path/to/file.pdf');

$response = $client->post('/upload', [
    'headers' => [
        'Content-Type' => 'application/pdf',
        'Content-Length' => strlen($fileContent),
    ],
    'body' => $fileContent,
]);

if ($response->statusCode === 201) {
    echo "File uploaded: {$response->body['url']}\n";
}
```

## Multipart Form Data

```php
// Build multipart body manually
function buildMultipartBody(array $fields, array $files): array
{
    $boundary = '----' . md5(uniqid());
    $body = '';
    
    // Add regular fields
    foreach ($fields as $name => $value) {
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n";
        $body .= "{$value}\r\n";
    }
    
    // Add files
    foreach ($files as $name => $file) {
        $filename = basename($file['path']);
        $content = file_get_contents($file['path']);
        $mime = $file['mime'] ?? 'application/octet-stream';
        
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"{$name}\"; filename=\"{$filename}\"\r\n";
        $body .= "Content-Type: {$mime}\r\n\r\n";
        $body .= "{$content}\r\n";
    }
    
    $body .= "--{$boundary}--\r\n";
    
    return [
        'boundary' => $boundary,
        'body' => $body,
    ];
}

// Usage
$multipart = buildMultipartBody(
    fields: [
        'title' => 'My Document',
        'description' => 'A sample document',
    ],
    files: [
        'document' => [
            'path' => '/path/to/document.pdf',
            'mime' => 'application/pdf',
        ],
    ]
);

$response = $client->post('/documents', [
    'headers' => [
        'Content-Type' => "multipart/form-data; boundary={$multipart['boundary']}",
    ],
    'body' => $multipart['body'],
]);
```

## Upload with Progress (using chunks)

```php
final class ChunkedUploader
{
    public function __construct(
        private HttpClient $client,
        private int $chunkSize = 1024 * 1024  // 1MB chunks
    ) {}

    public function upload(string $filePath, string $endpoint): array
    {
        $fileSize = filesize($filePath);
        $handle = fopen($filePath, 'rb');
        $uploadId = uniqid('upload_');
        $chunkIndex = 0;
        
        while (!feof($handle)) {
            $chunk = fread($handle, $this->chunkSize);
            $offset = $chunkIndex * $this->chunkSize;
            
            $response = $this->client->post($endpoint, [
                'headers' => [
                    'Content-Type' => 'application/octet-stream',
                    'X-Upload-ID' => $uploadId,
                    'X-Chunk-Index' => $chunkIndex,
                    'X-Chunk-Offset' => $offset,
                    'X-File-Size' => $fileSize,
                ],
                'body' => $chunk,
            ]);
            
            if ($response->statusCode !== 200 && $response->statusCode !== 201) {
                fclose($handle);
                throw new \Exception("Upload failed at chunk {$chunkIndex}");
            }
            
            $chunkIndex++;
            
            // Progress callback
            $progress = min(100, ($offset + strlen($chunk)) / $fileSize * 100);
            echo sprintf("Progress: %.1f%%\n", $progress);
        }
        
        fclose($handle);
        
        return $response->body;
    }
}

// Usage
$uploader = new ChunkedUploader($client);
$result = $uploader->upload('/path/to/large-file.zip', '/api/upload');
```

## Base64 Encoded Upload

```php
$filePath = '/path/to/image.jpg';
$fileContent = file_get_contents($filePath);
$base64Content = base64_encode($fileContent);

$response = $client->post('/api/images', [
    'headers' => ['Content-Type' => 'application/json'],
    'body' => json_encode([
        'filename' => basename($filePath),
        'content' => $base64Content,
        'mime_type' => mime_content_type($filePath),
    ]),
]);

if ($response->statusCode === 201) {
    echo "Image uploaded: {$response->body['id']}\n";
}
```

## Upload Multiple Files

```php
function uploadFiles(HttpClient $client, array $filePaths): array
{
    $results = [];
    
    foreach ($filePaths as $filePath) {
        $content = file_get_contents($filePath);
        $filename = basename($filePath);
        
        $response = $client->post('/api/files', [
            'headers' => [
                'Content-Type' => mime_content_type($filePath),
                'X-Filename' => $filename,
            ],
            'body' => $content,
        ]);
        
        $results[$filename] = [
            'success' => $response->statusCode === 201,
            'data' => $response->body,
        ];
    }
    
    return $results;
}

// Usage
$results = uploadFiles($client, [
    '/path/to/file1.pdf',
    '/path/to/file2.jpg',
    '/path/to/file3.txt',
]);

foreach ($results as $filename => $result) {
    $status = $result['success'] ? '✓' : '✗';
    echo "{$status} {$filename}\n";
}
```

## S3 Presigned URL Upload

```php
// Step 1: Get presigned URL from your API
$response = $client->post('/api/upload/presign', [
    'body' => json_encode([
        'filename' => 'document.pdf',
        'content_type' => 'application/pdf',
    ]),
]);

$presignedUrl = $response->body['upload_url'];
$fileKey = $response->body['key'];

// Step 2: Upload directly to S3 using presigned URL
$s3Client = HttpClientBuilder::create()
    ->timeout(120)
    ->build();

$fileContent = file_get_contents('/path/to/document.pdf');

$uploadResponse = $s3Client->put($presignedUrl, [
    'headers' => [
        'Content-Type' => 'application/pdf',
    ],
    'body' => $fileContent,
]);

if ($uploadResponse->statusCode === 200) {
    echo "Uploaded to S3: {$fileKey}\n";
}
```
