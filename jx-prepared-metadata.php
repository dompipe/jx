<?php declare(strict_types=1);

namespace jx\semantic;

require_once __DIR__ . '/jx-semantic.php';

/**
 * Stable prepared metadata shared by JXL/native admission.
 *
 * The numeric IDs are ABI-facing metadata values. They deliberately do not
 * encode PHP zval/runtime implementation details.
 */
final class PreparedType
{
    public const VOID = 0;
    public const ANY = 1;
    public const BOOL = 2;
    public const INT = 3;
    public const FLOAT = 4;
    public const STRING = 5;
    public const NULL = 6;
    public const COMPLEX = 7;
    public const BAG = 8;
    public const LIST = 9;
    public const MAP = 10;
    public const OBJECT = 11;
    public const HANDLE = 12;
    public const USER = 63;

    /** @return array<string,int> */
    public static function table(): array
    {
        return [
            Type::VOID => self::VOID,
            Type::ANY => self::ANY,
            Type::BOOL => self::BOOL,
            Type::INT => self::INT,
            Type::FLOAT => self::FLOAT,
            Type::STRING => self::STRING,
            Type::NULL => self::NULL,
            Type::COMPLEX => self::COMPLEX,
            Type::BAG => self::BAG,
            Type::LIST => self::LIST,
            Type::MAP => self::MAP,
            Type::OBJECT => self::OBJECT,
            Type::HANDLE => self::HANDLE,
        ];
    }

    public static function id(string $type): int
    {
        $canonical = Type::canonical($type);
        return self::table()[$canonical] ?? self::USER;
    }
}

final class PreparedMetadata
{
    /**
     * @return array{
     *   format:string,
     *   type_ids:array<string,int>,
     *   functions:list<array<string,mixed>>,
     *   classes:list<array<string,mixed>>,
     *   source_map:list<array{index:int,path:string,op:string,type:string,type_id:int,line:int}>
     * }
     */
    public static function build(Program $program): array
    {
        $functions = [];
        foreach ($program->functions as $fn) {
            $params = [];
            foreach (($fn->data['params'] ?? []) as $param) {
                $type = Type::canonical((string)($param['type'] ?? Type::ANY));
                $params[] = [
                    'name' => (string)($param['name'] ?? ''),
                    'type' => $type,
                    'type_id' => PreparedType::id($type),
                ];
            }
            $ret = Type::canonical((string)($fn->data['return'] ?? $fn->type));
            $functions[] = [
                'name' => (string)($fn->data['name'] ?? ''),
                'return' => $ret,
                'return_type_id' => PreparedType::id($ret),
                'params' => $params,
                'line' => $fn->line,
            ];
        }
        usort($functions, static fn(array $a, array $b): int => strcmp(strtolower($a['name']), strtolower($b['name'])));

        $classes = [];
        foreach ($program->classes as $cl) {
            $properties = [];
            foreach (($cl->data['properties'] ?? []) as $name => $property) {
                $type = Type::canonical((string)($property['type'] ?? Type::ANY));
                $properties[] = [
                    'name' => (string)$name,
                    'type' => $type,
                    'type_id' => PreparedType::id($type),
                    'visibility' => (string)($property['visibility'] ?? 'public'),
                    'static' => (bool)($property['static'] ?? false),
                ];
            }
            usort($properties, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));

            $methods = [];
            foreach (($cl->data['methods'] ?? []) as $method) {
                $ret = Type::canonical((string)($method->data['return'] ?? $method->type));
                $methods[] = [
                    'name' => (string)($method->data['name'] ?? ''),
                    'return' => $ret,
                    'return_type_id' => PreparedType::id($ret),
                    'line' => $method->line,
                ];
            }
            usort($methods, static fn(array $a, array $b): int => strcmp(strtolower($a['name']), strtolower($b['name'])));

            $classes[] = [
                'name' => (string)($cl->data['name'] ?? ''),
                'type_id' => PreparedType::USER,
                'extends' => $cl->data['extends'] ?? null,
                'implements' => array_values($cl->data['implements'] ?? []),
                'properties' => $properties,
                'methods' => $methods,
                'line' => $cl->line,
            ];
        }
        usort($classes, static fn(array $a, array $b): int => strcmp(strtolower($a['name']), strtolower($b['name'])));

        $sourceMap = [];
        $index = 0;
        foreach ($program->statements as $i => $node) {
            self::walkNode($node, 'program.' . $i, $sourceMap, $index);
        }
        foreach ($program->functions as $name => $node) {
            self::walkNode($node, 'function.' . strtolower((string)$name), $sourceMap, $index);
        }
        foreach ($program->classes as $name => $node) {
            self::walkNode($node, 'class.' . strtolower((string)$name), $sourceMap, $index);
        }

        return [
            'format' => 'jx.prepared-metadata/1',
            'type_ids' => PreparedType::table() + ['<user-type>' => PreparedType::USER],
            'functions' => $functions,
            'classes' => $classes,
            'source_map' => $sourceMap,
        ];
    }

    /** @param list<array{index:int,path:string,op:string,type:string,type_id:int,line:int}> $out */
    private static function walkNode(Node $node, string $path, array &$out, int &$index): void
    {
        $type = Type::canonical($node->type);
        $out[] = [
            'index' => $index++,
            'path' => $path,
            'op' => $node->op,
            'type' => $type,
            'type_id' => PreparedType::id($type),
            'line' => $node->line,
        ];

        foreach ($node->data as $key => $value) {
            self::walkValue($value, $path . '.' . (string)$key, $out, $index);
        }
    }

    /** @param list<array{index:int,path:string,op:string,type:string,type_id:int,line:int}> $out */
    private static function walkValue(mixed $value, string $path, array &$out, int &$index): void
    {
        if ($value instanceof Node) {
            self::walkNode($value, $path, $out, $index);
            return;
        }
        if (!is_array($value)) return;
        foreach ($value as $key => $item) {
            self::walkValue($item, $path . '.' . (string)$key, $out, $index);
        }
    }

    public static function json(Program $program): string
    {
        return json_encode(self::build($program), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
