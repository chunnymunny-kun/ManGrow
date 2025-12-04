<?php
include 'database.php';

$type = $_GET['type'] ?? 'imports';
$recordsPerPage = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $recordsPerPage;

// Base query
$query = "SELECT * FROM account_activitytbl WHERE action_type = ?";
$params = [ucfirst($type)];

// For pagination count
$countQuery = "SELECT COUNT(*) as total FROM account_activitytbl WHERE action_type = ?";

// Add filtering if needed
if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
    $query .= " AND activity_date >= ?";
    $countQuery .= " AND activity_date >= ?";
    $params[] = $_GET['date_from'];
}

if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
    $query .= " AND activity_date <= ?";
    $countQuery .= " AND activity_date <= ?";
    $params[] = $_GET['date_to'] . ' 23:59:59';
}

// Add sorting
$query .= " ORDER BY activity_date DESC LIMIT ? OFFSET ?";
$params[] = $recordsPerPage;
$params[] = $offset;

// Prepare and execute the query
$stmt = $connection->prepare($query);
$stmt->bind_param(str_repeat('s', count($params)), ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Get total count
$countStmt = $connection->prepare($countQuery);
$countStmt->bind_param(str_repeat('s', count($params) - 2), ...array_slice($params, 0, -2));
$countStmt->execute();
$totalResult = $countStmt->get_result();
$total = $totalResult->fetch_assoc()['total'];
$totalPages = ceil($total / $recordsPerPage);
?>

<div class="activity-filters mb-4">
    <form method="get" class="form-inline">
        <input type="hidden" name="type" value="<?= $type ?>">
        <div class="form-group mr-3">
            <label for="date_from" class="mr-2">From:</label>
            <input type="date" name="date_from" id="date_from" class="form-control" 
                   value="<?= $_GET['date_from'] ?? '' ?>">
        </div>
        <div class="form-group mr-3">
            <label for="date_to" class="mr-2">To:</label>
            <input type="date" name="date_to" id="date_to" class="form-control" 
                   value="<?= $_GET['date_to'] ?? '' ?>">
        </div>
        <button type="submit" class="btn btn-primary mr-2">Filter</button>
        <a href="?type=<?= $type ?>" class="btn btn-secondary">Reset</a>
    </form>
</div>

<table class="activity-details-table">
    <thead>
        <tr>
            <th>Date</th>
            <th>Action</th>
            <th>Details</th>
            <th>Account Type</th>
            <th>Performed By</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= date('M d, Y h:i A', strtotime($row['activity_date'])) ?></td>
                    <td><?= htmlspecialchars($row['action_type']) ?></td>
                    <td><?= htmlspecialchars($row['activity_details']) ?></td>
                    <td>
                        <span class="badge badge-<?= $row['affected_account_source'] === 'accountstbl' ? 'verified' : 'unverified' ?>">
                            <?= $row['affected_account_source'] === 'accountstbl' ? 'Verified' : 'Unverified' ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($row['performed_by']) ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="5" class="text-center">No <?= $type ?> activities found</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<div class="activity-pagination">
    <?php if ($page > 1): ?>
        <a href="?type=<?= $type ?>&page=<?= $page - 1 ?>" class="btn btn-sm btn-outline-primary mr-2">&laquo; Previous</a>
    <?php endif; ?>
    
    <span class="mx-2">Page <?= $page ?> of <?= $totalPages ?></span>
    
    <?php if ($page < $totalPages): ?>
        <a href="?type=<?= $type ?>&page=<?= $page + 1 ?>" class="btn btn-sm btn-outline-primary ml-2">Next &raquo;</a>
    <?php endif; ?>
</div>