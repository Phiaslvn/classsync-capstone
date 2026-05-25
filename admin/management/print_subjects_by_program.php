<?php
/**
 * Official printable subject list for one program (full data from DB, not DataTables DOM).
 * Optional query params: curr_id, term, search — same meaning as subject filters on the dashboard.
 */

session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth/security_middleware.php';

$hasPermission = hasPermission('manage_subjects')
    || hasPermission('view_subjects')
    || hasPermission('manage_curriculum');

if (!$hasPermission) {
    http_response_code(403);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Unauthorized</title></head><body><p>Unauthorized.</p></body></html>';
    exit;
}

$programId = (int) ($_GET['program_id'] ?? 0);
if ($programId <= 0) {
    http_response_code(400);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Bad request</title></head><body><p>Missing or invalid program_id.</p></body></html>';
    exit;
}

$userInfo = getUserInfo();
$userDeptId = $userInfo ? (int) $userInfo['dept_id'] : 0;
$isAdminSupport = function_exists('isAdminSupport') ? isAdminSupport() : false;

$stmtProg = $conn->prepare(
    'SELECT program_id, program_code, program_name, dept_id FROM program WHERE program_id = ? AND program_status = ? LIMIT 1'
);
$active = 'Active';
$stmtProg->bind_param('is', $programId, $active);
$stmtProg->execute();
$resProg = $stmtProg->get_result();
$program = $resProg->fetch_assoc();
$stmtProg->close();

if (!$program) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Not found</title></head><body><p>Program not found.</p></body></html>';
    exit;
}

if (!$isAdminSupport && $userDeptId > 0 && (int) $program['dept_id'] !== $userDeptId) {
    http_response_code(403);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Forbidden</title></head><body><p>You cannot print subjects for this program.</p></body></html>';
    exit;
}

$currId = (int) ($_GET['curr_id'] ?? 0);
$termRaw = $_GET['term'] ?? '';
$term = ($termRaw === '' || $termRaw === null) ? null : (int) $termRaw;
$search = trim((string) ($_GET['search'] ?? ''));

$where = [];
$params = [];
$types = '';

if (!$isAdminSupport && $userDeptId > 0) {
    $where[] = 's.dept_id = ?';
    $params[] = $userDeptId;
    $types .= 'i';
}

$where[] = 's.program_id = ?';
$params[] = $programId;
$types .= 'i';

if ($currId > 0) {
    $where[] = 's.curr_id = ?';
    $params[] = $currId;
    $types .= 'i';
}
if ($term !== null && $termRaw !== '') {
    $where[] = 's.subj_term = ?';
    $params[] = $term;
    $types .= 'i';
}
if ($search !== '') {
    $like = '%' . $search . '%';
    $where[] = '(s.subj_code LIKE ? OR s.subj_desc LIKE ? OR c.curr_name LIKE ?)';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

$sql = '
    SELECT s.subj_code, s.subj_desc, s.subj_lec, s.subj_lab, s.subj_unit,
           s.subj_lvl, s.subj_term
    FROM subject s
    LEFT JOIN curriculum c ON s.curr_id = c.curr_id
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY s.subj_lvl ASC, s.subj_term ASC, s.subj_code ASC
';

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Error</title></head><body><p>Database error.</p></body></html>';
    exit;
}
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$rows = [];
while ($r = $result->fetch_assoc()) {
    $rows[] = $r;
}
$stmt->close();

