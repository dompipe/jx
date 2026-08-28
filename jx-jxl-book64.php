<?php declare(strict_types=1);

namespace jx\semantic;

require_once __DIR__ . '/jx-semantic.php';
require_once __DIR__ . '/jx-prepared-metadata.php';

/** Deterministic compiled Book carrying prepared JXL. */
final class JxlBook64
{
    public const MAGIC = 'JX64B001';
    public const FORMAT = 'jx.64B/1';
    public const CODE_PATH = 'CODE/program.jxl';
    public const PREPARED_PATH = 'META/prepared.json';

    /** @return array{bytes:string,manifest:array<string,mixed>,content_sha256:string,file_sha256:string} */
    public static function compile(string $source, string $name = 'program'): array
    {
        $compiler = new Compiler();
        $program = $compiler->parse($source);
        $jxl = (new JxlEmitter())->emit($program);

        $semantic = [
            'namespace' => $program->namespace,
            'imports' => $program->imports,
            'functions' => array_values(array_map(static fn(Node $n): array => [
                'name' => $n->data['name'],
                'return' => $n->data['return'],
                'params' => $n->data['params'],
            ], $program->functions)),
            'classes' => array_values(array_map(static fn(Node $n): array => [
                'name' => $n->data['name'],
                'extends' => $n->data['extends'],
                'implements' => $n->data['implements'],
                'methods' => array_keys($n->data['methods']),
                'properties' => array_keys($n->data['properties']),
            ], $program->classes)),
            'source_sha256' => hash('sha256', $source),
        ];

        $sections = [
            self::CODE_PATH => $jxl,
            'META/semantic.json' => self::json($semantic),
            self::PREPARED_PATH => PreparedMetadata::json($program),
        ];
        ksort($sections, SORT_STRING);

        $sectionTable = [];
        foreach ($sections as $path => $data) {
            $sectionTable[] = [
                'path' => $path,
                'bytes' => strlen($data),
                'sha256' => hash('sha256', $data),
            ];
        }
        $contentHashMaterial = '';
        foreach ($sectionTable as $s) {
            $contentHashMaterial .= $s['path'] . "\0" . $s['bytes'] . "\0" . $s['sha256'] . "\n";
        }
        $contentSha = hash('sha256', $contentHashMaterial);

        $manifest = [
            'format' => self::FORMAT,
            'kind' => 'compiled-book',
            'architecture' => '64-bit',
            'native_target' => 'jxl',
            'book' => $name,
            'compiler' => 'jx.semantic+jxl/1',
            'content_sha256' => $contentSha,
            'sections' => $sectionTable,
        ];
        $manifestBytes = self::json($manifest);
        $header = self::MAGIC
            . pack('v', 1)
            . pack('v', 0)
            . pack('V', count($sections))
            . hash('sha256', $manifestBytes, true);
        if (strlen($header) !== 48) throw new SemanticException('64B header length invariant failed', '64B');

        $entries = ['JX64/header.bin' => $header, 'JX64/manifest.json' => $manifestBytes] + $sections;
        $bytes = self::zipStore($entries);
        return [
            'bytes' => $bytes,
            'manifest' => $manifest,
            'content_sha256' => $contentSha,
            'file_sha256' => hash('sha256', $bytes),
        ];
    }

    /** @return array{manifest:array<string,mixed>,entries:array<string,string>,content_sha256:string,file_sha256:string} */
    public static function validate(string $bytes): array
    {
        $entries = self::readZipStore($bytes);
        $names = array_keys($entries);
        if (($names[0] ?? null) !== 'JX64/header.bin') throw new SemanticException('64B first entry must be JX64/header.bin', '64B');
        if (($names[1] ?? null) !== 'JX64/manifest.json') throw new SemanticException('64B second entry must be JX64/manifest.json', '64B');
        $header = $entries['JX64/header.bin'];
        if (strlen($header) !== 48 || substr($header, 0, 8) !== self::MAGIC) throw new SemanticException('Invalid JX64B001 header', '64B');
        $major = unpack('v', substr($header, 8, 2))[1];
        $minor = unpack('v', substr($header, 10, 2))[1];
        $count = unpack('V', substr($header, 12, 4))[1];
        if ($major !== 1 || $minor !== 0) throw new SemanticException("Unsupported 64B version {$major}.{$minor}", '64B');
        $manifestBytes = $entries['JX64/manifest.json'];
        if (!hash_equals(bin2hex(substr($header, 16, 32)), hash('sha256', $manifestBytes))) throw new SemanticException('64B manifest hash mismatch', '64B');
        $manifest = json_decode($manifestBytes, true, flags: JSON_THROW_ON_ERROR);
        if (($manifest['format'] ?? null) !== self::FORMAT) throw new SemanticException('64B manifest format mismatch', '64B');
        if (!is_array($manifest['sections'] ?? null) || count($manifest['sections']) !== $count) throw new SemanticException('64B section count mismatch', '64B');

        $material = '';
        foreach ($manifest['sections'] as $s) {
            $path = $s['path'] ?? '';
            if (!is_string($path) || !array_key_exists($path, $entries)) throw new SemanticException("64B missing section {$path}", '64B');
            $data = $entries[$path];
            if (($s['bytes'] ?? -1) !== strlen($data)) throw new SemanticException("64B size mismatch {$path}", '64B');
            $sha = hash('sha256', $data);
            if (!hash_equals((string)($s['sha256'] ?? ''), $sha)) throw new SemanticException("64B hash mismatch {$path}", '64B');
            $material .= $path . "\0" . strlen($data) . "\0" . $sha . "\n";
        }
        $contentSha = hash('sha256', $material);
        if (!hash_equals((string)($manifest['content_sha256'] ?? ''), $contentSha)) throw new SemanticException('64B canonical content hash mismatch', '64B');
        return ['manifest'=>$manifest,'entries'=>$entries,'content_sha256'=>$contentSha,'file_sha256'=>hash('sha256',$bytes)];
    }

