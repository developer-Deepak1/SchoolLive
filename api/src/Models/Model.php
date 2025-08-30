<?php
namespace SchoolLive\Models;

use SchoolLive\Core\Database;
use PDO;
use PDOException;

abstract class Model {
    protected $db;
    protected $conn;
    protected $table;

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }

    // Find all records
    public function findAll($limit = null, $offset = null) {
        $query = "SELECT * FROM " . $this->table;
        
        if ($limit) {
            $query .= " LIMIT :limit";
            if ($offset) {
                $query .= " OFFSET :offset";
            }
        }

        $stmt = $this->conn->prepare($query);
        
        if ($limit) {
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            if ($offset) {
                $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            }
        }

        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Find by ID
    public function findById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch();
    }

    // Find by field
    public function findBy($field, $value) {
        $query = "SELECT * FROM " . $this->table . " WHERE " . $field . " = :value";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':value', $value);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Find one by field
    public function findOneBy($field, $value) {
        $query = "SELECT * FROM " . $this->table . " WHERE " . $field . " = :value LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':value', $value);
        $stmt->execute();
        return $stmt->fetch();
    }

    // Create record
    public function create($data) {
        $fields = array_keys($data);
        $placeholders = array_map(function($field) { return ':' . $field; }, $fields);
        
        $query = "INSERT INTO " . $this->table . " (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $this->conn->prepare($query);
        
        foreach ($data as $field => $value) {
            $stmt->bindParam(':' . $field, $value);
        }
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    // Update record
    public function update($id, $data) {
        $fields = array_keys($data);
        $setClause = array_map(function($field) { return $field . ' = :' . $field; }, $fields);
        
        $query = "UPDATE " . $this->table . " SET " . implode(', ', $setClause) . " WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        foreach ($data as $field => $value) {
            $stmt->bindParam(':' . $field, $value);
        }
        
        return $stmt->execute();
    }

    // Delete record
    public function delete($id) {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    // Count records
    public function count($where = null) {
        $query = "SELECT COUNT(*) as count FROM " . $this->table;
        if ($where) {
            $query .= " WHERE " . $where;
        }
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch();
        return $result['count'];
    }

    public function __destruct() {
        if ($this->db) {
            $this->db->closeConnection();
        }
    }
}
