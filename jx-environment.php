<?php declare(strict_types=1);

namespace jx;

use InvalidArgumentException;
use RuntimeException;

/** Execution environment identities. They classify hosts; they do not fork the language. */
final class EnvironmentClass
{
    public const BROWSER = 'browser';
    public const SERVER = 'server';
    public const NATIVE = 'native';
    public const CLI = 'cli';
    public const TEST = 'test';

    public static function all(): array { return [self::BROWSER,self::SERVER,self::NATIVE,self::CLI,self::TEST]; }
}

/** Capabilities are what matters when deciding whether a staged operation is legal. */
final class Capability
{
    public const DOM = 'dom';
    public const WINDOW = 'window';
    public const SQL = 'sql';
    public const SECRETS = 'secrets';
    public const FILESYSTEM = 'filesystem';
    public const NETWORK = 'network';
    public const PROCESS = 'process';
    public const HOST_EVENTS = 'host.events';
    public const STORAGE = 'storage';
    public const NATIVE_ABI = 'native.abi';
}

final class EnvironmentProfile
{
    /** @var array<string,true> */
    private array $capabilities=[];

    public function __construct(
        public readonly string $class,
        array $capabilities,
        public readonly string $name='default',
    ) {
        if(!in_array($class,EnvironmentClass::all(),true)) throw new InvalidArgumentException("Unknown environment class {$class}");
        foreach($capabilities as $cap){$cap=trim((string)$cap);if($cap!=='')$this->capabilities[$cap]=true;}
    }

    public function has(string $capability): bool { return isset($this->capabilities[$capability]); }
    public function require(string $capability,string $operation='operation'): void
    {
        if(!$this->has($capability)) throw new EnvironmentViolation($operation,$this,$capability);
    }
    public function capabilities(): array { return array_keys($this->capabilities); }

    public static function browser(string $name='browser'): self
    {
        return new self(EnvironmentClass::BROWSER,[Capability::DOM,Capability::NETWORK,Capability::HOST_EVENTS,Capability::STORAGE],$name);
    }
    public static function server(string $name='server'): self
    {
        return new self(EnvironmentClass::SERVER,[Capability::SQL,Capability::SECRETS,Capability::FILESYSTEM,Capability::NETWORK,Capability::PROCESS,Capability::HOST_EVENTS,Capability::STORAGE],$name);
    }
    public static function native(string $name='native'): self
    {
        return new self(EnvironmentClass::NATIVE,[Capability::WINDOW,Capability::FILESYSTEM,Capability::NETWORK,Capability::PROCESS,Capability::HOST_EVENTS,Capability::STORAGE,Capability::NATIVE_ABI],$name);
    }
    public static function cli(string $name='cli'): self
    {
        return new self(EnvironmentClass::CLI,[Capability::FILESYSTEM,Capability::NETWORK,Capability::PROCESS,Capability::HOST_EVENTS,Capability::STORAGE],$name);
    }
    public static function test(string $name='test'): self
    {
        return new self(EnvironmentClass::TEST,[Capability::DOM,Capability::WINDOW,Capability::SQL,Capability::SECRETS,Capability::FILESYSTEM,Capability::NETWORK,Capability::PROCESS,Capability::HOST_EVENTS,Capability::STORAGE,Capability::NATIVE_ABI],$name);
    }
}

final class EnvironmentViolation extends RuntimeException
{
    public function __construct(
        public readonly string $operation,
        public readonly EnvironmentProfile $environment,
        public readonly string $missingCapability,
    ) {
        parent::__construct("{$operation} requires {$missingCapability}; environment {$environment->class}:{$environment->name} does not provide it");
    }
}

/** Canonical description of an operation's environmental requirements. */
final class StageRequirement
{
    public function __construct(
        public readonly string $operation,
        public readonly array $requires=[],
        public readonly array $prefers=[],
    ) {}
}

final class StageDecision
{
    public function __construct(
        public readonly StageRequirement $requirement,
        public readonly EnvironmentProfile $environment,
        public readonly bool $allowed,
        public readonly array $missing,
        public readonly array $preferredMissing,
    ) {}

    public function assertAllowed(): self
    {
        if(!$this->allowed) throw new EnvironmentViolation($this->requirement->operation,$this->environment,$this->missing[0]??'unknown');
        return $this;
    }
}

/**
 * Prescient staging: decide legality while environment and canonical operation
 * are known, instead of waiting for a host-specific runtime failure.
 */
final class JxStage
{
    /** @var array<string,StageRequirement> */
    private array $requirements=[];

    public function register(string $operation,array $requires=[],array $prefers=[]): self
    {
        $key=$this->key($operation);
        $this->requirements[$key]=new StageRequirement($key,array_values(array_unique($requires)),array_values(array_unique($prefers)));
        return $this;
    }

    public function requirement(string $operation): StageRequirement
    {
        $key=$this->key($operation);
        return $this->requirements[$key]??new StageRequirement($key);
    }

    public function decide(string $operation,EnvironmentProfile $environment): StageDecision
    {
        $req=$this->requirement($operation);$missing=[];$preferredMissing=[];
        foreach($req->requires as $cap)if(!$environment->has((string)$cap))$missing[]=(string)$cap;
        foreach($req->prefers as $cap)if(!$environment->has((string)$cap))$preferredMissing[]=(string)$cap;
        return new StageDecision($req,$environment,$missing===[],$missing,$preferredMissing);
    }

    public function assert(string $operation,EnvironmentProfile $environment): StageDecision
    {
        return $this->decide($operation,$environment)->assertAllowed();
    }

    public static function standard(): self
    {
        return (new self())
            ->register('SQL.QUERY',[Capability::SQL,Capability::SECRETS])
            ->register('SQL.EXECUTE',[Capability::SQL,Capability::SECRETS])
            ->register('HOST.DOM.RENDER',[Capability::DOM])
            ->register('HOST.WINDOW.SHOW',[Capability::WINDOW])
            ->register('HOST.FILE.READ',[Capability::FILESYSTEM])
            ->register('HOST.PROCESS.START',[Capability::PROCESS])
            ->register('CHANNEL.SEND',[Capability::NETWORK])
            ->register('BOOK.CHECKPOINT',[Capability::STORAGE]);
    }

    private function key(string $operation): string
    {
        $key=strtoupper(trim($operation));if($key==='')throw new InvalidArgumentException('Operation cannot be empty');return $key;
    }
}
