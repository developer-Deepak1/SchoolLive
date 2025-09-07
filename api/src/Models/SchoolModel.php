<?php
namespace SchoolLive\Models;

use PDO;

class SchoolModel extends Model {
    protected $table = 'Tm_Schools';

    public function getSchoolById($id) {
        $sql = "SELECT * FROM Tm_Schools WHERE SchoolID = :id LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function getSchoolByUserId($userId) {
        $sql = "SELECT s.* FROM Tm_Schools s
                JOIN Tx_Users u ON u.SchoolID = s.SchoolID
                WHERE u.UserID = :uid LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
    }
}
