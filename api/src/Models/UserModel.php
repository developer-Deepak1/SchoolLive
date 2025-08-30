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
        
        $mappedData['CreatedAt'] = date('Y-m-d H:i:s');
        $mappedData['CreatedBy'] = $data['created_by'] ?? 'System';
        
        return $this->create($mappedData);
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
