<?php
/**
 * program_year_level_curriculum helpers: optional per-school-year (sy_id) scope.
 * Legacy rows use sy_id IS NULL and remain the fallback when no row exists for the active SY.
 */

function pylcurriculum_table_exists(mysqli $conn): bool {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $r = $conn->query("SHOW TABLES LIKE 'program_year_level_curriculum'");
    $cache = $r && $r->num_rows > 0;
    if ($r) {
        $r->close();
    }
    return $cache;
}

function pylcurriculum_has_sy_id_column(mysqli $conn): bool {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    if (!pylcurriculum_table_exists($conn)) {
        $cache = false;
        return false;
    }
    $r = $conn->query("SHOW COLUMNS FROM program_year_level_curriculum LIKE 'sy_id'");
    $cache = $r && $r->num_rows > 0;
    if ($r) {
        $r->close();
    }
    return $cache;
}

function get_active_school_year_id_from_settings(mysqli $conn): ?int {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'active_school_year_id' LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row || $row['setting_value'] === '' || $row['setting_value'] === null) {
        return null;
    }
    $id = (int)$row['setting_value'];
    return $id > 0 ? $id : null;
}

/**
 * Pick curr_id for program + year level: active sy_id row first, then legacy (sy_id NULL).
 */
function pylcurriculum_get_curr_id(mysqli $conn, int $programId, int $yearLevel): ?int {
    if (!pylcurriculum_table_exists($conn)) {
        return null;
    }
    if (!pylcurriculum_has_sy_id_column($conn)) {
        $st = $conn->prepare("SELECT curr_id FROM program_year_level_curriculum WHERE program_id = ? AND year_level = ? LIMIT 1");
        $st->bind_param("ii", $programId, $yearLevel);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ? (int)$row['curr_id'] : null;
    }
    $activeSy = get_active_school_year_id_from_settings($conn);
    if ($activeSy !== null) {
        $st = $conn->prepare("SELECT curr_id FROM program_year_level_curriculum WHERE program_id = ? AND year_level = ? AND sy_id = ? LIMIT 1");
        $st->bind_param("iii", $programId, $yearLevel, $activeSy);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if ($row) {
            return (int)$row['curr_id'];
        }
    }
    $st = $conn->prepare("SELECT curr_id FROM program_year_level_curriculum WHERE program_id = ? AND year_level = ? AND sy_id IS NULL LIMIT 1");
    $st->bind_param("ii", $programId, $yearLevel);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ? (int)$row['curr_id'] : null;
}

/**
 * Like pylcurriculum_get_curr_id but prefers a specific schoolyear id (e.g. diagnostics / copied section analysis).
 * Falls back to legacy sy_id IS NULL when no row exists for that sy_id.
 */
