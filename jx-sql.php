<?php declare(strict_types=1);

namespace jx;

require_once __DIR__ . '/jx-environment.php';

use InvalidArgumentException;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use Throwable;

final class SqlDriver
{
    public const SQLITE='sqlite';
    public const MYSQL='mysql';
    public const POSTGRES='pgsql';
    public static function all(): array{return [self::SQLITE,self::MYSQL,self::POSTGRES];}
}

final class SqlConfig
{
    public function __construct(
        public readonly string $driver,
        public readonly string $dsn,
        public readonly ?string $username=null,
        public readonly ?string $password=null,
        public readonly array $options=[],
        public readonly bool $readOnly=false,
        public readonly ?EnvironmentProfile $environment=null,
    ) {
        if(!in_array($driver,SqlDriver::all(),true))throw new InvalidArgumentException("Unsupported SQL driver {$driver}");
        if(!str_starts_with(strtolower($dsn),$driver.':'))throw new InvalidArgumentException("DSN does not match SQL driver {$driver}");
    }

    public static function sqliteMemory(?EnvironmentProfile $environment=null,bool $readOnly=false): self
    {
        return new self(SqlDriver::SQLITE,'sqlite::memory:',null,null,[], $readOnly,$environment);
    }
}

final class SqlResult
{
    public function __construct(
        public readonly array $rows,
        public readonly int $rowCount,
        public readonly array $columns,
    ) {}

    public function first(): ?array{return $this->rows[0]??null;}
}

final class JxSqlStatement
{
    public function __construct(private PDOStatement $statement,private JxSql $owner){}

    public function query(array $params=[]): SqlResult
    {
        $this->owner->assertSqlAllowed($this->statement->queryString,false);
        $this->statement->execute(array_values($params));
        $rows=$this->statement->fetchAll(PDO::FETCH_ASSOC);
        $cols=$rows!==[]?array_keys($rows[0]):[];
        return new SqlResult($rows,$this->statement->rowCount(),$cols);
    }

    public function execute(array $params=[]): int
    {
        $this->owner->assertSqlAllowed($this->statement->queryString,true);
        $this->statement->execute(array_values($params));
        return $this->statement->rowCount();
    }
}

/**
 * First-class JX SQL object.
 *
 * Security defaults:
 * - environment capability checked before connection/use;
 * - PDO exceptions enabled;
 * - emulated prepares disabled when the driver supports the attribute;
 * - public operations bind values as parameters rather than concatenating them;
 * - read-only mode rejects write/DDL verbs before PDO execution;
 * - credentials are never exposed through a serialization API.
 */
final class JxSql
{
    private PDO $pdo;
    private EnvironmentProfile $environment;
    private int $transactionDepth=0;

    public function __construct(public readonly SqlConfig $config)
    {
        $this->environment=$config->environment??EnvironmentProfile::server('jx-sql');
        $this->environment->require(Capability::SQL,'SQL.CONNECT');
        if($config->driver!==SqlDriver::SQLITE && ($config->username!==null||$config->password!==null)){
            $this->environment->require(Capability::SECRETS,'SQL.CONNECT');
        }
        $options=$config->options+[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC];
        try{$options[PDO::ATTR_EMULATE_PREPARES]=false;}catch(Throwable){}
        try{
            $this->pdo=new PDO($config->dsn,$config->username,$config->password,$options);
        }catch(PDOException $e){throw new RuntimeException('JX SQL connection failed: '.$e->getMessage(),0,$e);}
    }

    public function driver(): string{return $this->config->driver;}
    public function environment(): EnvironmentProfile{return $this->environment;}
    public function inTransaction(): bool{return $this->pdo->inTransaction();}

    public function prepare(string $sql): JxSqlStatement
    {
        $this->assertSqlAllowed($sql,$this->isWriteSql($sql));
        return new JxSqlStatement($this->pdo->prepare($sql),$this);
    }

    public function query(string $sql,array $params=[]): SqlResult
    {
        return $this->prepare($sql)->query($params);
    }

    public function execute(string $sql,array $params=[]): int
    {
        $this->assertSqlAllowed($sql,true);
        return $this->prepare($sql)->execute($params);
    }

    public function begin(): self
    {
        if($this->transactionDepth===0){$this->pdo->beginTransaction();}
        else{$this->savepoint('__jx_tx_'.$this->transactionDepth);}
        $this->transactionDepth++;
        return $this;
    }

    public function commit(): self
    {
        if($this->transactionDepth<1)throw new RuntimeException('SQL commit without transaction');
        $this->transactionDepth--;
        if($this->transactionDepth===0)$this->pdo->commit();
        else $this->releaseSavepoint('__jx_tx_'.$this->transactionDepth);
        return $this;
    }

    public function rollback(): self
    {
        if($this->transactionDepth<1)throw new RuntimeException('SQL rollback without transaction');
        $this->transactionDepth--;
        if($this->transactionDepth===0)$this->pdo->rollBack();
        else $this->rollbackTo('__jx_tx_'.$this->transactionDepth);
        return $this;
    }

    public function savepoint(string $name): self
    {
        $name=$this->safeIdentifier($name);
        $this->pdo->exec('SAVEPOINT '.$name);
        return $this;
    }

    public function rollbackTo(string $name): self
    {
        $name=$this->safeIdentifier($name);
        $this->pdo->exec('ROLLBACK TO SAVEPOINT '.$name);
        return $this;
    }

    public function releaseSavepoint(string $name): self
    {
        $name=$this->safeIdentifier($name);
        $this->pdo->exec('RELEASE SAVEPOINT '.$name);
        return $this;
    }

    public function transaction(callable $work): mixed
    {
        $this->begin();
        try{$result=$work($this);$this->commit();return $result;}
        catch(Throwable $e){if($this->transactionDepth>0)$this->rollback();throw $e;}
    }

    public function assertSqlAllowed(string $sql,bool $write): void
    {
        $this->environment->require(Capability::SQL,$write?'SQL.EXECUTE':'SQL.QUERY');
        if($this->config->readOnly && ($write||$this->isWriteSql($sql)))throw new RuntimeException('JX SQL read-only connection rejected mutating statement');
    }

    private function isWriteSql(string $sql): bool
    {
        $s=ltrim($sql);
        if($s==='')return false;
        if(!preg_match('/^([A-Za-z]+)/',$s,$m))return false;
        return in_array(strtoupper($m[1]),['INSERT','UPDATE','DELETE','REPLACE','CREATE','ALTER','DROP','TRUNCATE','VACUUM','ATTACH','DETACH','PRAGMA'],true);
    }

    private function safeIdentifier(string $name): string
    {
        if(!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/',$name))throw new InvalidArgumentException('Unsafe SQL identifier');
        return $name;
    }
}
