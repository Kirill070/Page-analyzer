<?php

namespace App;

use Carbon\Carbon;

class DBQuery
{
    private $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function insert(string $name)
    {
        $sql = 'INSERT INTO urls(name, created_at) VALUES(:name, :created_at)';

        $stmt = $this->pdo->prepare($sql);

        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':created_at', Carbon::now());

        $stmt->execute();

        return $this->pdo->lastInsertId('urls_id_seq');
    }
}
