<?php declare(strict_types=1);

require_once __DIR__ . '/jx-sql.php';

use jx\EnvironmentProfile;
use jx\JxSql;
use jx\SqlConfig;

$eq=static function(mixed $a,mixed $b,string $label):void{if($a!==$b){fwrite(STDERR,"FAIL {$label}: got ".var_export($a,true)." expected ".var_export($b,true)."\n");exit(1);}};

$db=new JxSql(SqlConfig::sqliteMemory(EnvironmentProfile::test('sql-test')));
$eq($db->driver(),'sqlite','driver');
$db->execute('CREATE TABLE card (id INTEGER PRIMARY KEY, name TEXT NOT NULL, price INTEGER NOT NULL)');
$insert=$db->prepare('INSERT INTO card(name, price) VALUES (?, ?)');
$insert->execute(['Mays',900]);
$insert->execute(['Mantle',1200]);

$result=$db->query('SELECT id,name,price FROM card WHERE price >= ? ORDER BY price',[1000]);
$eq(count($result->rows),1,'parameterized query count');
$eq($result->first()['name']??null,'Mantle','parameterized query value');
$eq($result->columns,['id','name','price'],'typed row column names');

// Injection text remains a value because parameters are never interpolated into SQL.
$probe=$db->query('SELECT COUNT(*) AS n FROM card WHERE name = ?',["Mantle' OR 1=1 --"]);
$eq((int)($probe->first()['n']??-1),0,'bound injection-like string stays data');

$db->begin();
$db->execute('INSERT INTO card(name,price) VALUES (?,?)',['Rollback',1]);
$db->rollback();
$eq((int)$db->query('SELECT COUNT(*) AS n FROM card WHERE name=?',['Rollback'])->first()['n'],0,'rollback');

$db->transaction(function(JxSql $tx):void{
    $tx->execute('INSERT INTO card(name,price) VALUES (?,?)',['Committed',77]);
});
$eq((int)$db->query('SELECT COUNT(*) AS n FROM card WHERE name=?',['Committed'])->first()['n'],1,'transaction commit');

$db->begin();
$db->execute('INSERT INTO card(name,price) VALUES (?,?)',['Outer',10]);
$db->begin();
$db->execute('INSERT INTO card(name,price) VALUES (?,?)',['Inner',11]);
$db->rollback();
$db->commit();
$eq((int)$db->query("SELECT COUNT(*) AS n FROM card WHERE name='Outer'")->first()['n'],1,'outer transaction retained');
$eq((int)$db->query("SELECT COUNT(*) AS n FROM card WHERE name='Inner'")->first()['n'],0,'nested savepoint rollback');

$ro=new JxSql(SqlConfig::sqliteMemory(EnvironmentProfile::test('sql-ro'),true));
$eq((int)$ro->query('SELECT 1 AS n')->first()['n'],1,'read-only query');
$blocked=false;
try{$ro->execute('CREATE TABLE forbidden(id INTEGER)');}catch(RuntimeException){$blocked=true;}
$eq($blocked,true,'read-only write blocked before PDO');

$browserBlocked=false;
try{new JxSql(SqlConfig::sqliteMemory(EnvironmentProfile::browser('browser')));}catch(Throwable){$browserBlocked=true;}
$eq($browserBlocked,true,'browser environment cannot acquire SQL capability');

fwrite(STDOUT,"PASS JX SQL prepared parameters transactions savepoints read-only staging\n");
