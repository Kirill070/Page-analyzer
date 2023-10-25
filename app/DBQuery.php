<?php

namespace App;

class DBQuery
{
    private $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function insert(string $name)
    {
        $sql = 'INSERT INTO urls(name) VALUES(:name)';

        $stmt = $this->pdo->prepare($sql);

        $stmt->bindValue(':name', $name);

        $stmt->execute();

        return $this->pdo->lastInsertId('urls_id_seq');
    }
}
