<?php declare(strict_types=1);
final class XipEngine {
    public function __construct(private string $booksRoot, private string $dataRoot, private array $siteConfig = []) {
        if (!is_dir($this->dataRoot)) mkdir($this->dataRoot, 0755, true);
    }
    public function handle(array $http): array {
        $path = (string)($http['path'] ?? '/');
        if (str_starts_with($path, '/jx/assets/')) return $this->asset($path);
        $req = $this->normalize($http);
        if ($path === '/jx/drop') return $this->acceptHostDrop($req, $http);
        $book = Book::load($this->booksRoot, $req['book']);
        if ($book === null) return $this->html(404, '<h1>Book not found</h1>');
        $bus = new ChannelBus($book->channelsDir($this->dataRoot));
        $bind = $this->loadBinding($book, $bus);
        $this->processDrops($book, $bus);
        $buffer = Bag::empty();
        $pipe = $this->buildPipe($book, $bind, $bus);
        $buffer = $pipe->run($buffer, $req);
        $io = $bus->channel('io');
        $ladder = new Ladder(
            before: [function (array $in) use ($io): void {
                $io->set('in_at', time());
                $io->set('in_path', (string)($in['path'] ?? '/'));
            }],
            after: [function (array $in, array $out) use ($io): void {
                $io->set('out_status', (int)($out['status'] ?? 200));
            }],
        );
        $out = $ladder->run($req, function (array $in) use ($book, $bind, $buffer, $bus): array {
            $leaf = (string)$buffer->get('next', $bind->here());
            if ($buffer->has('next')) $bind->open($leaf);
            $leaf = $bind->here();
            $path = $book->pagePath($leaf);
            $html = ($path && is_file($path)) ? $this->renderLeaf($path, $bind, $buffer, $book, $bus) : '<h1>Missing leaf</h1>';
            return ['status' => 200, 'body' => $this->wrapDocument($book, $bind, $html, $this->browserProgram($book, $leaf))];
        });
        $this->saveBinding($book, $bind);
        $bus->save();
        return ['status' => (int)($out['status'] ?? 200), 'headers' => ['Content-Type' => 'text/html; charset=utf-8'], 'body' => (string)($out['body'] ?? '')];
    }

    /** @return array{status:int,headers:array<string,string>,body:string} */
    private function asset(string $path): array {
        $name = basename($path);
        if (!in_array($name, ['pasl-vm.js', 'jx-browser-host.js'], true)) return $this->html(404, '<h1>Asset not found</h1>');
        $file = dirname(__DIR__, 2) . '/browser/' . $name;
        if (!is_file($file)) return $this->html(404, '<h1>Asset not found</h1>');
        return ['status' => 200, 'headers' => ['Content-Type' => 'text/javascript; charset=utf-8'], 'body' => (string)file_get_contents($file)];
    }

    /** @return array{status:int,headers:array<string,string>,body:string} */
    private function acceptHostDrop(array $req, array $http): array {
        if (($req['method'] ?? '') !== 'POST') return $this->json(405, ['error' => 'POST required']);
        $raw = $http['json'] ?? null;
        if (!is_array($raw)) return $this->json(400, ['error' => 'JSON object required']);
        $book = Book::load($this->booksRoot, $req['book']);
        if ($book === null) return $this->json(404, ['error' => 'Book not found']);
        try { $drop = JxHostProtocol::drop($raw, $book->id()); }
        catch (InvalidArgumentException $e) { return $this->json(400, ['error' => $e->getMessage()]); }
        if ($drop['book'] !== $book->id()) return $this->json(409, ['error' => 'Drop Book mismatch']);

        $bus = new ChannelBus($book->channelsDir($this->dataRoot));
        $host = $bus->channel('host');
        $host->set('last', $drop);
        $host->set('sequence', $drop['sequence']);
        $dropBag = $bus->channel($book->dropChannel());
        $dropBag->set('last', $drop);
        $n = 0;
        foreach ($drop['payload'] as $key => $value) {
            if ($n++ >= 64) break;
            if (is_scalar($value) || is_array($value) || $value === null) $dropBag->set((string)$key, $value);
        }
        $bus->save();
        return $this->json(202, ['accepted' => true, 'sequence' => $drop['sequence']]);
    }

    private function browserProgram(Book $book, string $leaf): ?string {
        $file = $book->paslPath($leaf);
        if ($file === null) return null;
        require_once dirname(__DIR__, 2) . '/pasl-front.php';
        require_once dirname(__DIR__, 2) . '/pasl-back.php';
        require_once dirname(__DIR__, 2) . '/pasl-package.php';
        return \pasl\Package::toPasmAsm((string)file_get_contents($file));
    }
    private function buildPipe(Book $book, Binding $bind, ChannelBus $bus): SegmentPipe {
        $formSeg = function (Bag $b, array $r) use ($book): Bag {
            if (($r['method'] ?? '') !== 'POST') return $b;
            foreach (($r['fields'] ?? []) as $k => $v) {
                $k = (string)$k;
                if (in_array($k, ['protocol','book','dir','table','y'], true)) { $b->set($k, is_scalar($v)?(string)$v:''); continue; }
                if ($k === 'control' && is_array($v)) { $b->set('control', array_slice($v, 0, 64, true)); continue; }
                if (preg_match('/secret|password|token|xi_/i', $k)) continue;
                if (is_scalar($v) && strlen((string)$v) < 8192) $b->set($k, $v);
            }
            return $b;
        };
        $protoSeg = function (Bag $b, array $r) use ($book, $bind, $bus): Bag {
            $name = (string)$b->get('protocol', $r['protocol'] ?? '');
            if ($name === '' && ($r['method'] ?? '') === 'POST' && isset($r['fields']['dir'])) { $name = 'book.turn'; $b->set('protocol', $name); }
            if ($name === '') return $b;
            $path = $book->protocolPath($name);
            if ($path === null || !is_file($path)) return $b;
            $buffer = $b;
            include $path;
            return $buffer;
        };
        return new SegmentPipe(['http' => fn(Bag $b, array $r) => $b, 'form' => $formSeg, 'protocol' => $protoSeg, 'page' => fn(Bag $b, array $r) => $b, 'monitor' => fn(Bag $b, array $r) => $b]);
    }
    private function processDrops(Book $book, ChannelBus $bus): void {
        if (!$book->dropsEnabled()) return;
        $inbox = $book->inboxDir($this->dataRoot);
        if (!is_dir($inbox)) { mkdir($inbox, 0755, true); return; }
        $bag = $bus->channel($book->dropChannel());
        $processed = $inbox . '/processed';
        if (!is_dir($processed)) mkdir($processed, 0755, true);
        foreach (glob($inbox . '/*.json') ?: [] as $file) {
            if (!is_file($file)) continue;
            $data = json_decode((string)file_get_contents($file), true);
            if (!is_array($data)) { rename($file, $processed . '/' . basename($file) . '.bad'); continue; }
            $n = 0;
            foreach ($data as $k => $v) {
                if ($n++ > 64) break;
                if (is_scalar($v) || is_array($v)) $bag->set((string)$k, $v);
            }
            rename($file, $processed . '/' . basename($file));
        }
        $bus->save($book->dropChannel());
    }
    private function renderLeaf(string $path, Binding $bind, Bag $buffer, Book $book, ChannelBus $bus): string {
        ob_start();
