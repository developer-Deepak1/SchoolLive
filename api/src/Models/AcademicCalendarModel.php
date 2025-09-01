<?php
namespace SchoolLive\Models;

class AcademicCalendarModel extends Model {
    protected $table = 'Tx_Academic_Calendar';

    public function getByAcademicYear($academicYearId) {
        $query = "SELECT CalendarID as calendar_id, AcademicYearID as academic_year_id, `Date` as date, DayType as day_type, Title as title, Description as description, IsHalfDay as is_half_day, CreatedAt as created_at, UpdatedAt as updated_at FROM " . $this->table . " WHERE AcademicYearID = :ay ORDER BY `Date`";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':ay', $academicYearId);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create($data) {
        $query = "INSERT INTO " . $this->table . " (AcademicYearID, `Date`, DayType, Title, Description, IsHalfDay, CreatedBy) VALUES (:ay, :date, :daytype, :title, :description, :ishalf, :createdby)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':ay', $data['AcademicYearID']);
        $stmt->bindParam(':date', $data['Date']);
        $stmt->bindParam(':daytype', $data['DayType']);
        $stmt->bindParam(':title', $data['Title']);
        $stmt->bindParam(':description', $data['Description']);
        $stmt->bindParam(':ishalf', $data['IsHalfDay']);
        $stmt->bindParam(':createdby', $data['CreatedBy']);
        if ($stmt->execute()) return $this->conn->lastInsertId();
        return false;
    }

    public function update($id, $data) {
        $data['UpdatedAt'] = date('Y-m-d H:i:s');
        $fields = [];
        $params = [':id' => $id];
        $allowed = ['Date','DayType','Title','Description','IsHalfDay','UpdatedBy'];
        foreach ($allowed as $f) {
            if (isset($data[$f])) { $fields[] = "$f = :$f"; $params[':' . $f] = $data[$f]; }
        }
        if (empty($fields)) return false;
        $query = "UPDATE " . $this->table . " SET " . implode(', ', $fields) . " WHERE CalendarID = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute($params);
    }

    public function delete($id) {
        $query = "DELETE FROM " . $this->table . " WHERE CalendarID = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
