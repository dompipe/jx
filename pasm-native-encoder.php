<?php declare(strict_types=1);

namespace pasm;

require_once __DIR__ . '/pasm-native-jxl.php';

/**
 * Canonical direct PASM -> native CODE encoder facade.
 *
 * The historical PASMNativeJxlEncoder class predates the current file-format
 * contract and returns raw machine-code bytes. Keep it intact for compatibility;
 * new compiler code should use this facade and then wrap CODE in JxNativeImage.
 */
final class PASMNativeEncoder
{
    public const ARCH = PASMNativeJxlEncoder::ARCH;

    private PASMNativeJxlEncoder $legacy;

    public function __construct()
    {
        $this->legacy = new PASMNativeJxlEncoder();
    }

    /** @param string|array<int,string> $pasm */
    public function compileCode(string|array $pasm): string
    {
        return $this->legacy->compile($pasm);
    }

    /** @return array<string,int> */
    public static function registerMap(): array
    {
        return PASMNativeJxlEncoder::registerMap();
    }
}
