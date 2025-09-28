<?php

namespace SchoolLive\Models;

use SchoolLive\Models\Model;
use PDO;

class FinePolicyModel extends Model
{
    protected $table = 'Tx_fine_policies';
    protected $primaryKey = 'FinePolicyID';

    /**
     * List fine policies for a given school and academic year
     */
    public function getPolicies(int $schoolId, int $academicYearId): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE SchoolID = :SchoolID AND AcademicYearID = :AcademicYearID AND IsActive = 1 ORDER BY {$this->primaryKey} DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':SchoolID', $schoolId, PDO::PARAM_INT);
        $stmt->bindValue(':AcademicYearID', $academicYearId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Fetch single policy by id */
    public function getPolicyById(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Create new policy, returns new id */
    public function createPolicy(array $data)
    {
        $now = date('Y-m-d H:i:s');
        $sql = "INSERT INTO {$this->table}
            (SchoolID, AcademicYearID, FeeID, ApplyType, Amount, GraceDays, MaxAmount, IsActive, CreatedAt, CreatedBy)
            VALUES (:SchoolID, :AcademicYearID, :FeeID, :ApplyType, :Amount, :GraceDays, :MaxAmount, :IsActive, :CreatedAt, :CreatedBy)";
        $stmt = $this->conn->prepare($sql);

        $stmt->bindValue(':SchoolID', (int)$data['SchoolID'], PDO::PARAM_INT);
        $stmt->bindValue(':AcademicYearID', (int)$data['AcademicYearID'], PDO::PARAM_INT);
        if (array_key_exists('FeeID', $data) && $data['FeeID'] !== null && $data['FeeID'] !== '') {
            $stmt->bindValue(':FeeID', (int)$data['FeeID'], PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':FeeID', null, PDO::PARAM_NULL);
        }
        $stmt->bindValue(':ApplyType', $data['ApplyType']);
        // Bind amounts as string to avoid driver precision surprises; DB will cast to DECIMAL
        $stmt->bindValue(':Amount', (string)$data['Amount']);
        $stmt->bindValue(':GraceDays', (int)$data['GraceDays'], PDO::PARAM_INT);
        if (array_key_exists('MaxAmount', $data) && $data['MaxAmount'] !== null && $data['MaxAmount'] !== '') {
            $stmt->bindValue(':MaxAmount', (string)$data['MaxAmount']);
        } else {
            $stmt->bindValue(':MaxAmount', null, PDO::PARAM_NULL);
        }
        $stmt->bindValue(':IsActive', !empty($data['IsActive']) ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':CreatedAt', $now);
        $stmt->bindValue(':CreatedBy', $data['CreatedBy'] ?? 'System');

        $ok = $stmt->execute();
        if (!$ok) { return false; }
        return (int)$this->conn->lastInsertId();
    }

    /** Update an existing policy */
    public function updatePolicy(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];
        $now = date('Y-m-d H:i:s');

        $allowed = ['FeeID','ApplyType','Amount','GraceDays','MaxAmount','IsActive','UpdatedBy'];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                if ($key === 'FeeID') {
                    $fields[] = 'FeeID = :FeeID';
                    if ($data['FeeID'] === null || $data['FeeID'] === '') {
                        $params[':FeeID'] = null; // bind as null later
                    } else {
                        $params[':FeeID'] = (int)$data['FeeID'];
                    }
                } elseif ($key === 'Amount' || $key === 'MaxAmount') {
                    $fields[] = "$key = :$key";
                    $params[":$key"] = $data[$key] === null || $data[$key] === '' ? null : (string)$data[$key];
                } elseif ($key === 'GraceDays') {
                    $fields[] = 'GraceDays = :GraceDays';
                    $params[':GraceDays'] = (int)$data['GraceDays'];
                } elseif ($key === 'IsActive') {
                    $fields[] = 'IsActive = :IsActive';
                    $params[':IsActive'] = !empty($data['IsActive']) ? 1 : 0;
                } else {
                    $fields[] = "$key = :$key";
                    $params[":$key"] = $data[$key];
                }
            }
        }

        if (empty($fields)) { return true; }
        $fields[] = 'UpdatedAt = :UpdatedAt';
        $params[':UpdatedAt'] = $now;

        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE {$this->primaryKey} = :id";
        $stmt = $this->conn->prepare($sql);

        foreach ($params as $p => $v) {
            // Bind FeeID explicitly as int or NULL
            if ($p === ':FeeID') {
                if ($v === null) {
                    $stmt->bindValue($p, null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue($p, (int)$v, PDO::PARAM_INT);
                }
                continue;
            }

            // Bind Amount/MaxAmount as strings when present, or NULL when explicitly null
            if ($p === ':Amount' || $p === ':MaxAmount') {
                if ($v === null) {
                    $stmt->bindValue($p, null, PDO::PARAM_NULL);
                } else {
                    // Preserve numeric zero and other values by casting to string
                    $stmt->bindValue($p, (string)$v);
                }
                continue;
            }

            // Integer bindings
            if ($p === ':IsActive' || $p === ':GraceDays' || $p === ':id') {
                $stmt->bindValue($p, (int)$v, PDO::PARAM_INT);
                continue;
            }

            // Fallback - bind as-is
            $stmt->bindValue($p, $v);
        }

        return $stmt->execute();
    }

    /**
     * Soft delete (disable) a policy instead of hard delete
     */
    public function deletePolicy(int $id): bool
    {
        $sql = "UPDATE {$this->table} SET IsActive = 0, UpdatedAt = :UpdatedAt, UpdatedBy = :UpdatedBy WHERE {$this->primaryKey} = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':UpdatedAt', date('Y-m-d H:i:s'));
        $stmt->bindValue(':UpdatedBy', 'System');
        return $stmt->execute();
    }
}
