<?php

namespace Zeynallow\Booknetic;

use Zeynallow\Booknetic\Exceptions\DatabaseException;

class Database {
	
    private $host;
    private $username;
    private $password;
    private $database;
    private $connection;

    public function __construct() {
        try {
            $this->host =  $_ENV["DB_HOST"];
            $this->username =  $_ENV["DB_USERNAME"];
            $this->password =  $_ENV["DB_PASSWORD"];
            $this->database = $_ENV["DB_NAME"];
            
            $this->connection = new \PDO("mysql:host={$this->host};dbname={$this->database}", $this->username, $this->password);
            $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch(\PDOException $e) {
            throw new DatabaseException("Database connection failed: " . $e->getMessage());
        }
    }

    public function exec($query){
        try {
           return $this->connection->exec($query);
        } catch(\PDOException $e) {
            throw new DatabaseException("Error executing query: " . $e->getMessage());
        }
    }

    public function select($query, $params = array()) {
        try {
            $statement = $this->connection->prepare($query);
            $statement->execute($params);
            return $statement->fetchAll(\PDO::FETCH_ASSOC);
        } catch(\PDOException $e) {
            throw new DatabaseException("Error executing SELECT query: " . $e->getMessage());
        }
    }

    public function selectOne($query, $params = array()) {
        try {
            $statement = $this->connection->prepare($query);
            $statement->execute($params);
            return $statement->fetch(\PDO::FETCH_ASSOC);
        } catch(\PDOException $e) {
            throw new DatabaseException("Error executing SELECT query: " . $e->getMessage());
        }
    }

    public function insert($table, $data) {
        try {
            $keys = implode(", ", array_keys($data));
            $values = ":" . implode(", :", array_keys($data));
            $query = "INSERT INTO $table ($keys) VALUES ($values)";
            $statement = $this->connection->prepare($query);
            $statement->execute($data);
            return $this->connection->lastInsertId();
        } catch(\PDOException $e) {
            throw new DatabaseException("Error executing INSERT query: " . $e->getMessage());
        }
    }
    
    public function update($table, $data, $whereConditions = array()) {
        try {
            $set = '';
            foreach ($data as $key => $value) {
                $set .= "$key=:$key, ";
            }
            $set = rtrim($set, ', ');
    
            $where = '';
            foreach ($whereConditions as $key => $value) {
                $where .= "$key=:$key AND ";
            }
            $where = rtrim($where, 'AND ');
    
            $query = "UPDATE $table SET $set WHERE $where";
            $statement = $this->connection->prepare($query);
            $statement->execute(array_merge($data, $whereConditions));
            return $statement->rowCount();
        } catch(\PDOException $e) {
            throw new DatabaseException("Error executing UPDATE query: " . $e->getMessage());
        }
    }
}