function pylcurriculum_get_curr_id_for_sy(mysqli $conn, int $programId, int $yearLevel, ?int $preferSyId): ?int {
    if (!pylcurriculum_table_exists($conn)) {
        return null;
    }
    if (!pylcurriculum_has_sy_id_column($conn)) {
        $st = $conn->prepare("SELECT curr_id FROM program_year_level_curriculum WHERE program_id = ? AND year_level = ? LIMIT 1");
        $st->bind_param("ii", $programId, $yearLevel);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ? (int)$row['curr_id'] : null;
    }
    if ($preferSyId !== null && $preferSyId > 0) {
        $st = $conn->prepare("SELECT curr_id FROM program_year_level_curriculum WHERE program_id = ? AND year_level = ? AND sy_id = ? LIMIT 1");
        $st->bind_param("iii", $programId, $yearLevel, $preferSyId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if ($row) {
            return (int)$row['curr_id'];
        }
    }
    $st = $conn->prepare("SELECT curr_id FROM program_year_level_curriculum WHERE program_id = ? AND year_level = ? AND sy_id IS NULL LIMIT 1");
    $st->bind_param("ii", $programId, $yearLevel);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ? (int)$row['curr_id'] : null;
}

/**
 * For each year level 1–5, pick curr_id from mapping rows (same precedence as pylcurriculum_get_curr_id).
 *
 * @return array<int,int> year_level => curr_id
 */
function pylcurriculum_pick_curr_ids_by_level(mysqli $conn, int $programId): array {
    $out = [];
    if (!pylcurriculum_table_exists($conn)) {
        return $out;
    }
    if (!pylcurriculum_has_sy_id_column($conn)) {
        $st = $conn->prepare("SELECT year_level, curr_id FROM program_year_level_curriculum WHERE program_id = ?");
        $st->bind_param("i", $programId);
        $st->execute();
        $res = $st->get_result();
        while ($row = $res->fetch_assoc()) {
            $out[(int)$row['year_level']] = (int)$row['curr_id'];
        }
        $st->close();
        return $out;
    }
    $st = $conn->prepare("SELECT year_level, curr_id, sy_id FROM program_year_level_curriculum WHERE program_id = ?");
    $st->bind_param("i", $programId);
    $st->execute();
    $res = $st->get_result();
    $byLevel = [];
    while ($row = $res->fetch_assoc()) {
        $lvl = (int)$row['year_level'];
        $byLevel[$lvl][] = [
            'curr_id' => (int)$row['curr_id'],
            'sy_id' => $row['sy_id'] !== null ? (int)$row['sy_id'] : null
        ];
    }
    $st->close();
    $activeSy = get_active_school_year_id_from_settings($conn);
    for ($level = 1; $level <= 5; $level++) {
        if (empty($byLevel[$level])) {
            continue;
        }
        $chosen = null;
        if ($activeSy !== null) {
            foreach ($byLevel[$level] as $cand) {
                if ($cand['sy_id'] !== null && $cand['sy_id'] === $activeSy) {
                    $chosen = $cand;
                    break;
                }
            }
        }
        if ($chosen === null) {
            foreach ($byLevel[$level] as $cand) {
                if ($cand['sy_id'] === null) {
                    $chosen = $cand;
                    break;
                }
            }
        }
        if ($chosen === null) {
            $chosen = $byLevel[$level][0];
        }
        $out[$level] = $chosen['curr_id'];
    }
    return $out;
}

/**
 * Upsert one mapping row: scoped to active sy_id when column exists; otherwise legacy ON DUPLICATE KEY path.
 */
function pylcurriculum_upsert_mapping(mysqli $conn, int $programId, int $yearLevel, int $currId): bool {
    if (!pylcurriculum_has_sy_id_column($conn)) {
        $q = "INSERT INTO program_year_level_curriculum (program_id, year_level, curr_id) 
              VALUES (?, ?, ?)
              ON DUPLICATE KEY UPDATE curr_id = VALUES(curr_id), updated_at = CURRENT_TIMESTAMP";
        $st = $conn->prepare($q);
        $st->bind_param("iii", $programId, $yearLevel, $currId);
        $ok = $st->execute();
        $st->close();
        return $ok;
    }
    $activeSy = get_active_school_year_id_from_settings($conn);
    if ($activeSy === null) {
        $st = $conn->prepare("SELECT id FROM program_year_level_curriculum WHERE program_id = ? AND year_level = ? AND sy_id IS NULL LIMIT 1");
        $st->bind_param("ii", $programId, $yearLevel);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if ($row) {
            $id = (int)$row['id'];
            $u = $conn->prepare("UPDATE program_year_level_curriculum SET curr_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $u->bind_param("ii", $currId, $id);
            $ok = $u->execute();
            $u->close();
            return $ok;
        }
        $ins = $conn->prepare("INSERT INTO program_year_level_curriculum (program_id, year_level, curr_id, sy_id) VALUES (?, ?, ?, NULL)");
        $ins->bind_param("iii", $programId, $yearLevel, $currId);
        $ok = $ins->execute();
        $ins->close();
        return $ok;
    }
    $st = $conn->prepare("SELECT id FROM program_year_level_curriculum WHERE program_id = ? AND year_level = ? AND sy_id = ? LIMIT 1");
    $st->bind_param("iii", $programId, $yearLevel, $activeSy);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if ($row) {
        $id = (int)$row['id'];
        $u = $conn->prepare("UPDATE program_year_level_curriculum SET curr_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $u->bind_param("ii", $currId, $id);
        $ok = $u->execute();
        $u->close();
        return $ok;
    }
    $ins = $conn->prepare("INSERT INTO program_year_level_curriculum (program_id, year_level, curr_id, sy_id) VALUES (?, ?, ?, ?)");
    $ins->bind_param("iiii", $programId, $yearLevel, $currId, $activeSy);
    $ok = $ins->execute();
    $ins->close();
    return $ok;
}
