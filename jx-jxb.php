<?php declare(strict_types=1);

namespace jx\semantic;

require_once __DIR__ . '/jx-jxl-book64.php';
require_once __DIR__ . '/jx-book-trust.php';

/**
 * Public compiled-Book contract.
 *
 * .jxb is the canonical user/tooling extension. The current v1 package keeps
 * the proven JX64B001 / jx.64B/1 internal ABI so renaming the public artifact
 * does not invalidate existing compiled bytes or host probes.
 */
final class JxbBook
{
    public const EXTENSION = '.jxb';
    public const INTERNAL_FORMAT = JxlBook64::FORMAT;
    public const INTERNAL_MAGIC = JxlBook64::MAGIC;
    public const CODE_PATH = JxlBook64::CODE_PATH;
    public const PREPARED_PATH = JxlBook64::PREPARED_PATH;
    public const PREPARED_FORMAT = 'jx.prepared-metadata/1';

    /** @return array{bytes:string,manifest:array<string,mixed>,content_sha256:string,file_sha256:string} */
    public static function compile(string $source, string $name = 'program'): array
    {
        return JxlBook64::compile($source, $name);
    }

    /**
     * Compile canonical .jx source to a .jxb file.
     * When $outPath is omitted the source basename is reused with .jxb.
     *
     * @return array{path:string,bytes:string,manifest:array<string,mixed>,content_sha256:string,file_sha256:string}
     */
    public static function compileFile(string $sourcePath, ?string $outPath = null, ?string $name = null): array
    {
        $source = file_get_contents($sourcePath);
        if ($source === false) throw new SemanticException("Cannot read {$sourcePath}", 'io');

        $outPath ??= self::defaultOutputPath($sourcePath);
        $compiled = self::compile($source, $name ?? pathinfo($sourcePath, PATHINFO_FILENAME));
        $dir = dirname($outPath);
        if ($dir !== '.' && !is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new SemanticException("Cannot create {$dir}", 'io');
        }
        if (file_put_contents($outPath, $compiled['bytes']) !== strlen($compiled['bytes'])) {
            throw new SemanticException("Cannot write {$outPath}", 'io');
        }
        return ['path'=>$outPath] + $compiled;
    }

    /** @return array{manifest:array<string,mixed>,entries:array<string,string>,content_sha256:string,file_sha256:string} */
    public static function validate(string $bytes): array
    {
        return JxlBook64::validate($bytes);
    }

    /** @return array{manifest:array<string,mixed>,entries:array<string,string>,content_sha256:string,file_sha256:string} */
    public static function loadFile(string $path): array
    {
        $bytes = file_get_contents($path);
        if ($bytes === false) throw new SemanticException("Cannot read {$path}", 'io');
        return self::validate($bytes);
    }