function esc($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function year_label($lvl)
{
    $n = (int) $lvl;
    $map = [1 => '1st Year', 2 => '2nd Year', 3 => '3rd Year', 4 => '4th Year', 5 => '5th Year'];
    return $map[$n] ?? ($n . 'th Year');
}

function term_label($t)
{
    $n = (int) $t;
    $map = [1 => '1st Term', 2 => '2nd Term', 3 => 'Summer'];
    return $map[$n] ?? ('Term ' . $n);
}

$programTitle = esc(
    trim(
        $program['program_code'] . ' - ' . $program['program_name'],
        ' -'
    )
);
$generated = (new DateTimeImmutable('now'))->format('F j, Y g:i A');

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Subject list — <?php echo esc($program['program_code']); ?></title>
    <style>
        @page { margin: 1.2cm; size: letter; }
        body { font-family: 'Times New Roman', Times, serif; font-size: 11pt; color: #000; margin: 0; padding: 12px; }
        .hdr { text-align: center; border-bottom: 3px solid #800000; padding-bottom: 12px; margin-bottom: 14px; }
        .hdr h1 { font-size: 14pt; color: #800000; margin: 0 0 4px 0; text-transform: uppercase; }
        .hdr .sub { font-size: 10pt; color: #444; }
        .meta { font-size: 10pt; color: #333; margin-bottom: 14px; line-height: 1.5; }
        .meta strong { color: #800000; }
        .section { margin-top: 16px; page-break-inside: avoid; }
        .section h2 { font-size: 12pt; color: #800000; margin: 0 0 8px 0; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 10pt; }
        th { background: #800000; color: #fff; padding: 8px 6px; text-align: left; border: 1px solid #660000; }
        th.num { text-align: center; width: 44px; }
        td { padding: 6px; border: 1px solid #ccc; vertical-align: top; }
        td.num { text-align: center; }
        tr:nth-child(even) td { background: #f9f9f9; }
        tfoot td { font-weight: bold; background: #eee; }
        .foot { margin-top: 20px; padding-top: 10px; border-top: 2px solid #800000; font-size: 9pt; color: #555; text-align: center; }
        @media print {
            body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="hdr">
        <h1>Eastern Visayas State University — Ormoc City Campus</h1>
        <div class="sub">Official subject listing (by program)</div>
    </div>
    <div class="meta">
        <strong>Program:</strong> <?php echo $programTitle; ?> &nbsp;|&nbsp;
        <strong>Generated:</strong> <?php echo esc($generated); ?>
        <?php
        $currLabel = '';
        if ($currId > 0) {
            $cs = $conn->prepare('SELECT curr_name FROM curriculum WHERE curr_id = ? LIMIT 1');
            if ($cs) {
                $cs->bind_param('i', $currId);
                $cs->execute();
                $cr = $cs->get_result()->fetch_assoc();
                $cs->close();
                $currLabel = $cr && !empty($cr['curr_name']) ? $cr['curr_name'] : ('ID ' . $currId);
            }
        }
        ?>
        <?php if ($currId > 0) : ?>
            &nbsp;|&nbsp; <strong>Curriculum:</strong> <?php echo esc($currLabel); ?>
        <?php endif; ?>
        <?php if ($term !== null && $termRaw !== '') : ?>
            &nbsp;|&nbsp; <strong>Term:</strong> <?php echo esc(term_label($term)); ?>
        <?php endif; ?>
        <?php if ($search !== '') : ?>
            &nbsp;|&nbsp; <strong>Search:</strong> <?php echo esc($search); ?>
        <?php endif; ?>
        <br><strong>Records:</strong> <?php echo count($rows); ?>
    </div>

<?php
if (count($rows) === 0) :
    ?>
    <p>No subjects match the selected program and filters.</p>
<?php
else :
    $byYearTerm = [];
    foreach ($rows as $r) {
        $yl = (int) $r['subj_lvl'];
        $tm = (int) $r['subj_term'];
        $key = $yl . '_' . $tm;
        if (!isset($byYearTerm[$key])) {
            $byYearTerm[$key] = ['lvl' => $yl, 'term' => $tm, 'rows' => []];
        }
        $byYearTerm[$key]['rows'][] = $r;
    }
    ksort($byYearTerm, SORT_NATURAL);
    foreach ($byYearTerm as $block) :
        $yl = $block['lvl'];
        $tm = $block['term'];
        $sub = $block['rows'];
        $lec = 0;
        $lab = 0;
        $unit = 0;
        foreach ($sub as $r) {
            $lec += (float) $r['subj_lec'];
            $lab += (float) $r['subj_lab'];
            $unit += (float) $r['subj_unit'];
        }
        ?>
    <div class="section">
        <h2><?php echo esc(year_label($yl)); ?> — <?php echo esc(term_label($tm)); ?></h2>
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Descriptive title</th>
                    <th class="num">Lec</th>
                    <th class="num">Lab</th>
                    <th class="num">Units</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($sub as $r) : ?>
                <tr>
                    <td><strong><?php echo esc($r['subj_code']); ?></strong></td>
                    <td><?php echo esc($r['subj_desc']); ?></td>
                    <td class="num"><?php echo esc($r['subj_lec']); ?></td>
                    <td class="num"><?php echo esc($r['subj_lab']); ?></td>
                    <td class="num"><?php echo esc($r['subj_unit']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" style="text-align:right">Section totals</td>
                    <td class="num"><?php echo esc($lec); ?></td>
                    <td class="num"><?php echo esc($lab); ?></td>
                    <td class="num"><?php echo esc($unit); ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
        <?php
    endforeach;
endif;
?>

    <div class="foot">
        EVSU-OCC Scheduling System — official data export. Totals shown per year/term section.
    </div>
    <script>
        window.onload = function () { window.print(); };
    </script>
</body>
</html>
