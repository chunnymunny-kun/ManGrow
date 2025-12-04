<?php
session_start();
require_once 'database.php';

// Authorization check
if (!isset($_SESSION['accessrole']) || !in_array($_SESSION['accessrole'], ['Administrator', 'Representative'])) {
    $_SESSION['response'] = [
        'status' => 'error',
        'msg' => 'Unauthorized access'
    ];
    header("Location: adminaccspage.php");
    exit;
}

// Get initial data (we'll use AJAX for filtered data)
$tempAccountsQuery = "SELECT * FROM tempaccs_archive ORDER BY date_deleted DESC";
$validAccountsQuery = "SELECT * FROM accounts_archive ORDER BY date_deleted DESC";

$tempAccountsResult = $connection->query($tempAccountsQuery);
$validAccountsResult = $connection->query($validAccountsQuery);

$tempAccounts = $tempAccountsResult->fetch_all(MYSQLI_ASSOC);
$validAccounts = $validAccountsResult->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Archived Accounts</title>
    <link rel="stylesheet" href="adminpage.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.css">
    <style>
        body {
            background-color: #EFE3C2;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .archive-container {
            max-width: 1400px;
            width:100%;
            padding:30px;
            margin:30px auto;
            min-height:600px;
            height:calc(100% - 60px);
            display:flex;
            flex-direction: column;
            background: #FFFDF6;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            box-sizing: border-box;
        }
        
        .archive-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eaeaea;
        }
        
        .archive-header h1 {
            color: #123524;
            font-weight: 600;
            margin: 0;
        }
        
        .archive-nav {
            display: flex;
            gap: 10px;
        }
        
        .archive-nav a {
            padding: 8px 20px;
            background: #e9ecef;
            border-radius: 6px;
            text-decoration: none;
            color: #495057;
            font-weight: 500;
            transition: all 0.2s;
            border: 1px solid #dee2e6;
            cursor: pointer;
        }
        
        .archive-nav a:hover {
            background: #dee2e6;
        }
        
        .archive-nav a.active {
            background: #123524;
            color: white;
            border-color: #368a63;
        }
        
        .archive-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 20px;
            font-size: 14px;
            background:#FFFDF6;
        }
        
        .archive-table thead tr th {
            background-color: #123524;
            color: azure;
            padding: 12px 15px;
            text-align: left;
            font-weight: 500;
            position: sticky;
            top: 0;
        }
        
        .archive-table th:first-child {
            border-top-left-radius: 8px;
        }
        
        .archive-table th:last-child {
            border-top-right-radius: 8px;
        }
        
        .archive-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eaeaea;
            vertical-align: middle;
        }
        
        .archive-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .archive-table tr:last-child td {
            border-bottom: none;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 50px;
            margin-bottom: 15px;
            color: #adb5bd;
        }
        
        #initial-empty-state {
            display: block;
        }
        
        #dynamic-empty-state {
            display: none;
            margin: 20px auto;
        }
        
        .badge-role {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            background-color: #e9ecef;
            color: #495057;
        }
        
        .date-cell {
            white-space: nowrap;
        }
        
        .back-button {
            margin-bottom: 20px;
        }
        
        .back-button a {
            display: inline-flex;
            align-items: center;
            padding: 8px 15px;
            background-color: #123524;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        
        .back-button a:hover {
            background-color: #0c2318;
        }
        
        .back-button i {
            margin-right: 8px;
        }
        
        /* For the JavaScript version */
        .table-container {
            display: none;
        }
        
        .active-table {
            display: block;
        }
        
        /* Filter styles */
        .filter-container {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #495057;
        }
        
        .filter-group input, 
        .filter-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .filter-actions {
            display: flex;
            align-items: flex-end;
            gap: 10px;
        }
        
        .filter-actions button {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
        }
        
        .filter-actions .apply-btn {
            background-color: #123524;
            color: white;
        }
        
        .filter-actions .reset-btn {
            background-color: #e9ecef;
            color: #495057;
        }
        
        .loading-indicator {
            display: none;
            text-align: center;
            padding: 15px;
            color: #6c757d;
        }
        
        .loading-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #123524;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            display: inline-block;
            vertical-align: middle;
            margin-right: 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Date range picker adjustments */
        .daterangepicker {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        #role-filter{
            height:auto;
        }
    </style>
</head>
<body>
    <div class="archive-container">
        <div class="back-button">
            <a href="adminaccspage.php">
                <i class="fas fa-arrow-left"></i> Back to Accounts
            </a>
        </div>
        
        <div class="archive-header">
            <h1>Archived Accounts</h1>
            
            <div class="archive-nav">
                <a href="#" class="archive-tab active" data-tab="valid">
                    <i class="fas fa-user-check"></i> Valid Accounts
                </a>
                <a href="#" class="archive-tab" data-tab="temp">
                    <i class="fas fa-user-clock"></i> Temporary Accounts
                </a>
            </div>
        </div>
        
        <!-- Filter Section -->
        <div class="filter-container">
            <div class="filter-group">
                <label for="search-input">Search</label>
                <input type="text" id="search-input" placeholder="Search by name, email, or organization...">
            </div>
            
            <div class="filter-group">
                <label for="date-range">Date Range</label>
                <input type="text" id="date-range" placeholder="Select date range" readonly>
            </div>
            
            <div class="filter-group">
                <label for="role-filter">Role</label>
                <select id="role-filter">
                    <option value="">All Roles</option>
                    <option value="Resident">Resident</option>
                    <option value="Barangay Official">Barangay Official</option>
                </select>
            </div>
            
            <div class="filter-actions">
                <button id="reset-filters" class="reset-btn">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </div>
        
        <div class="loading-indicator">
            <div class="loading-spinner"></div>
            Loading archived accounts...
        </div>
        
        <!-- Dynamic Empty State (will be moved to active tab when needed) -->
        <div class="empty-state" id="dynamic-empty-state" style="display: none;">
            <i class="fas fa-archive"></i>
            <h3 id="empty-state-title">No archived accounts found</h3>
            <p id="empty-state-message">There are currently no accounts in the archive.</p>
        </div>
        
        <!-- Valid Accounts Table -->
        <div id="valid-table" class="table-container active-table">
            <?php if (!empty($validAccounts)): ?>
                <div class="table-responsive">
                    <table class="archive-table">
                        <thead>
                            <tr>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Organization</th>
                                <th>Date Created</th>
                                <th>Date Archived</th>
                                <th>Archived By</th>
                            </tr>
                        </thead>
                        <tbody id="valid-accounts-body">
                            <?php foreach ($validAccounts as $account): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($account['fullname']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($account['email']); ?></td>
                                    <td><span class="badge-role"><?php echo htmlspecialchars($account['accessrole']); ?></span></td>
                                    <td><?php echo htmlspecialchars($account['organization']); ?></td>
                                    <td class="date-cell"><?php echo htmlspecialchars($account['date_created']); ?></td>
                                    <td class="date-cell"><?php echo htmlspecialchars($account['date_deleted']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($account['deleted_by']); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state" id="initial-empty-state">
                    <i class="fas fa-archive"></i>
                    <h3>No archived valid accounts found</h3>
                    <p>There are currently no valid accounts in the archive.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Temporary Accounts Table -->
        <div id="temp-table" class="table-container">
            <?php if (!empty($tempAccounts)): ?>
                <div class="table-responsive">
                    <table class="archive-table">
                        <thead>
                            <tr>
                                <th>First Name</th>
                                <th>Last Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Organization</th>
                                <th>Verification</th>
                                <th>Date Created</th>
                                <th>Date Archived</th>
                                <th>Archived By</th>
                            </tr>
                        </thead>
                        <tbody id="temp-accounts-body">
                            <?php foreach ($tempAccounts as $account): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($account['firstname']); ?></td>
                                    <td><?php echo htmlspecialchars($account['lastname']); ?></td>
                                    <td><?php echo htmlspecialchars($account['email']); ?></td>
                                    <td><span class="badge-role"><?php echo htmlspecialchars($account['accessrole']); ?></span></td>
                                    <td><?php echo htmlspecialchars($account['organization']); ?></td>
                                    <td>
                                        <span class="badge-role <?php echo $account['is_verified'] ? 'bg-success text-white' : 'bg-warning text-dark'; ?>">
                                            <?php echo $account['is_verified'] ? 'Verified' : 'Unverified'; ?>
                                        </span>
                                    </td>
                                    <td class="date-cell"><?php echo htmlspecialchars($account['date_created']); ?></td>
                                    <td class="date-cell"><?php echo htmlspecialchars($account['date_deleted']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($account['deleted_by']); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state" id="initial-empty-state">
                    <i class="fas fa-archive"></i>
                    <h3>No archived temporary accounts found</h3>
                    <p>There are currently no temporary accounts in the archive.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize date range picker
            $('#date-range').daterangepicker({
                autoUpdateInput: false,
                locale: {
                    cancelLabel: 'Clear',
                    format: 'YYYY-MM-DD'
                },
                opens: 'right'
            });

            $('#date-range').on('apply.daterangepicker', function(ev, picker) {
                $(this).val(picker.startDate.format('YYYY-MM-DD') + ' - ' + picker.endDate.format('YYYY-MM-DD'));
                loadArchivedAccounts();
            });

            $('#date-range').on('cancel.daterangepicker', function(ev, picker) {
                $(this).val('');
                loadArchivedAccounts();
            });

            // Tab switching
            $('.archive-tab').on('click', function(e) {
                e.preventDefault();
                
                $('.archive-tab').removeClass('active');
                $(this).addClass('active');
                
                const tabType = $(this).data('tab');
                $('.table-container').removeClass('active-table');
                $(`#${tabType}-table`).addClass('active-table');
                
                // Update URL without reloading
                const url = new URL(window.location);
                url.searchParams.set('type', tabType);
                window.history.pushState({}, '', url);
                
                // Load data for the active tab
                loadArchivedAccounts();
            });

            // Filter change events
            $('#search-input, #role-filter').on('input change', function() {
                loadArchivedAccounts();
            });

            // Reset filters
            $('#reset-filters').on('click', function() {
                $('#search-input').val('');
                $('#date-range').val('');
                $('#role-filter').val('');
                loadArchivedAccounts();
            });

            // Check URL parameter on load
            const urlParams = new URLSearchParams(window.location.search);
            const typeParam = urlParams.get('type');
            
            if (typeParam && typeParam !== 'valid') {
                // Activate the appropriate tab
                $(`.archive-tab[data-tab="${typeParam}"]`).click();
            }

            // Function to load archived accounts with filters
            function loadArchivedAccounts() {
                const activeTab = $('.archive-tab.active').data('tab');
                const searchTerm = $('#search-input').val().trim();
                const dateRange = $('#date-range').val();
                const roleFilter = $('#role-filter').val();
                
                let startDate = '';
                let endDate = '';
                
                if (dateRange) {
                    const dates = dateRange.split(' - ');
                    startDate = dates[0];
                    endDate = dates[1] || dates[0];
                }
                
                // Show loading indicator
                $('.loading-indicator').show();
                $(`#${activeTab}-table .table-responsive`).hide();
                $(`#${activeTab}-table .empty-state`).hide();
                $('#dynamic-empty-state').hide();
                
                $.ajax({
                    url: 'fetch_archived_accounts.php',
                    method: 'POST',
                    data: {
                        type: activeTab,
                        search: searchTerm,
                        start_date: startDate,
                        end_date: endDate,
                        role: roleFilter
                    },
                    dataType: 'json',
                    success: function(response) {
                        // Hide both the table and any existing empty states
                        $(`#${activeTab}-table .table-responsive`).hide();
                        $(`#${activeTab}-table .empty-state`).hide();
                        $('#dynamic-empty-state').hide();

                        if (response.success) {
                            const tbodyId = `${activeTab}-accounts-body`;
                            const $tbody = $(`#${tbodyId}`);
                            $tbody.empty();
                            
                            if (response.data.length > 0) {
                                // We have data - populate the table
                                if (activeTab === 'valid') {
                                    response.data.forEach(account => {
                                        $tbody.append(`
                                            <tr>
                                                <td><strong>${escapeHtml(account.fullname)}</strong></td>
                                                <td>${escapeHtml(account.email)}</td>
                                                <td><span class="badge-role">${escapeHtml(account.accessrole)}</span></td>
                                                <td>${escapeHtml(account.organization)}</td>
                                                <td class="date-cell">${escapeHtml(account.date_created)}</td>
                                                <td class="date-cell">${escapeHtml(account.date_deleted)}</td>
                                                <td><strong>${escapeHtml(account.deleted_by)}</strong></td>
                                            </tr>
                                        `);
                                    });
                                } else {
                                    response.data.forEach(account => {
                                        const verifiedClass = account.is_verified ? 'bg-success text-white' : 'bg-warning text-dark';
                                        const verifiedText = account.is_verified ? 'Verified' : 'Unverified';
                                        
                                        $tbody.append(`
                                            <tr>
                                                <td>${escapeHtml(account.firstname)}</td>
                                                <td>${escapeHtml(account.lastname)}</td>
                                                <td>${escapeHtml(account.email)}</td>
                                                <td><span class="badge-role">${escapeHtml(account.accessrole)}</span></td>
                                                <td>${escapeHtml(account.organization)}</td>
                                                <td><span class="badge-role ${verifiedClass}">${verifiedText}</span></td>
                                                <td class="date-cell">${escapeHtml(account.date_created)}</td>
                                                <td class="date-cell">${escapeHtml(account.date_deleted)}</td>
                                                <td><strong>${escapeHtml(account.deleted_by)}</strong></td>
                                            </tr>
                                        `);
                                    });
                                }
                                
                                $(`#${activeTab}-table .table-responsive`).show();
                            } else {
                                // No data - show appropriate message
                                const searchTerm = $('#search-input').val().trim();
                                const dateRange = $('#date-range').val();
                                const roleFilter = $('#role-filter').val();
                                
                                // Check if this is an initial load or filtered load
                                if (searchTerm || dateRange || roleFilter) {
                                    // Filtered load with no results
                                    $('#empty-state-title').text('No matching accounts found');
                                    $('#empty-state-message').text('No archived accounts match your current filters.');
                                } else {
                                    // Initial load with no data
                                    $('#empty-state-title').text(`No archived ${activeTab === 'valid' ? 'valid' : 'temporary'} accounts found`);
                                    $('#empty-state-message').text(`There are currently no ${activeTab === 'valid' ? 'valid' : 'temporary'} accounts in the archive.`);
                                }
                                
                                // Position the empty state in the active tab
                                $(`#${activeTab}-table`).append($('#dynamic-empty-state'));
                                $('#dynamic-empty-state').show();
                            }
                        } else {
                            alert('Error loading archived accounts: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Error loading archived accounts: ' + error);
                    },
                    complete: function() {
                        $('.loading-indicator').hide();
                    }
                });
            }
            
            // Helper function to escape HTML
            function escapeHtml(unsafe) {
                if (unsafe === null || unsafe === undefined) return '';
                return unsafe.toString()
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            }
        });
    </script>
</body>
</html>