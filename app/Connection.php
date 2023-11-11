<?php

namespace App;

use Dotenv\Dotenv;

final class Connection
{
    private static ?Connection $conn = null;

    public function connect()
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->safeLoad();
        $databaseUrl = parse_url($_ENV['DATABASE_URL']);
        var_dump($databaseUrl);
        if ($databaseUrl === false) {
            throw new \Exception("Error reading database url");
        }

        // $username = $databaseUrl['user'];
        // $password = $databaseUrl['pass'];
        // $host = $databaseUrl['host'];
        // $dbName = ltrim($databaseUrl['path'], '/');

        // $conStr = sprintf(
        //     "pgsql:host=%s;dbname=%s;user=%s;password=%s",
        //     $host,
        //     $dbName,
        //     $username,
        //     $password
        // );

        // $pdo = new \PDO($conStr);
        // $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);


        $host = isset($databaseUrl['host']) ? $databaseUrl['host'] : '';
        $port = isset($databaseUrl['port']) ? $databaseUrl['port'] : '';
        $dbname = isset($databaseUrl['path']) ? ltrim($databaseUrl['path'], '/') : '';
        $username = isset($databaseUrl['user']) ? $databaseUrl['user'] : '';
        $password = isset($databaseUrl['pass']) ? $databaseUrl['pass'] : '';

        $conStr = "pgsql:host=$host;port=$port;dbname=$dbname";
        $opt = array(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo = new \PDO($conStr, $username, $password, $opt);
        return $pdo;
    }

    public static function get()
    {
        if (null === static::$conn) {
            static::$conn = new self();
        }

        return static::$conn;
    }

    protected function __construct()
    {
    }
}
