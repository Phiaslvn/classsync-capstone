<?php
/**
 * Per-department instructor appointments (employment lines).
 * Used for workload limits keyed by subject.dept_id; time conflicts remain global on inst_id.
 */

if (!function_exists('ida_appointments_table_exists')) {
    function ida_appointments_table_exists(mysqli $conn): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $r = @$conn->query("SHOW TABLES LIKE 'instructor_department_appointment'");
        $cache = $r && $r->num_rows > 0;
        return $cache;
    }

    function ida_get_subject_department_id(mysqli $conn, int $subj_id): ?int
    {
        if ($subj_id <= 0) {
            return null;
        }
        $stmt = $conn->prepare('SELECT dept_id FROM subject WHERE subj_id = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $subj_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row || !isset($row['dept_id'])) {
            return null;
        }
        return (int) $row['dept_id'];
    }

    /**
     * Resolve workload policy for scheduled teaching attributed to subject's department.
     *
     * @param array $instructor_row Row from instructor (inst_status, instruction_hours, …)
     * @return array{inst_status:string,instruction_hours:int,policy_dept_id:int,source:string}
     */
    function ida_get_workload_policy_for_subject_dept(
        mysqli $conn,
        int $inst_id,
        int $subject_dept_id,
        array $instructor_row
    ): array {
        $base = [
            'inst_status' => $instructor_row['inst_status'] ?? 'Regular',
            'instruction_hours' => (int) ($instructor_row['instruction_hours'] ?? 0),
            'policy_dept_id' => $subject_dept_id,
            'source' => 'instructor',
        ];

        if (!ida_appointments_table_exists($conn) || $subject_dept_id <= 0) {
            return $base;
        }

        $stmt = $conn->prepare(
            'SELECT appointment_status, instruction_hours FROM instructor_department_appointment
             WHERE inst_id = ? AND dept_id = ? LIMIT 1'
        );
        if (!$stmt) {
            return $base;
        }
        $stmt->bind_param('ii', $inst_id, $subject_dept_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            return [
                'inst_status' => $row['appointment_status'],
                'instruction_hours' => (int) $row['instruction_hours'],
                'policy_dept_id' => $subject_dept_id,
                'source' => 'appointment',
            ];
        }

        return $base;
    }

    /**
     * Sum active schedule minutes for this instructor in the given school year/term,
     * counting only classes whose subject belongs to $subject_dept_id.
     */
    function ida_sum_scheduled_minutes_for_department(
        mysqli $conn,
        int $inst_id,
        int $sy_id,
        int $schd_term,
        int $subject_dept_id,
        int $exclude_schd_id = 0
    ): float {
        if ($subject_dept_id <= 0) {
            $sql = "SELECT COALESCE(SUM(s.schd_min), 0) AS m FROM schedule s
                    WHERE s.inst_id = ? AND s.sy_id = ? AND s.schd_term = ? AND s.schd_status = 'Active'";
            if ($exclude_schd_id > 0) {
                $sql .= ' AND s.schd_id != ?';
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    return 0.0;
                }
                $stmt->bind_param('iiii', $inst_id, $sy_id, $schd_term, $exclude_schd_id);
            } else {
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    return 0.0;
                }
                $stmt->bind_param('iii', $inst_id, $sy_id, $schd_term);
            }
            $stmt->execute();
            $m = (float) ($stmt->get_result()->fetch_assoc()['m'] ?? 0);
            $stmt->close();
            return $m;
        }

        $sql = 'SELECT COALESCE(SUM(s.schd_min), 0) AS m FROM schedule s
                INNER JOIN subject sub ON s.subj_id = sub.subj_id
                WHERE s.inst_id = ? AND s.sy_id = ? AND s.schd_term = ? AND s.schd_status = \'Active\'
                AND sub.dept_id = ?';
        if ($exclude_schd_id > 0) {
            $sql .= ' AND s.schd_id != ?';
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return 0.0;
            }
            $stmt->bind_param('iiiii', $inst_id, $sy_id, $schd_term, $subject_dept_id, $exclude_schd_id);
        } else {
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return 0.0;
            }
            $stmt->bind_param('iiii', $inst_id, $sy_id, $schd_term, $subject_dept_id);
        }
        $stmt->execute();
        $m = (float) ($stmt->get_result()->fetch_assoc()['m'] ?? 0);
        $stmt->close();
        return $m;
    }

    function ida_department_name(mysqli $conn, int $dept_id): string
    {
        if ($dept_id <= 0) {
            return '';
        }
        $stmt = $conn->prepare('SELECT dept_name FROM department WHERE dept_id = ? LIMIT 1');
        if (!$stmt) {
            return '';
        }
        $stmt->bind_param('i', $dept_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (string) $row['dept_name'] : '';
    }

    function ida_upsert_appointment(
        mysqli $conn,
        int $inst_id,
        int $dept_id,
        string $appointment_status,
        int $instruction_hours
    ): bool {
        if (!ida_appointments_table_exists($conn) || $inst_id <= 0 || $dept_id <= 0) {
            return false;
        }
        $valid = ['Regular', 'Part-Time', 'Contractual'];
        if (!in_array($appointment_status, $valid, true)) {
            return false;
        }
        $stmt = $conn->prepare(
            'INSERT INTO instructor_department_appointment (inst_id, dept_id, appointment_status, instruction_hours)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE appointment_status = VALUES(appointment_status), instruction_hours = VALUES(instruction_hours)'
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('iisi', $inst_id, $dept_id, $appointment_status, $instruction_hours);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    function ida_delete_appointment(mysqli $conn, int $inst_id, int $dept_id): bool
    {
        if (!ida_appointments_table_exists($conn)) {
            return false;
        }
        $stmt = $conn->prepare(
            'DELETE FROM instructor_department_appointment WHERE inst_id = ? AND dept_id = ?'
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ii', $inst_id, $dept_id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Mirror instructor.inst_status and instruction_hours onto the primary department row.
     */
    function ida_sync_primary_appointment_from_instructor(mysqli $conn, int $inst_id): void
    {
        if (!ida_appointments_table_exists($conn) || $inst_id <= 0) {
            return;
        }
        $stmt = $conn->prepare(
            'SELECT dept_id, inst_status, instruction_hours FROM instructor WHERE inst_id = ? LIMIT 1'
        );
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('i', $inst_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row || empty($row['dept_id'])) {
            return;
        }
        ida_upsert_appointment(
            $conn,
            $inst_id,
            (int) $row['dept_id'],
            $row['inst_status'],
            (int) $row['instruction_hours']
        );
    }
}