    /**
     * Admission binds the target and prepared type table once before execution.
     * Repeated execution therefore does not rediscover representation meaning.
     *
     * @param array{manifest:array<string,mixed>,entries:array<string,string>} $book
     */
    public static function admit(array $book): string
    {
        $target = (string)($book['manifest']['native_target'] ?? '');
        if ($target !== 'jxl') {
            throw new SemanticException("JXB target {$target} is not executable by the JXL host", 'jxb-admission');
        }

        $preparedBytes = $book['entries'][self::PREPARED_PATH] ?? null;
        if (!is_string($preparedBytes)) {
            throw new SemanticException('JXB is missing prepared metadata', 'jxb-admission');
        }
        try {
            $prepared = json_decode($preparedBytes, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new SemanticException('JXB prepared metadata is not valid JSON', 'jxb-admission');
        }
        if (!is_array($prepared) || ($prepared['format'] ?? null) !== self::PREPARED_FORMAT) {
            throw new SemanticException('JXB prepared metadata format mismatch', 'jxb-admission');
        }
        self::verifyPreparedTypes($prepared);

        $code = $book['entries'][self::CODE_PATH] ?? null;
        if (!is_string($code)) throw new SemanticException('JXB is missing prepared JXL code', 'jxb-admission');
        return $code;
    }

    /** Execute the admitted prepared JXL carried by a compiled Book. */
    public static function run(string $bytes): int
    {
        $book = self::validate($bytes);
        return (new JxlVm())->run(self::admit($book));
    }

    public static function runFile(string $path): int
    {
        $bytes = file_get_contents($path);
        if ($bytes === false) throw new SemanticException("Cannot read {$path}", 'io');
        return self::run($bytes);
    }

    /**
     * Verify detached Book identity/capabilities before prepared admission.
     * This is the production security path for hosts that require signed Books.
     *
     * @param array<string,mixed> $envelope
     * @param list<string> $requiredCapabilities
     */
    public static function runTrusted(string $bytes, array $envelope, string $publicKey, array $requiredCapabilities = []): int
    {
        if (!BookTrust::verify($bytes, $envelope, $publicKey, $requiredCapabilities)) {
            throw new SemanticException('JXB trust signature verification failed', 'jxb-admission');
        }
        return self::run($bytes);
    }

    /** @param list<string> $requiredCapabilities */
    public static function runTrustedFile(string $path, string $trustPath, string $publicKey, array $requiredCapabilities = []): int
    {
        $bytes = file_get_contents($path);
        if ($bytes === false) throw new SemanticException("Cannot read {$path}", 'io');
        $json = file_get_contents($trustPath);
        if ($json === false) throw new SemanticException("Cannot read {$trustPath}", 'io');
        try {
            $envelope = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new SemanticException('JXB trust envelope is not valid JSON', 'jxb-admission');
        }
        if (!is_array($envelope)) throw new SemanticException('JXB trust envelope is malformed', 'jxb-admission');
        return self::runTrusted($bytes, $envelope, $publicKey, $requiredCapabilities);
    }

    public static function defaultOutputPath(string $sourcePath): string
    {
        $dir = dirname($sourcePath);
        $base = pathinfo($sourcePath, PATHINFO_FILENAME) . self::EXTENSION;
        return $dir === '.' ? $base : $dir . DIRECTORY_SEPARATOR . $base;
    }

    /** @param array<string,mixed> $prepared */
    private static function verifyPreparedTypes(array $prepared): void
    {
        $typeIds = $prepared['type_ids'] ?? null;
        if (!is_array($typeIds)) throw new SemanticException('JXB prepared type table is missing', 'jxb-admission');
        foreach (PreparedType::table() as $name => $id) {
            if (($typeIds[$name] ?? null) !== $id) {
                throw new SemanticException("JXB prepared type mismatch for {$name}", 'jxb-admission');
            }
        }
        if (($typeIds['<user-type>'] ?? null) !== PreparedType::USER) {
            throw new SemanticException('JXB prepared user-type ID mismatch', 'jxb-admission');
        }

        $functions = $prepared['functions'] ?? null;
        $classes = $prepared['classes'] ?? null;
        $sourceMap = $prepared['source_map'] ?? null;
        if (!is_array($functions) || !is_array($classes) || !is_array($sourceMap)) {
            throw new SemanticException('JXB prepared metadata tables are malformed', 'jxb-admission');
        }

        foreach ($functions as $fn) {
            if (!is_array($fn)) throw new SemanticException('JXB prepared function row is malformed', 'jxb-admission');
            $return = Type::canonical((string)($fn['return'] ?? Type::ANY));
            if (($fn['return_type_id'] ?? null) !== PreparedType::id($return)) {
                throw new SemanticException('JXB prepared function return type mismatch', 'jxb-admission');
            }
            foreach (($fn['params'] ?? []) as $param) {
                if (!is_array($param)) throw new SemanticException('JXB prepared parameter row is malformed', 'jxb-admission');
                $type = Type::canonical((string)($param['type'] ?? Type::ANY));
                if (($param['type_id'] ?? null) !== PreparedType::id($type)) {
                    throw new SemanticException('JXB prepared parameter type mismatch', 'jxb-admission');
                }
            }
        }

        foreach ($sourceMap as $row) {
            if (!is_array($row)) throw new SemanticException('JXB prepared source-map row is malformed', 'jxb-admission');
            $type = Type::canonical((string)($row['type'] ?? Type::ANY));
            if (($row['type_id'] ?? null) !== PreparedType::id($type)) {
                throw new SemanticException('JXB prepared source-map type mismatch', 'jxb-admission');
            }
        }
    }
}
