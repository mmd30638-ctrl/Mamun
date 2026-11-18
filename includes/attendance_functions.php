<?php
/**
 * HRM Attendance Helper (mysqli, schema-safe)
 * Path: public_html/includes/attendance_functions.php
 */

function ensure_attendance_schema($db) {
    static $done = false;
    if ($done) {
        return;
    }
    if (function_exists('ame_schema_stamp') && ame_schema_stamp('attendance')) {
        $done = true;
        return;
    }
    $done = true;
    $db->query("CREATE TABLE IF NOT EXISTS attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT DEFAULT NULL,
        att_date DATE DEFAULT NULL,
        check_in TIME DEFAULT NULL,
        check_out TIME DEFAULT NULL,
        status ENUM('Present','Absent','Leave','Late') DEFAULT 'Present',
        KEY employee_id (employee_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $indexes = [];
    $res = $db->query("SHOW INDEX FROM attendance");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $indexes[] = $row['Key_name'];
        }
    }
    if (!in_array('uniq_emp_date', $indexes, true)) {
        @$db->query("ALTER TABLE attendance ADD UNIQUE KEY uniq_emp_date (employee_id, att_date)");
    }
    $cols = [];
    $cres = @$db->query('SHOW COLUMNS FROM attendance');
    if ($cres) {
        while ($c = $cres->fetch_assoc()) {
            $cols[$c['Field']] = true;
        }
    }
    if (!isset($cols['activated_at'])) {
        @$db->query('ALTER TABLE attendance ADD COLUMN activated_at DATETIME NULL DEFAULT NULL');
    }
    if (!isset($cols['source'])) {
        @$db->query("ALTER TABLE attendance ADD COLUMN source VARCHAR(40) NULL DEFAULT NULL");
    }
    if (function_exists('ame_schema_stamp')) {
        ame_schema_stamp('attendance', true);
    }
}

function get_attendance_row($db, $employee_id, $date) {
    $stmt = $db->prepare("SELECT * FROM attendance WHERE employee_id = ? AND att_date = ?");
    $stmt->bind_param("is", $employee_id, $date);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}