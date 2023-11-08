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

    public function insertCheck(int $url_id)
    {
        $sql = 'INSERT INTO url_checks(url_id, created_at) VALUES(:url_id, :created_at)';

        $stmt = $this->pdo->prepare($sql);

        $stmt->bindValue(':url_id', $url_id);
        $stmt->bindValue(':created_at', Carbon::now());

        $stmt->execute();

        return $this->pdo->lastInsertId('url_checks_id_seq');
    }

    public function selectURL(string $name)
    {
        $sql = 'SELECT * FROM urls WHERE name = ?';

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([$name]);

        return $stmt->fetchAll();
    }

    public function selectID(int $id)
    {
        $sql = 'SELECT * FROM urls WHERE id = ?';

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([$id]);

        return $stmt->fetch();
    }

    public function selectUrlID(int $id)
    {
        $sql = 'SELECT * FROM url_checks WHERE url_id = ?';

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([$id]);

        return $stmt->fetchAll();
    }

    public function selectAll()
    {
        $sql = 'SELECT * FROM urls ORDER BY created_at DESC';

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute();

        return $stmt->fetchAll();
    }
}
