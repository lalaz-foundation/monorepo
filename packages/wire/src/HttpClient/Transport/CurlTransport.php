<?php

declare(strict_types=1);

namespace Lalaz\Wire\HttpClient\Transport;

use Lalaz\Wire\HttpClient\Contracts\TransportInterface;
use Lalaz\Wire\HttpClient\HttpException;
use Lalaz\Wire\HttpClient\HttpRequest;

final class CurlTransport implements TransportInterface
{
    public function send(HttpRequest $request): array
    {
        $ch = curl_init();
        if ($ch === false) {
            throw new HttpException('Unable to initialize cURL.');
        }

        $url = $request->url;
        if (!empty($request->query)) {
            $queryString = http_build_query($request->query);
            $url .= (str_contains($url, '?') ? '&' : '?') . $queryString;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => $request->followRedirects,
            CURLOPT_MAXREDIRS => $request->maxRedirects,
            CURLOPT_TIMEOUT => $request->timeout,
            CURLOPT_CONNECTTIMEOUT => $request->connectTimeout,
            CURLOPT_SSL_VERIFYPEER => !$request->skipSsl,
            CURLOPT_CUSTOMREQUEST => $request->method,
        ]);

        if (!empty($request->headers)) {
            $formatted = [];
            foreach ($request->headers as $name => $value) {
                $formatted[] = $name . ': ' . $value;
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $formatted);
        }

        if ($request->body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $request->body);
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new HttpException('cURL error: ' . $error);
        }

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $rawHeaders = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        $headers = $this->parseHeaders($rawHeaders);

        return [
            'status' => $status,
            'headers' => $headers,
            'body' => $body,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function parseHeaders(string $raw): array
    {
        $lines = explode("\r\n", $raw);
        $headers = [];
        foreach ($lines as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[trim($name)] = trim($value);
            }
        }
        return $headers;
    }
}
