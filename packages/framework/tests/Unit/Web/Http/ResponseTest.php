<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Web\Http;

use Lalaz\Web\Http\Response;
use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Lalaz\Framework\Tests\Fixtures\Fakes\FakeResponseBodyEmitter;

#[CoversClass(Response::class)]
/**
 * Tests for the Response class.
 */
final class ResponseTest extends FrameworkUnitTestCase
{
    public function testsendsBufferedContentThroughAnEmitter(): void
    {
        $response = new Response("example.test");
        $response->setBody("hello");

        $emitter = new FakeResponseBodyEmitter();
        $response->sendBody($emitter);

        $this->assertSame(["hello"], $emitter->chunks);
    }

    public function teststreamsContentWhenACallbackLeveragesTheWriter(): void
    {
        $response = new Response("example.test");
        $response->stream(function (callable $write): void {
            $write("chunk-1");
            $write("chunk-2");
        });

        $emitter = new FakeResponseBodyEmitter();
        $response->sendBody($emitter);

        $this->assertSame(["chunk-1", "chunk-2"], $emitter->chunks);
    }

    public function testcapturesEchoedOutputWhenStreamingWithoutTheWriterArgument(): void
    {
        $response = new Response("example.test");
        $response->stream(function (): void {
            echo "plain-output";
        });

        $emitter = new FakeResponseBodyEmitter();
        $response->sendBody($emitter);

        $this->assertSame(["plain-output"], $emitter->chunks);
    }

    public function teststreamsFileDownloadsWithoutBufferingTheContent(): void
    {
        $file = tempnam(sys_get_temp_dir(), "lalaz_response_test");
        $this->assertNotFalse($file);

        file_put_contents((string) $file, str_repeat("data", 100));

        $response = new Response("example.test");
        $response->download((string) $file, "test.bin");

        $emitter = new FakeResponseBodyEmitter();
        $response->sendBody($emitter);

        $this->assertTrue($response->isStreamed());
        $this->assertArrayHasKey("Content-Disposition", $response->headers());
        $this->assertSame(str_repeat("data", 100), implode("", $emitter->chunks));

        @unlink((string) $file);
    }
}
