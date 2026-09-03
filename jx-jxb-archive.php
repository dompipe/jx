<?php declare(strict_types=1);

namespace jx;

use RuntimeException;
use ZipArchive;

/**
 * Canonical .jxb resource package.
 *
 * JXB is ZIP-compatible so resources remain individually indexed and deflated.
 * The runtime opens the directory once and extracts only the member requested;
 * it does not expand the whole package into memory.
 */
final class JxbArchive
{
    private ZipArchive $zip;

    private function __construct(ZipArchive $zip) { $this->zip = $zip; }

    public static function open(string $path): self
    {
        if (!class_exists(ZipArchive::class)) throw new RuntimeException('JXB requires PHP ZipArchive support');
        $zip = new ZipArchive();
        $rc = $zip->open($path);
        if ($rc !== true) throw new RuntimeException("Cannot open JXB {$path}; ZipArchive code {$rc}");
        return new self($zip);
    }

    /** @param array<string,string> $members archive-name => source-file */
    public static function create(string $path, array $members): void
    {
        if (!class_exists(ZipArchive::class)) throw new RuntimeException('JXB requires PHP ZipArchive support');
        $zip = new ZipArchive();
        $rc = $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($rc !== true) throw new RuntimeException("Cannot create JXB {$path}; ZipArchive code {$rc}");
        try {
            foreach ($members as $name=>$source) {
                $name = self::memberName((string)$name);
                if (!is_file($source)) throw new RuntimeException("JXB source member does not exist: {$source}");
                if (!$zip->addFile($source,$name)) throw new RuntimeException("Cannot add {$name} to JXB");
                if (method_exists($zip,'setCompressionName')) $zip->setCompressionName($name, ZipArchive::CM_DEFLATE);
            }
            $manifest = [
                'format'=>'jx.jxb/1',
                'compression'=>'zip-deflate',
                'members'=>array_keys($members),
            ];
            $zip->addFromString('jx-manifest.json', json_encode($manifest,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
        } finally {
            $zip->close();
        }
    }

    /** Return exactly one resource member, decompressing only that member. */
    public function get(string $name): string
    {
        $name = self::memberName($name);
        $bytes = $this->zip->getFromName($name);
        if ($bytes === false) throw new RuntimeException("JXB member not found: {$name}");
        return $bytes;
    }

    /** Stream exactly one resource member without expanding the package. */
    public function stream(string $name)
    {
        $name = self::memberName($name);
        $stream = $this->zip->getStream($name);
        if ($stream === false) throw new RuntimeException("JXB member not found: {$name}");
        return $stream;
    }

    /** @return list<string> */
    public function names(): array
    {
        $names=[];
        for($i=0;$i<$this->zip->numFiles;$i++){
            $stat=$this->zip->statIndex($i);
            if(is_array($stat)&&isset($stat['name']))$names[]=(string)$stat['name'];
        }
        return $names;
    }

    public function close(): void { $this->zip->close(); }

    private static function memberName(string $name): string
    {
        $name = str_replace('\\','/',trim($name));
        $name = ltrim($name,'/');
        if ($name==='' || str_contains($name,'../') || str_starts_with($name,'..')) throw new RuntimeException('Unsafe JXB member name');
        return $name;
    }
}
