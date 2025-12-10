<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Web\Http;

use Lalaz\Web\Http\JsonResponse;
use Lalaz\Web\Http\Response;
use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(JsonResponse::class)]
/**
 * Tests for the JsonResponse class.
 */
final class JsonResponseTest extends FrameworkUnitTestCase
{
    public function testcreatesJsonResponseWithData(): void
    {
        $data = ['name' => 'John', 'age' => 30];
        $jsonResponse = new JsonResponse($data);

        $this->assertSame($data, $jsonResponse->data());
        $this->assertSame(200, $jsonResponse->statusCode());
    }

    public function testcreatesJsonResponseWithCustomStatusCode(): void
    {
        $data = ['error' => 'Not found'];
        $jsonResponse = new JsonResponse($data, 404);

        $this->assertSame(404, $jsonResponse->statusCode());
    }

    public function testcreatesJsonResponseWithHeaders(): void
    {
        $data = ['message' => 'Success'];
        $headers = ['X-Custom-Header' => 'value'];
        $jsonResponse = new JsonResponse($data, 200);
        $jsonResponse->withHeaders($headers);

        $this->assertSame($headers, $jsonResponse->headers());
    }

    public function teststaticCreateFactoryMethod(): void
    {
        $data = ['status' => 'ok'];
        $jsonResponse = JsonResponse::create($data, 201);

        $this->assertSame($data, $jsonResponse->data());
        $this->assertSame(201, $jsonResponse->statusCode());
    }

    public function testcanSetStatusFluently(): void
    {
        $jsonResponse = new JsonResponse(['test' => true]);
        $result = $jsonResponse->status(202);

        $this->assertSame($jsonResponse, $result);
        $this->assertSame(202, $jsonResponse->statusCode());
    }

    public function testcanAddHeaderFluently(): void
    {
        $jsonResponse = new JsonResponse(['test' => true]);
        $result = $jsonResponse->header('X-Test', 'value');

        $this->assertSame($jsonResponse, $result);
        $this->assertSame(['X-Test' => 'value'], $jsonResponse->headers());
    }

    public function testcanAddMultipleHeadersFluently(): void
    {
        $headers = [
            'X-First' => 'one',
            'X-Second' => 'two',
        ];

        $jsonResponse = new JsonResponse(['test' => true]);
        $result = $jsonResponse->withHeaders($headers);

        $this->assertSame($jsonResponse, $result);
        $this->assertSame($headers, $jsonResponse->headers());
    }

    public function testcanMergeDataFluently(): void
    {
        $jsonResponse = new JsonResponse(['name' => 'John']);
        $result = $jsonResponse->with('age', 30);

        $this->assertSame($jsonResponse, $result);
        $this->assertSame(['name' => 'John', 'age' => 30], $jsonResponse->data());
    }

    public function testcanMergeArrayDataFluently(): void
    {
        $jsonResponse = new JsonResponse(['name' => 'John']);
        $result = $jsonResponse->with(['age' => 30, 'city' => 'NYC']);

        $this->assertSame($jsonResponse, $result);
        $this->assertSame(['name' => 'John', 'age' => 30, 'city' => 'NYC'], $jsonResponse->data());
    }

    public function testrendersToResponse(): void
    {
        $data = ['status' => 'success', 'data' => [1, 2, 3]];
        $jsonResponse = new JsonResponse($data, 201);
        $jsonResponse->header('X-Custom', 'value');

        $response = new Response('example.test');
        $jsonResponse->toResponse($response);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('application/json; charset=utf-8', $response->headers()['Content-Type']);
        $this->assertSame('value', $response->headers()['X-Custom']);
        $this->assertJsonStringEqualsJsonString(
            json_encode($data),
            $response->body()
        );
    }

    public function testrendersEmptyArrayCorrectly(): void
    {
        $jsonResponse = new JsonResponse([]);

        $response = new Response('example.test');
        $jsonResponse->toResponse($response);

        $this->assertSame('[]', $response->body());
    }

    public function testrendersNestedObjectsCorrectly(): void
    {
        $data = [
            'user' => [
                'name' => 'John',
                'profile' => [
                    'avatar' => 'photo.jpg',
                    'bio' => 'Hello world',
                ],
            ],
        ];

        $jsonResponse = new JsonResponse($data);

        $response = new Response('example.test');
        $jsonResponse->toResponse($response);

        $this->assertJsonStringEqualsJsonString(
            json_encode($data),
            $response->body()
        );
    }

    public function testjsonHelperFunctionCreatesJsonResponse(): void
    {
        $this->assertTrue(function_exists('json'));

        $data = ['test' => true];
        $jsonResponse = json($data, 201);
        $jsonResponse->header('X-Test', 'value');

        $this->assertInstanceOf(JsonResponse::class, $jsonResponse);
        $this->assertSame($data, $jsonResponse->data());
        $this->assertSame(201, $jsonResponse->statusCode());
        $this->assertSame(['X-Test' => 'value'], $jsonResponse->headers());
    }
}
