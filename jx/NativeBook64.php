<?php declare(strict_types=1);

namespace jx;

use ZipArchive;

/**
 * Deterministic native JX compiled-Book package.
 *
 * Native source may be PHP-backed during authoring/compilation, but installed
 * native programs consume this package rather than reparsing PHP source.
 *
 * The filename extension is descriptive only. A loader recognizes the package
 * from the mandatory JX64/header.bin magic entry and validates the canonical
 * content digest before using any compiled section.
 */
final class NativeBook64
{
    public const VERSION = 'jx.64B/1';
    public const DEFAULT_EXTENSION = '.64B';
    public const HEADER_ENTRY = 'JX64/header.bin';
    public const MANIFEST_ENTRY = 'JX64/manifest.json';
    public const MAGIC = "JX64B001"; // exactly 8 bytes
    public const HEADER_BYTES = 48;
    public const FIXED_MTIME = 946684800; // 2000-01-01T00:00:00Z
    public const MAX_SECTIONS = 65535;
    public const MAX_SECTION_BYTES = 268435456; // 256 MiB per section

    /**
     * Build a deterministic ZIP-compatible .64B package.
     *
     * $sections maps stable package paths (for example CODE/native.bin or
     * HOT/registers.bin) to compiled binary strings. Source-language files do
     * not belong in a native package unless intentionally included as assets.
     *
     * @param array<string,string> $sections
     * @param array<string,mixed> $metadata
     * @return array{path:string,content_sha256:string,file_sha256:string,sections:int,bytes:int}
     */
    public static function build(string $path, array $sections, array $metadata = []): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new JxException('Native .64B packaging requires the PHP zip extension', '64b', true);
        }
        if ($sections === [] || count($sections) > self::MAX_SECTIONS) {
            throw new JxException('Native .64B package requires 1..65535 sections', '64b', true,
                ['sections'=>count($sections)]);
        }

        $clean = [];
        foreach ($sections as $name => $bytes) {
            $name = self::sectionName((string)$name);
            if (isset($clean[$name])) {
                throw new JxException('Duplicate native .64B section', '64b', true, ['section'=>$name]);
            }
            if (strlen($bytes) > self::MAX_SECTION_BYTES) {
                throw new JxException('Native .64B section exceeds bounded size', '64b', true,
                    ['section'=>$name, 'bytes'=>strlen($bytes)]);
            }
            $clean[$name] = $bytes;
        }
        ksort($clean, SORT_STRING);

        $sectionRows = [];
        $canonicalDigestInput = '';
        foreach ($clean as $name => $bytes) {
            $digest = hash('sha256', $bytes);
            $sectionRows[] = ['name'=>$name, 'bytes'=>strlen($bytes), 'sha256'=>$digest];
            // Length-prefix names and content hashes so concatenation is unambiguous.
            $canonicalDigestInput .= pack('V', strlen($name)).$name.pack('V', strlen($bytes)).hex2bin($digest);
        }
        $contentSha = hash('sha256', $canonicalDigestInput);

        $manifest = [
            'format'=>self::VERSION,
            'kind'=>'compiled-book',
            'arch'=>(string)($metadata['arch'] ?? 'x86_64'),
            'target'=>(string)($metadata['target'] ?? 'native'),
            'book'=>(string)($metadata['book'] ?? 'main'),
            'compiler'=>(string)($metadata['compiler'] ?? 'jx'),
            'content_sha256'=>$contentSha,
            'sections'=>$sectionRows,
        ];
        foreach ($metadata as $key => $value) {
            if (!array_key_exists((string)$key, $manifest) && self::metadataKey((string)$key)) {
                $manifest[(string)$key] = $value;
            }
        }
        $manifestJson = self::stableJson($manifest);
        $manifestShaRaw = hash('sha256', $manifestJson, true);
        $header = self::header(count($clean), $manifestShaRaw);

        $entries = [self::HEADER_ENTRY=>$header, self::MANIFEST_ENTRY=>$manifestJson] + $clean;

        $dir = dirname($path);
        if ($dir !== '.' && !is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new JxException('Cannot create native .64B output directory', '64b', true, ['directory'=>$dir]);
        }
        @unlink($path);
        $zip = new ZipArchive();
        $opened = $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($opened !== true) {
            throw new JxException('Cannot create native .64B package', '64b', true, ['path'=>$path, 'zip'=>$opened]);
        }
        try {
            foreach ($entries as $name => $bytes) {
                if (!$zip->addFromString($name, $bytes)) {
                    throw new JxException('Cannot add native .64B entry', '64b', true, ['entry'=>$name]);
                }
                // STORE removes zlib-version variability; fixed timestamps and
                // sorted entries make identical compiled content byte-stable.
                if (method_exists($zip, 'setCompressionName')) {
                    $zip->setCompressionName($name, ZipArchive::CM_STORE);
                }
                if (method_exists($zip, 'setMtimeName')) {
                    $zip->setMtimeName($name, self::FIXED_MTIME);
                }
                if (method_exists($zip, 'setExternalAttributesName')) {
                    $zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, 0100644 << 16);
                }
            }
        } finally {
            $zip->close();
        }

        if (!is_file($path)) {
            throw new JxException('Native .64B package was not written', '64b', true, ['path'=>$path]);
        }
        return [
            'path'=>$path,
            'content_sha256'=>$contentSha,
            'file_sha256'=>hash_file('sha256', $path),
            'sections'=>count($clean),
            'bytes'=>(int)filesize($path),
        ];
    }

    /**
     * Recognize and validate a compiled Book regardless of filename extension.
     *
     * @return array{manifest:array<string,mixed>,content_sha256:string,file_sha256:string,sections:array<string,string>}
     */
    public static function load(string $path, bool $loadSections = true): array
    {
        if (!class_exists(ZipArchive::class) || !is_file($path)) {
            throw new JxException('Native .64B package is unavailable', '64b', true, ['path'=>$path]);
        }
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new JxException('File is not a readable JX native package', '64b', true, ['path'=>$path]);
        }
        try {
            $header = $zip->getFromName(self::HEADER_ENTRY);
            $manifestJson = $zip->getFromName(self::MANIFEST_ENTRY);
            if (!is_string($header) || !is_string($manifestJson)) {
                throw new JxException('Missing JX64 package identity', '64b', true, ['path'=>$path]);
            }
            $head = self::parseHeader($header);
            if (!hash_equals(bin2hex($head['manifest_sha256']), hash('sha256', $manifestJson))) {
                throw new JxException('JX64 manifest checksum mismatch', '64b', true);
            }
            $manifest = json_decode($manifestJson, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($manifest) || ($manifest['format'] ?? null) !== self::VERSION || ($manifest['kind'] ?? null) !== 'compiled-book') {
                throw new JxException('Unsupported JX64 compiled Book manifest', '64b', true);
            }
            $rows = $manifest['sections'] ?? null;
            if (!is_array($rows) || count($rows) !== $head['sections']) {
                throw new JxException('JX64 section table mismatch', '64b', true);
            }

            $sections = [];
            $canonicalDigestInput = '';
            foreach ($rows as $row) {
                if (!is_array($row)) throw new JxException('Malformed JX64 section row', '64b', true);
                $name = self::sectionName((string)($row['name'] ?? ''));
                $bytes = $zip->getFromName($name);
                if (!is_string($bytes)) throw new JxException('Missing JX64 compiled section', '64b', true, ['section'=>$name]);
                $expectedBytes = (int)($row['bytes'] ?? -1);
                $expectedSha = strtolower((string)($row['sha256'] ?? ''));
                $actualSha = hash('sha256', $bytes);
                if ($expectedBytes !== strlen($bytes) || !preg_match('/^[0-9a-f]{64}$/', $expectedSha) || !hash_equals($expectedSha, $actualSha)) {
                    throw new JxException('JX64 section checksum mismatch', '64b', true, ['section'=>$name]);
                }
                $canonicalDigestInput .= pack('V', strlen($name)).$name.pack('V', strlen($bytes)).hex2bin($actualSha);
                if ($loadSections) $sections[$name] = $bytes;
            }
            $contentSha = hash('sha256', $canonicalDigestInput);
            if (!hash_equals(strtolower((string)($manifest['content_sha256'] ?? '')), $contentSha)) {
                throw new JxException('JX64 canonical content checksum mismatch', '64b', true);
            }
            return [
                'manifest'=>$manifest,
                'content_sha256'=>$contentSha,
                'file_sha256'=>hash_file('sha256', $path),
                'sections'=>$sections,
            ];
        } finally {
            $zip->close();
        }
    }

    public static function recognizes(string $path): bool
    {
        try { self::load($path, false); return true; }
        catch (\Throwable) { return false; }
    }

    private static function header(int $sections, string $manifestShaRaw): string
    {
        if (strlen($manifestShaRaw) !== 32) throw new \LogicException('sha256 must be 32 bytes');
        // 8 magic + 2 major + 2 minor + 4 section count + 32 manifest digest = 48 bytes.
        return self::MAGIC.pack('vvV', 1, 0, $sections).$manifestShaRaw;
    }

    /** @return array{major:int,minor:int,sections:int,manifest_sha256:string} */
    private static function parseHeader(string $header): array
    {
        if (strlen($header) !== self::HEADER_BYTES || substr($header, 0, 8) !== self::MAGIC) {
            throw new JxException('File is not a JX64 compiled Book', '64b', true);
        }
        $v = unpack('vmajor/vminor/Vsections', substr($header, 8, 8));
        if (!is_array($v) || ($v['major'] ?? 0) !== 1 || ($v['minor'] ?? -1) !== 0) {
            throw new JxException('Unsupported JX64 header version', '64b', true);
        }
        return [
            'major'=>(int)$v['major'], 'minor'=>(int)$v['minor'], 'sections'=>(int)$v['sections'],
            'manifest_sha256'=>substr($header, 16, 32),
        ];
    }

    private static function sectionName(string $name): string
    {
        $name = str_replace('\\', '/', trim($name));
        if ($name === '' || strlen($name) > 1024 || str_starts_with($name, '/') || str_contains($name, "\0") ||
            preg_match('#(^|/)\.\.(/|$)#', $name) || preg_match('/[\r\n]/', $name) ||
            $name === self::HEADER_ENTRY || $name === self::MANIFEST_ENTRY) {
            throw new JxException('Invalid native .64B section name', '64b', true, ['section'=>$name]);
        }
        return $name;
    }

    private static function metadataKey(string $key): bool
    {
        return $key !== '' && strlen($key) <= 128 && preg_match('/^[a-z0-9._-]+$/i', $key) === 1;
    }

    /** @param array<string,mixed> $value */
    private static function stableJson(array $value): string
    {
        self::sortRecursive($value);
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }

    /** @param array<mixed> $value */
    private static function sortRecursive(array &$value): void
    {
        if (!array_is_list($value)) ksort($value, SORT_STRING);
        foreach ($value as &$item) if (is_array($item)) self::sortRecursive($item);
        unset($item);
    }
}