    public static function compileFile(string $sourcePath, string $outPath, ?string $name = null): array
    {
        $source = file_get_contents($sourcePath);
        if ($source === false) throw new SemanticException("Cannot read {$sourcePath}", 'io');
        $r = self::compile($source, $name ?? pathinfo($sourcePath, PATHINFO_FILENAME));
        $dir = dirname($outPath);
        if ($dir !== '.' && !is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) throw new SemanticException("Cannot create {$dir}", 'io');
        if (file_put_contents($outPath, $r['bytes']) !== strlen($r['bytes'])) throw new SemanticException("Cannot write {$outPath}", 'io');
        return $r;
    }

    private static function json(array $v): string
    {
        return json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /** @param array<string,string> $entries */
    private static function zipStore(array $entries): string
    {
        $local = '';
        $central = '';
        $offset = 0;
        $count = 0;
        foreach ($entries as $name => $data) {
            $nameBytes = $name;
            $crc = (int)sprintf('%u', crc32($data));
            $size = strlen($data);
            $nameLen = strlen($nameBytes);
            $localHeader = pack('VvvvvvVVVvv',
                0x04034b50, 20, 0, 0, 0, 33, $crc, $size, $size, $nameLen, 0
            );
            $local .= $localHeader . $nameBytes . $data;
            $central .= pack('VvvvvvvVVVvvvvvVV',
                0x02014b50, 20, 20, 0, 0, 0, 33, $crc, $size, $size,
                $nameLen, 0, 0, 0, 0, 0, $offset
            ) . $nameBytes;
            $offset += strlen($localHeader) + $nameLen + $size;
            $count++;
        }
        $centralOffset = strlen($local);
        $end = pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen($central), $centralOffset, 0);
        return $local . $central . $end;
    }

    /** @return array<string,string> insertion order is archive order */
    private static function readZipStore(string $bytes): array
    {
        $entries = [];
        $p = 0;
        $n = strlen($bytes);
        while ($p + 4 <= $n) {
            $sig = unpack('V', substr($bytes, $p, 4))[1];
            if ($sig === 0x02014b50 || $sig === 0x06054b50) break;
            if ($sig !== 0x04034b50) throw new SemanticException('Invalid ZIP local header in 64B', '64B');
            if ($p + 30 > $n) throw new SemanticException('Truncated ZIP local header in 64B', '64B');
            $h = unpack('Vsig/vver/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vusize/vnlen/velen', substr($bytes, $p, 30));
            if ($h['flags'] !== 0 || $h['method'] !== 0) throw new SemanticException('64B entries must be unencrypted STORE entries', '64B');
            $nameStart = $p + 30;
            $dataStart = $nameStart + $h['nlen'] + $h['elen'];
            $dataEnd = $dataStart + $h['csize'];
            if ($dataEnd > $n) throw new SemanticException('Truncated 64B entry', '64B');
            $name = substr($bytes, $nameStart, $h['nlen']);
            $data = substr($bytes, $dataStart, $h['csize']);
            $crc = (int)sprintf('%u', crc32($data));
            if ($crc !== $h['crc'] || $h['csize'] !== $h['usize']) throw new SemanticException("64B CRC/size mismatch {$name}", '64B');
            if (array_key_exists($name, $entries)) throw new SemanticException("Duplicate 64B entry {$name}", '64B');
            $entries[$name] = $data;
            $p = $dataEnd;
        }
        if ($entries === []) throw new SemanticException('Empty 64B archive', '64B');
        return $entries;
    }
}
