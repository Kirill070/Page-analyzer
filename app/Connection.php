<?php

namespace App;

final class Connection
{
    private static ?Connection $conn = null;

    public function connect()
    {
        $params = parse_url($_ENV['DATABASE_URL']);
        if ($params === false) {
            throw new \Exception("Error reading database url");
        }

        $username = $databaseUrl['user'];
        $password = $databaseUrl['pass'];
        $host = $databaseUrl['host'];
        $port = isset($databaseUrl['port']) ? $databaseUrl['port'] : '';
        $dbName = ltrim($databaseUrl['path'], '/');

        $conStr = sprintf(
            "pgsql:host=%s;port=%d;dbname=%s;user=%s;password=%s",
            $host,
            $port,
            $dbName,
            $username,
            $password
        );

        $pdo = new \PDO($conStr);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

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
