<?php
function paginateQuery($conn, $baseQuery, $perPage = 10, $params = []) {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $search = trim($_GET['search'] ?? '');

    $offset = ($page - 1) * $perPage;

    if (!empty($search)) {
        $searchPattern = '%' . $search . '%';
        $where = " WHERE a.fname LIKE ? OR a.lname LIKE ? OR a.acc_user LIKE ? OR r.role_name LIKE ? OR d.dept_name LIKE ?";
        $bindParams = array_fill(0, 5, $searchPattern);
        $bindTypes = str_repeat('s', 5);

        $countQuery = "SELECT COUNT(*) as total FROM ($baseQuery $where) as sub";
        $stmt = $conn->prepare($countQuery);
        $stmt->bind_param($bindTypes, ...$bindParams);
        $stmt->execute();
        $totalRows = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        $dataQuery = "$baseQuery $where LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($dataQuery);
        $stmt->bind_param($bindTypes . 'ii', ...array_merge($bindParams, [$perPage, $offset]));
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    } else {
        $countQuery = "SELECT COUNT(*) as total FROM ($baseQuery) as sub";
        $totalResult = $conn->query($countQuery);
        $totalRows = $totalResult->fetch_assoc()['total'];
        $totalResult->free();

        $stmt = $conn->prepare("$baseQuery LIMIT ? OFFSET ?");
        $stmt->bind_param('ii', $perPage, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    }

    $totalPages = max(1, (int) ceil($totalRows / $perPage));

    return [
        'result' => $result,
        'page' => $page,
        'totalPages' => $totalPages,
        'search' => $search
    ];
}

function renderPagination($page, $totalPages, $baseUrl = 'dashboard.php') {
    if ($totalPages <= 1) return; // no pagination needed

    // Preserve other GET params (search, tab, etc.)
    $queryString = $_GET;
    unset($queryString['page']); // remove current page so we can replace it later

    echo '<nav><ul class="pagination justify-content-center">';

    // Previous button
    $prevPage = $page - 1;
    $disabled = ($page <= 1) ? 'disabled' : '';
    $queryString['page'] = $prevPage;
    $prevLink = $baseUrl . '?' . http_build_query($queryString);
    echo "<li class='page-item $disabled'><a class='page-link' href='$prevLink'>Previous</a></li>";

    // Numbered pages
    for ($i = 1; $i <= $totalPages; $i++) {
        $active = ($i == $page) ? 'active' : '';
        $queryString['page'] = $i;
        $pageLink = $baseUrl . '?' . http_build_query($queryString);
        echo "<li class='page-item $active'><a class='page-link' href='$pageLink'>$i</a></li>";
    }

    // Next button
    $nextPage = $page + 1;
    $disabled = ($page >= $totalPages) ? 'disabled' : '';
    $queryString['page'] = $nextPage;
    $nextLink = $baseUrl . '?' . http_build_query($queryString);
    echo "<li class='page-item $disabled'><a class='page-link' href='$nextLink'>Next</a></li>";

    echo '</ul></nav>';
}

