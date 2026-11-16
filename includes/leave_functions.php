<?php
/**
 * HRM Leave Management Helper (mysqli, schema-safe)
 * Path: public_html/includes/leave_functions.php
 */

function ensure_leave_schema($db) {
    $db->query("CREATE TABLE IF NOT EXISTS leave_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        leave_type VARCHAR(50) NOT NULL DEFAULT 'সাধারণ ছুটি',
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        total_days INT NOT NULL DEFAULT 1,
        reason VARCHAR(255) NULL,
        status ENUM('Pending','Approved','Rejected','Cancelled') NOT NULL DEFAULT 'Pending',
        applied_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        decided_by INT NULL,
        decided_at DATETIME NULL,
        KEY employee_id (employee_id),
        KEY status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * একজন কর্মীর এই বছরে অনুমোদিত ছুটির মোট দিন
 */
function get_employee_leave_days_this_year($db, $employee_id) {
    $year = date('Y');
    $stmt = $db->prepare(
        "SELECT SUM(total_days) AS total
         FROM leave_requests
         WHERE employee_id = ?
           AND status = 'Approved'
           AND YEAR(start_date) = ?"
    );
    $stmt->bind_param("is", $employee_id, $year);
    $stmt->execute();
    $total = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();
    return $total;
}