<?php declare(strict_types=1);

namespace jx\semantic;

require_once __DIR__ . '/jx-jxb.php';

/** Conservative executable-schema gate for replacing a live JXB generation. */
final class JxbGeneration
{
    /**
     * Existing public executable signatures must survive a candidate generation.
     * Additive functions/classes/members are allowed; removals or representation
     * changes are refused. Durable Bag migration remains a separate state-layer law.
     *
     * @return array{compatible:bool,reasons:list<string>,from_sha256:string,to_sha256:string}
     */
    public static function compare(string $currentBytes, string $candidateBytes): array
    {
        $current = JxbBook::validate($currentBytes);
        $candidate = JxbBook::validate($candidateBytes);
        // Also force prepared metadata/type admission even though no code executes.
        JxbBook::admit($current);
        JxbBook::admit($candidate);

        $reasons = [];
        if (($current['manifest']['format'] ?? null) !== ($candidate['manifest']['format'] ?? null)) {
            $reasons[] = 'compiled Book format changed';
        }
        if (($current['manifest']['native_target'] ?? null) !== ($candidate['manifest']['native_target'] ?? null)) {
            $reasons[] = 'execution target changed';
        }

        $oldPrepared = self::prepared($current);
        $newPrepared = self::prepared($candidate);
        self::compareFunctions($oldPrepared['functions'], $newPrepared['functions'], $reasons);
        self::compareClasses($oldPrepared['classes'], $newPrepared['classes'], $reasons);

        $oldSemantic = self::semantic($current);
        $newSemantic = self::semantic($candidate);
        if (($oldSemantic['namespace'] ?? null) !== ($newSemantic['namespace'] ?? null)) {
            $reasons[] = 'canonical namespace changed';
        }

        return [
            'compatible'=>$reasons === [],
            'reasons'=>$reasons,
            'from_sha256'=>$current['content_sha256'],
            'to_sha256'=>$candidate['content_sha256'],
        ];
    }

    public static function assertCompatible(string $currentBytes, string $candidateBytes): void
    {
        $result = self::compare($currentBytes, $candidateBytes);
        if (!$result['compatible']) {
            throw new SemanticException('Incompatible JXB generation: ' . implode('; ', $result['reasons']), 'jxb-generation');
        }
    }

    /** @return array<string,mixed> */
    private static function prepared(array $book): array
    {
        $bytes = $book['entries'][JxbBook::PREPARED_PATH] ?? null;
        if (!is_string($bytes)) throw new SemanticException('JXB prepared metadata missing during generation check', 'jxb-generation');
        $v = json_decode($bytes, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($v)) throw new SemanticException('JXB prepared metadata malformed during generation check', 'jxb-generation');
        return $v;
    }

    /** @return array<string,mixed> */
    private static function semantic(array $book): array
    {
        $bytes = $book['entries']['META/semantic.json'] ?? null;
        if (!is_string($bytes)) return [];
        $v = json_decode($bytes, true, flags: JSON_THROW_ON_ERROR);
        return is_array($v) ? $v : [];
    }

    /** @param list<array<string,mixed>> $old @param list<array<string,mixed>> $new @param list<string> $reasons */
    private static function compareFunctions(array $old, array $new, array &$reasons): void
    {
        $newByName = self::indexByName($new);
        foreach ($old as $fn) {
            $name = strtolower((string)($fn['name'] ?? ''));
            if ($name === '' || !isset($newByName[$name])) {
                $reasons[] = "function removed: {$name}";
                continue;
            }
            $next = $newByName[$name];
            if (($fn['return_type_id'] ?? null) !== ($next['return_type_id'] ?? null)) {
                $reasons[] = "function return representation changed: {$name}";
            }
            $a = array_map(static fn(array $p): mixed => $p['type_id'] ?? null, is_array($fn['params'] ?? null) ? $fn['params'] : []);
            $b = array_map(static fn(array $p): mixed => $p['type_id'] ?? null, is_array($next['params'] ?? null) ? $next['params'] : []);
            if ($a !== $b) $reasons[] = "function parameter signature changed: {$name}";
        }
    }

    /** @param list<array<string,mixed>> $old @param list<array<string,mixed>> $new @param list<string> $reasons */
    private static function compareClasses(array $old, array $new, array &$reasons): void
    {
        $newByName = self::indexByName($new);
        foreach ($old as $class) {
            $name = strtolower((string)($class['name'] ?? ''));
            if ($name === '' || !isset($newByName[$name])) {
                $reasons[] = "class removed: {$name}";
                continue;
            }
            $next = $newByName[$name];
            if (($class['extends'] ?? null) !== ($next['extends'] ?? null) || ($class['implements'] ?? []) !== ($next['implements'] ?? [])) {
                $reasons[] = "class inheritance contract changed: {$name}";
            }
            self::compareMembers($name, 'property', $class['properties'] ?? [], $next['properties'] ?? [], $reasons);
            self::compareMembers($name, 'method', $class['methods'] ?? [], $next['methods'] ?? [], $reasons);
        }
    }

    /** @param list<array<string,mixed>> $old @param list<array<string,mixed>> $new @param list<string> $reasons */
    private static function compareMembers(string $class, string $kind, mixed $old, mixed $new, array &$reasons): void
    {
        $old = is_array($old) ? $old : [];
        $new = is_array($new) ? $new : [];
        $newByName = self::indexByName($new);
        foreach ($old as $member) {
            if (!is_array($member)) continue;
            $name = strtolower((string)($member['name'] ?? ''));
            if ($name === '' || !isset($newByName[$name])) {
                $reasons[] = "{$kind} removed: {$class}.{$name}";
                continue;
            }
            $next = $newByName[$name];
            $idKey = $kind === 'property' ? 'type_id' : 'return_type_id';
            if (($member[$idKey] ?? null) !== ($next[$idKey] ?? null)) {
                $reasons[] = "{$kind} representation changed: {$class}.{$name}";
            }
            if ($kind === 'property' && (($member['visibility'] ?? 'public') !== ($next['visibility'] ?? 'public') || ($member['static'] ?? false) !== ($next['static'] ?? false))) {
                $reasons[] = "property access contract changed: {$class}.{$name}";
            }
        }
    }

    /** @param list<array<string,mixed>> $rows @return array<string,array<string,mixed>> */
    private static function indexByName(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $name = strtolower((string)($row['name'] ?? ''));
            if ($name !== '') $out[$name] = $row;
        }
        return $out;
    }
}
