<?php
namespace SchoolLive\Models;

use PDO;

class UserModel extends Model {
    protected $table = 'Tx_Users';

    public function createUser($data) {
        // Hash password before storing
        if (isset($data['password'])) {
            $data['PasswordHash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            unset($data['password']);
        }
        
        // Map old field names to new ones
        $mappedData = [];
        if (isset($data['first_name'])) $mappedData['FirstName'] = $data['first_name'];
        if (isset($data['middle_name'])) $mappedData['MiddleName'] = $data['middle_name'];
        if (isset($data['last_name'])) $mappedData['LastName'] = $data['last_name'];
        if (isset($data['email'])) $mappedData['EmailID'] = $data['email'];
        if (isset($data['contact'])) $mappedData['ContactNumber'] = $data['contact'];
        if (isset($data['username'])) $mappedData['Username'] = $data['username'];
        if (isset($data['role_id'])) $mappedData['RoleID'] = $data['role_id'];
        if (isset($data['school_id'])) $mappedData['SchoolID'] = $data['school_id'];
        if (isset($data['PasswordHash'])) $mappedData['PasswordHash'] = $data['PasswordHash'];
        
        // Ensure Username exists (DB requires it)
        if (empty($mappedData['Username'])) {
            // create a provisional username to avoid DB NOT NULL constraint failures
            $mappedData['Username'] = 'usr_' . uniqid();
        }

        $mappedData['CreatedAt'] = date('Y-m-d H:i:s');
        $mappedData['CreatedBy'] = $data['created_by'] ?? 'System';
        
        return $this->insertTxUser($mappedData);
    }

    /**
     * Insert a user directly into Tx_Users with explicit fields.
     * Accepts keys: username, first_name, middle_name, last_name, role_id, school_id, password, email, contact, created_by
     * Returns inserted UserID or false on failure.
     */
    public function insertTxUser(array $data) {
        // Accept input where keys may already be DB column names (e.g. Username, FirstName, PasswordHash)
        // Build the fields list in a deterministic order and fill params accordingly so placeholders match columns.
        $fields = [];
        $params = [];

        // Helper to check either camel-case DB key or snake-case input
        $get = function($dbKey, $altKey = null) use ($data) {
            if (array_key_exists($dbKey, $data)) return $data[$dbKey];
            if ($altKey && array_key_exists($altKey, $data)) return $data[$altKey];
            return null;
        };

        // Desired column order
        $candidates = [
            ['Username','username'],
            ['FirstName','first_name'],
            ['MiddleName','middle_name'],
            ['LastName','last_name'],
            ['RoleID','role_id'],
            ['SchoolID','school_id'],
            ['PasswordHash','password'], // if password provided in 'password' we hash it and store as PasswordHash
            ['EmailID','email'],
            ['ContactNumber','contact'],
            ['CreatedAt', null],
            ['CreatedBy','created_by']
        ];

        foreach ($candidates as [$col, $alt]) {
            $val = $get($col, $alt);
            if ($col === 'PasswordHash') {
                // if caller provided PasswordHash already, use it; otherwise check for 'password'
                if (array_key_exists('PasswordHash', $data) && $data['PasswordHash']) {
                    $val = $data['PasswordHash'];
                } elseif (!empty($data['password'])) {
                    $val = password_hash($data['password'], PASSWORD_DEFAULT);
                } else {
                    $val = null;
                }
            }

            if ($col === 'CreatedAt') {
                $val = $val ?? date('Y-m-d H:i:s');
            }

            if ($val !== null && $val !== '') {
                $fields[] = $col;
                // cast ints where appropriate
                if (in_array($col, ['RoleID','SchoolID'])) {
                    $params[':' . $col] = (int)$val;
                } else {
                    $params[':' . $col] = $val;
                }
            }
        }

        // Ensure Username present
        if (!in_array('Username', $fields, true)) {
            array_unshift($fields, 'Username');
            $params[':Username'] = 'usr_' . uniqid();
        }

        if (empty($fields)) return false;

        $cols = implode(', ', $fields);
        $placeholders = implode(', ', array_map(fn($f) => ':' . $f, $fields));

        $query = "INSERT INTO Tx_Users ({$cols}) VALUES ({$placeholders})";
        $stmt = $this->conn->prepare($query);

        foreach ($fields as $f) {
            $p = ':' . $f;
            $v = $params[$p] ?? null;
            if (in_array($f, ['RoleID','SchoolID'])) {
                $stmt->bindValue($p, $v, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($p, $v, PDO::PARAM_STR);
            }
        }

        if ($stmt->execute()) {
            return (int)$this->conn->lastInsertId();
        }
        return false;
    }

    

    public function updateUser($id, $data) {
        // Hash password if it's being updated
        if (isset($data['password']) && !empty($data['password'])) {
            $data['PasswordHash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            unset($data['password']);
        }
        
        // Map old field names to new ones
        $mappedData = [];
        if (isset($data['first_name'])) $mappedData['FirstName'] = $data['first_name'];
        if (isset($data['middle_name'])) $mappedData['MiddleName'] = $data['middle_name'];
        if (isset($data['last_name'])) $mappedData['LastName'] = $data['last_name'];
        if (isset($data['email'])) $mappedData['EmailID'] = $data['email'];
        if (isset($data['contact'])) $mappedData['ContactNumber'] = $data['contact'];
        if (isset($data['role_id'])) $mappedData['RoleID'] = $data['role_id'];
        if (isset($data['school_id'])) $mappedData['SchoolID'] = $data['school_id'];
        if (isset($data['is_active'])) $mappedData['IsActive'] = $data['is_active'];
        if (isset($data['PasswordHash'])) $mappedData['PasswordHash'] = $data['PasswordHash'];
        
        $mappedData['UpdatedAt'] = date('Y-m-d H:i:s');
        $mappedData['UpdatedBy'] = $data['updated_by'] ?? 'System';
        
        return $this->update($id, $mappedData);
    }

    public function findByEmail($email) {
        $query = "SELECT u.*, r.RoleName, r.RoleDisplayName, s.SchoolName 
                  FROM " . $this->table . " u 
                  LEFT JOIN Tm_Roles r ON u.RoleID = r.RoleID
                  LEFT JOIN Tm_Schools s ON u.SchoolID = s.SchoolID
                  WHERE u.EmailID = :email";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function findByUsername($username) {
        $query = "SELECT u.*, r.RoleName, r.RoleDisplayName, s.SchoolName 
                  FROM " . $this->table . " u 
                  LEFT JOIN Tm_Roles r ON u.RoleID = r.RoleID
                  LEFT JOIN Tm_Schools s ON u.SchoolID = s.SchoolID
                  WHERE u.Username = :username";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function authenticate($username, $password) {
        $query = "SELECT * FROM " . $this->table . " WHERE Username = :username AND IsActive = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['PasswordHash'])) {
            return $user;
        }

        return false;
    }

    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    public function getUsersByRole($roleId) {
        $query = "SELECT u.UserID, u.Username, u.FirstName, u.MiddleName, u.LastName, 
                         u.EmailID, u.ContactNumber, u.IsActive, u.CreatedAt,
                         r.RoleName, r.RoleDisplayName, s.SchoolName 
                  FROM " . $this->table . " u 
                  LEFT JOIN Tm_Roles r ON u.RoleID = r.RoleID
                  LEFT JOIN Tm_Schools s ON u.SchoolID = s.SchoolID
                  WHERE u.RoleID = :role_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':role_id', $roleId);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getUsersBySchool($schoolId) {
        $query = "SELECT u.UserID, u.Username, u.FirstName, u.MiddleName, u.LastName, 
                         u.EmailID, u.ContactNumber, u.IsActive, u.CreatedAt,
                         r.RoleName, r.RoleDisplayName 
                  FROM " . $this->table . " u 
                  LEFT JOIN Tm_Roles r ON u.RoleID = r.RoleID
                  WHERE u.SchoolID = :school_id AND u.IsActive = 1
                  ORDER BY u.FirstName, u.LastName";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':school_id', $schoolId);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getActiveUsers() {
        $query = "SELECT u.UserID, u.Username, u.FirstName, u.MiddleName, u.LastName, 
                         u.EmailID, u.ContactNumber, u.CreatedAt,
                         r.RoleName, r.RoleDisplayName, s.SchoolName 
                  FROM " . $this->table . " u 
                  LEFT JOIN Tm_Roles r ON u.RoleID = r.RoleID
                  LEFT JOIN Tm_Schools s ON u.SchoolID = s.SchoolID
                  WHERE u.IsActive = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Override findAll to exclude password
    public function findAll($limit = null, $offset = null) {
        $query = "SELECT u.UserID, u.Username, u.FirstName, u.MiddleName, u.LastName, 
                         u.EmailID, u.ContactNumber, u.IsActive, u.CreatedAt, u.UpdatedAt,
                         r.RoleName, r.RoleDisplayName, s.SchoolName 
                  FROM " . $this->table . " u 
                  LEFT JOIN Tm_Roles r ON u.RoleID = r.RoleID
                  LEFT JOIN Tm_Schools s ON u.SchoolID = s.SchoolID";
        
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

    // Override findById to exclude password
    public function findById($id) {
        $query = "SELECT u.UserID, u.Username, u.FirstName, u.MiddleName, u.LastName, 
                         u.EmailID, u.ContactNumber, u.IsActive, u.CreatedAt, u.UpdatedAt,
                         r.RoleName, r.RoleDisplayName, s.SchoolName 
                  FROM " . $this->table . " u 
                  LEFT JOIN Tm_Roles r ON u.RoleID = r.RoleID
                  LEFT JOIN Tm_Schools s ON u.SchoolID = s.SchoolID
                  WHERE u.UserID = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function updatePassword($userId, $newPassword) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $query = "UPDATE " . $this->table . " SET PasswordHash = :password, 
                  IsFirstLogin = 0, UpdatedAt = CURRENT_TIMESTAMP 
                  WHERE UserID = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            'password' => $hashedPassword,
            'id' => $userId
        ]);
    }

    public function deactivateUser($userId, $updatedBy) {
        $query = "UPDATE " . $this->table . " SET IsActive = 0, 
                  UpdatedBy = :updated_by, UpdatedAt = CURRENT_TIMESTAMP 
                  WHERE UserID = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            'id' => $userId,
            'updated_by' => $updatedBy
        ]);
    }
}
