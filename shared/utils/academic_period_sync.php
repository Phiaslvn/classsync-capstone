<?php
/**
 * Academic period auto-sync based on EVSU calendar.
 *
 * Rules:
 * - 1st Semester: August to December
 * - 2nd Semester: January to May
 * - Mid-Year: June to July
 */

/**
 * Resolve current academic period from current server date.
 *
 * @return array{sy_year:string, semester_long:string, semester_short:string, sy_name:string}
 */
function resolveCurrentAcademicPeriod() {
    $month = (int)date('n');
    $year = (int)date('Y');

    if ($month >= 8) {
        $startYear = $year;
        $semesterLong = '1st Semester';
        $semesterShort = '1st';
    } elseif ($month >= 1 && $month <= 5) {
        $startYear = $year - 1;
        $semesterLong = '2nd Semester';
        $semesterShort = '2nd';
    } else {
        $startYear = $year - 1;
        $semesterLong = 'Mid-Year';
        $semesterShort = 'Summer';
    }

    $syYear = $startYear . ' - ' . ($startYear + 1);

    return [
        'sy_year' => $syYear,
        'semester_long' => $semesterLong,
        'semester_short' => $semesterShort,
        'sy_name' => $syYear . ' - ' . $semesterLong
    ];
}

/**
 * Keep settings and active SY table aligned with current date.
 *
 * When $onlyIfUnset is true (recommended for page loads and read APIs), this does nothing
 * if active_school_year_id and active_semester are already set — manual admin choice wins.
 * When false, calendar rules always overwrite (use for opt-in "reset to calendar" or jobs).
 *
 * @param mysqli $conn
 * @param int|null $userId
 * @param bool $onlyIfUnset Skip writes when settings already have an active SY and semester
 * @return array{success:bool,changed:bool,message:string,data?:array|null}
 */
function syncActiveAcademicPeriod($conn, $userId = null, $onlyIfUnset = false) {
    if (!($conn instanceof mysqli)) {
        return ['success' => false, 'changed' => false, 'message' => 'Invalid database connection'];
    }

    if ($onlyIfUnset) {
        $currentSyId = 0;
        $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'active_school_year_id' LIMIT 1");
        if ($stmt) {
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row && $row['setting_value'] !== null && $row['setting_value'] !== '') {
                $currentSyId = (int)$row['setting_value'];
            }
        }
        $currentSemester = '';
        $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'active_semester' LIMIT 1");
        if ($stmt) {
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row && isset($row['setting_value'])) {
                $currentSemester = trim((string)$row['setting_value']);
            }
        }
        if ($currentSyId > 0 && $currentSemester !== '') {
            return [
                'success' => true,
                'changed' => false,
                'message' => 'Active academic period already configured.',
                'data' => null
            ];
        }
    }

    $period = resolveCurrentAcademicPeriod();

    try {
        $conn->begin_transaction();

        // Ensure matching schoolyear record exists.
        $stmt = $conn->prepare("
            SELECT sy_id
            FROM schoolyear
            WHERE sy_name = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $period['sy_name']);
        $stmt->execute();
        $existingSy = $stmt->get_result()->fetch_assoc();

        if ($existingSy) {
            $syId = (int)$existingSy['sy_id'];
        } else {
            $stmt = $conn->prepare("
                INSERT INTO schoolyear (sy_year, curr_def, sy_name)
                VALUES (?, 1, ?)
            ");
            $stmt->bind_param("ss", $period['sy_year'], $period['sy_name']);
            $stmt->execute();
            $syId = (int)$conn->insert_id;
        }

        // Read current settings to determine if writes are needed.
        $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'active_school_year_id' LIMIT 1");
        $stmt->execute();
        $currentSySetting = $stmt->get_result()->fetch_assoc();
        $currentSyId = $currentSySetting ? (int)$currentSySetting['setting_value'] : 0;

        $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'active_semester' LIMIT 1");
        $stmt->execute();
        $currentSemSetting = $stmt->get_result()->fetch_assoc();
        $currentSemester = $currentSemSetting ? (string)$currentSemSetting['setting_value'] : '';

        $needsSettingsUpdate = ($currentSyId !== $syId) || ($currentSemester !== $period['semester_long']);
        $changed = false;

        if ($needsSettingsUpdate) {
            $changed = true;
            $syIdStr = (string)$syId;
            $updatedBy = $userId ? (int)$userId : null;

            $stmt = $conn->prepare("
                INSERT INTO settings (setting_key, setting_value, updated_by, updated_at)
                VALUES ('active_school_year_id', ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    setting_value = VALUES(setting_value),
                    updated_by = VALUES(updated_by),
                    updated_at = NOW()
            ");
            $stmt->bind_param("si", $syIdStr, $updatedBy);
            $stmt->execute();

            $stmt = $conn->prepare("
                INSERT INTO settings (setting_key, setting_value, updated_by, updated_at)
                VALUES ('active_semester', ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    setting_value = VALUES(setting_value),
                    updated_by = VALUES(updated_by),
                    updated_at = NOW()
            ");
            $stmt->bind_param("si", $period['semester_long'], $updatedBy);
            $stmt->execute();
        }

        // Keep active_school_year_semester aligned for modules that still read it.
        $stmt = $conn->prepare("
            SELECT id
            FROM active_school_year_semester
            WHERE sy_id = ? AND semester = ?
            LIMIT 1
        ");
        $stmt->bind_param("is", $syId, $period['semester_short']);
        $stmt->execute();
        $activeRow = $stmt->get_result()->fetch_assoc();

        $stmt = $conn->prepare("UPDATE active_school_year_semester SET is_active = 0 WHERE is_active = 1");
        $stmt->execute();

        if ($activeRow) {
            $stmt = $conn->prepare("
                UPDATE active_school_year_semester
                SET is_active = 1, created_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("i", $activeRow['id']);
            $stmt->execute();
        } else {
            $changed = true;
            $stmt = $conn->prepare("
                INSERT INTO active_school_year_semester (sy_id, semester, is_active, created_at)
                VALUES (?, ?, 1, NOW())
            ");
            $stmt->bind_param("is", $syId, $period['semester_short']);
            $stmt->execute();
        }

        $conn->commit();

        return [
            'success' => true,
            'changed' => $changed,
            'message' => 'Academic period sync completed.',
            'data' => [
                'sy_id' => $syId,
                'sy_year' => $period['sy_year'],
                'semester' => $period['semester_long']
            ]
        ];
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'changed' => false, 'message' => $e->getMessage()];
    }
}
?>
