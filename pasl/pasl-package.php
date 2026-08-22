<?php declare(strict_types=1);
namespace pasl;

/**
 * Unified facade: one API over numeric core + strnet full surface.
 */
final class Package
{
    public static function needsFullSurface(string $source): bool
    {
        return (bool) preg_match(
            '/["\']|net_|strlen|substr|strcmp|count\s*\(|string\s+\$|object\s+\$|bag\s+\$|array\s+\$|\.[A-Za-z_]|\[/',
            $source
        );
    }

    public static function hasStrnet(): bool
    {
        return class_exists(\pasl\strnet\Compiler::class);
    }

    public static function toC(string $source): string
    {
        if (self::needsFullSurface($source) && self::hasStrnet()) {
            return (new \pasl\strnet\Compiler())->toC($source);
        }
        return (new Compiler(true))->toC($source);
    }

    public static function toX86(string $source): string
    {
        return (new Compiler(true))->toX86($source);
    }

    public static function toArm(string $source): string
    {
        return (new Compiler(true))->toArm($source);
    }

    public static function toPasmAsm(string $source): string
    {
        return (new Compiler(true))->toPasmAsm($source);
    }

    /** @return array{backend:string,code:string} */
    public static function compile(string $source, string $mode = 'c'): array
    {
        $mode = strtolower($mode);
        return match ($mode) {
            'x86' => ['backend' => 'x86', 'code' => self::toX86($source)],
            'arm' => ['backend' => 'arm', 'code' => self::toArm($source)],
            'pasm' => ['backend' => 'pasm', 'code' => self::toPasmAsm($source)],
            'strnet', 'full' => [
                'backend' => 'strnet',
                'code' => self::hasStrnet()
                    ? (new \pasl\strnet\Compiler())->toC($source)
                    : (new Compiler(true))->toC($source),
            ],
            default => [
                'backend' => (self::needsFullSurface($source) && self::hasStrnet()) ? 'strnet' : 'c',
                'code' => self::toC($source),
            ],
        };
    }
}
