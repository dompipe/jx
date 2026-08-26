
        try { include $path; } catch (Throwable $e) { ob_end_clean(); return '<pre>leaf error</pre>'; }
        return (string)ob_get_clean();
    }
    private function wrapDocument(Book $book, Binding $bind, string $inner, ?string $pasl): string {
        $title = htmlspecialchars((string)($book->config()['title'] ?? $book->id()), ENT_QUOTES, 'UTF-8');
        $here = htmlspecialchars($bind->here(), ENT_QUOTES, 'UTF-8');
        $bookId = htmlspecialchars($book->id(), ENT_QUOTES, 'UTF-8');
        $runtime = '';
        if ($pasl !== null) {
            $window = JxHostProtocol::openWindow($book, $bind);
            $windowJson = htmlspecialchars((string)json_encode($window, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
            $programJson = json_encode($pasl, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES);
            $runtime = "<script type=\"application/jx-pasl\" data-window=\"{$windowJson}\">{$programJson}</script>"
                . '<script src="/jx/assets/pasl-vm.js" defer></script><script src="/jx/assets/jx-browser-host.js" defer></script>';
        }
        $hostLabel = $pasl === null ? 'no JS courier' : 'browser PASL host';
        return "<!DOCTYPE html><html lang=\"en\"><head><meta charset=\"utf-8\"><title>{$title} · {$here}</title>
<style>body{font-family:system-ui,sans-serif;margin:1.5rem;max-width:52rem}nav{margin-bottom:1rem}table{border-collapse:collapse;width:100%}th,td{border:1px solid #ddd;padding:.4rem}.meta{color:#666;font-size:.85rem}#xi-root{border:1px solid #e0e0e0;border-radius:8px;padding:1rem}</style>
</head><body><p class=\"meta\">book=<strong>{$bookId}</strong> leaf=<strong>{$here}</strong> · XIP · {$hostLabel}</p>
<nav>
<form method=\"post\" style=\"display:inline\"><input type=\"hidden\" name=\"book\" value=\"{$bookId}\"><input type=\"hidden\" name=\"protocol\" value=\"book.turn\"><input type=\"hidden\" name=\"dir\" value=\"back\"><button type=\"submit\">Back</button></form>
<form method=\"post\" style=\"display:inline\"><input type=\"hidden\" name=\"book\" value=\"{$bookId}\"><input type=\"hidden\" name=\"protocol\" value=\"book.turn\"><input type=\"hidden\" name=\"dir\" value=\"forward\"><button type=\"submit\">Forward</button></form>
</nav><div id=\"xi-root\">{$inner}</div>{$runtime}</body></html>";
    }
    private function loadBinding(Book $book, ChannelBus $bus): Binding {
        $path = $book->bindingPath($this->dataRoot);
        $dir = dirname($path);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        if (is_file($path)) {
            $snap = json_decode((string)file_get_contents($path), true);
            if (is_array($snap)) {
                $snap['bookId'] = $book->id();
                $snap['spine'] = $book->spine();
                $snap['leafMeta'] = $book->leafMeta();
                $snap['tables'] = $book->tables();
                return Binding::restore($snap, $bus);
            }
        }
        return new Binding($book->id(), $book->spine(), $bus, 0, [], $book->leafMeta(), $book->tables());
    }
    private function saveBinding(Book $book, Binding $bind): void {
        $path = $book->bindingPath($this->dataRoot);
        $dir = dirname($path);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents($path, json_encode($bind->snapshot(), JSON_PRETTY_PRINT));
    }
    private function normalize(array $http): array {
        $get = is_array($http['get'] ?? null) ? $http['get'] : [];
        $post = is_array($http['post'] ?? null) ? $http['post'] : [];
        $book = (string)($post['book'] ?? $get['book'] ?? $this->siteConfig['default_book'] ?? 'cover');
        $book = preg_replace('/[^a-z0-9_-]/i', '', $book) ?: 'cover';
        return ['method' => strtoupper((string)($http['method'] ?? 'GET')), 'path' => (string)($http['path'] ?? '/'), 'book' => $book, 'protocol' => (string)($post['protocol'] ?? $get['protocol'] ?? ''), 'fields' => $post, 'get' => $get];
    }
    private function html(int $status, string $body): array {
        return ['status' => $status, 'headers' => ['Content-Type' => 'text/html; charset=utf-8'], 'body' => '<!DOCTYPE html><html><body>' . $body . '</body></html>'];
    }
    private function json(int $status, array $body): array {
        return ['status' => $status, 'headers' => ['Content-Type' => 'application/json; charset=utf-8'], 'body' => (string)json_encode($body, JSON_UNESCAPED_SLASHES)];
    }
}
