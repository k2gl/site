<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Outbound HTTP over the stream wrapper (this box's curl_multi is unreliable):
 * short timeouts, an honest User-Agent, and the GitHub token attached only when
 * talking to api.github.com.
 */
final class HttpClient implements HttpClientInterface
{
    private const string USER_AGENT = 'k2gl.com-tools (+https://k2gl.com/tools)';

    private const int MAX_BODY_BYTES = 10_485_760;

    /** @var list<string> */
    private array $lastHeaders = [];

    public function __construct(private readonly int $timeoutSeconds = 10) {}

    public function get(string $url, array $headers = []): array
    {
        $stream = $this->open(url: $url, headers: $headers);
        $body = stream_get_contents($stream, self::MAX_BODY_BYTES);
        $status = $this->status();
        fclose($stream);

        if ($body === false) {
            throw new HttpProblem(status: 502, code: 'upstream_error', message: 'Upstream response could not be read.');
        }

        return ['status' => $status, 'body' => $body];
    }

    public function downloadToFile(string $url, int $maxBytes): array
    {
        $stream = $this->open(url: $url, headers: []);
        $status = $this->status();

        if ($status !== 200) {
            fclose($stream);

            throw new HttpProblem(status: 502, code: 'upstream_error', message: 'Artifact download failed upstream (HTTP ' . $status . ').');
        }

        $path = tempnam(sys_get_temp_dir(), 'k2gl-artifact-');

        if ($path === false) {
            fclose($stream);

            throw new HttpProblem(status: 500, code: 'internal', message: 'Could not allocate a temp file.');
        }

        $out = fopen($path, 'wb');
        $hash = hash_init('sha256');
        $size = 0;

        while (! feof($stream)) {
            $chunk = fread($stream, 65_536);

            if ($chunk === false || $chunk === '') {
                break;
            }

            $size += strlen($chunk);

            if ($size > $maxBytes) {
                fclose($stream);
                fclose($out);
                unlink($path);

                throw new HttpProblem(status: 422, code: 'artifact_too_large', message: 'The artifact exceeds the ' . intdiv($maxBytes, 1_048_576) . ' MiB inspection cap.');
            }

            hash_update($hash, $chunk);
            fwrite($out, $chunk);
        }

        fclose($stream);
        fclose($out);

        return ['path' => $path, 'size' => $size, 'sha256' => hash_final($hash)];
    }

    /**
     * @param array<string, string> $headers
     *
     * @return resource
     */
    private function open(string $url, array $headers)
    {
        $headers['User-Agent'] = self::USER_AGENT;

        $token = getenv('GITHUB_TOKEN');
        $host = parse_url($url, PHP_URL_HOST);

        if (is_string($token) && $token !== '' && $host === 'api.github.com') {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $lines = [];

        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $lines),
            'timeout' => $this->timeoutSeconds,
            'follow_location' => 1,
            'max_redirects' => 5,
            'ignore_errors' => true,
        ]]);

        $stream = @fopen($url, 'rb', false, $context);

        if ($stream === false) {
            throw new HttpProblem(status: 502, code: 'upstream_error', message: 'Upstream host is unreachable: ' . (string) $host);
        }

        // Read by the loops above without ever blocking past the timeout.
        stream_set_timeout($stream, $this->timeoutSeconds);
        $this->lastHeaders = $http_response_header ?? [];

        return $stream;
    }

    private function status(): int
    {
        // With follow_location the wrapper appends each hop; the last status wins.
        $status = 0;

        foreach ($this->lastHeaders as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) {
                $status = (int) $m[1];
            }
        }

        return $status;
    }
}
