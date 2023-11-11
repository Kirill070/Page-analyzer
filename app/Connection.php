<?php

namespace App;

final class Connection
{
    private static ?Connection $conn = null;

    public function connect()
    {
        $databaseUrl = parse_url((string) getenv('DATABASE_URL'));
        if ($databaseUrl === false) {
            throw new \Exception("Error reading database url");
        }

        $username = $databaseUrl['user'];
        $password = $databaseUrl['pass'];
        $host = $databaseUrl['host'];
        $dbName = ltrim($databaseUrl['path'], '/');

        $conStr = sprintf(
            "pgsql:host=%s;dbname=%s;user=%s;password=%s",
            $host,
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
