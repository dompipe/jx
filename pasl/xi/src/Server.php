<?php declare(strict_types=1);
/**
 * Lightweight HTTP(S) server for XIP — pure PHP sockets.
 */
final class Server
{
    public function __construct(
        private XipEngine $engine,
        private string $host,
        private int $port,
        private bool $ssl = false,
        private ?string $cert = null,
        private ?string $key = null,
    ) {}

    public function serve(): void
    {
        $cert = getenv('XI_SSL_CERT') ?: $this->cert;
        $key = getenv('XI_SSL_KEY') ?: $this->key;
        $ssl = $this->ssl || ($cert && $key);

        if ($ssl) {
            if (!$cert || !$key || !is_file($cert) || !is_file($key)) {
                fwrite(STDERR, "xi: SSL requested but cert/key missing (XI_SSL_CERT / XI_SSL_KEY)\n");
                exit(1);
            }
            $ctx = stream_context_create(['ssl' => [
                'local_cert'        => $cert,
                'local_pk'          => $key,
                'allow_self_signed' => true,
                'verify_peer'       => false,
            ]]);
            $addr = "tls://{$this->host}:{$this->port}";
            $server = @stream_socket_server($addr, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $ctx);
        } else {
            $server = @stream_socket_server("tcp://{$this->host}:{$this->port}", $errno, $errstr);
        }

        if (!$server) {
            fwrite(STDERR, "xi: bind failed {$this->host}:{$this->port} — {$errstr}\n");
            exit(1);
        }

        $scheme = $ssl ? 'https' : 'http';
        echo "xi: XIP serving {$scheme}://{$this->host}:{$this->port}/  pid=" . getmypid() . "\n";

        while (true) {
            $conn = @stream_socket_accept($server, -1);
            if (!$conn) {
                continue;
            }
            try {
                $this->handleConn($conn);
            } catch (Throwable $e) {
                fwrite(STDERR, 'xi: ' . $e->getMessage() . "\n");
            }
            fclose($conn);
        }
    }

    /** @param resource $conn */
    private function handleConn($conn): void
    {
        stream_set_timeout($conn, 15);
        $raw = '';
        while (!str_contains($raw, "\r\n\r\n")) {
            $chunk = fread($conn, 8192);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $raw .= $chunk;
            if (strlen($raw) > 1024 * 1024) {
                break;
            }
        }
        if ($raw === '') {
            return;
        }

        $headerEnd = strpos($raw, "\r\n\r\n");
        $head = $headerEnd === false ? $raw : substr($raw, 0, $headerEnd);
        $body = $headerEnd === false ? '' : substr($raw, $headerEnd + 4);
        $lines = explode("\r\n", $head);
        $reqLine = $lines[0] ?? 'GET / HTTP/1.0';
        $parts = explode(' ', $reqLine);
        $method = strtoupper($parts[0] ?? 'GET');
        $uri = $parts[1] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $query = [];
        parse_str((string)parse_url($uri, PHP_URL_QUERY), $query);

        $headers = [];
        foreach (array_slice($lines, 1) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $headers[strtolower(trim($k))] = trim($v);
            }
        }

        $len = (int)($headers['content-length'] ?? 0);
        while (strlen($body) < $len) {
            $chunk = fread($conn, $len - strlen($body));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $body .= $chunk;
        }

        $post = [];
        $ct = $headers['content-type'] ?? '';
        if (str_starts_with($ct, 'application/x-www-form-urlencoded') || $method === 'POST') {
            parse_str($body, $post);
        }

        $result = $this->engine->handle([
            'method' => $method,
            'path'   => $path,
            'get'    => $query,
            'post'   => $post,
            'files'  => [],
        ]);

        $status = (int)($result['status'] ?? 200);
        $bodyOut = (string)($result['body'] ?? '');
        $reason = $status === 404 ? 'Not Found' : 'OK';
        $hdr = "HTTP/1.0 {$status} {$reason}\r\n";
        foreach ($result['headers'] ?? [] as $k => $v) {
            $hdr .= "{$k}: {$v}\r\n";
        }
        $hdr .= 'Content-Length: ' . strlen($bodyOut) . "\r\n";
        $hdr .= "Connection: close\r\n\r\n";
        fwrite($conn, $hdr . $bodyOut);
    }
}
