<?php
// Ensure no output is sent before headers
ob_start();

require 'vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

// Set headers first
header('Content-Type: application/json');
session_start();

try {
    // Validate authorization
    if (!isset($_SESSION["accessrole"]) || 
        !in_array($_SESSION["accessrole"], ['Barangay Official', 'Administrator', 'Representative'])) {
        throw new Exception('Unauthorized access', 403);
    }

    // Get input data
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input', 400);
    }

    $reportIds = $data['reportIds'] ?? [];
    $reportType = $data['reportType'] ?? '';
    $reportTypeName = $data['reportTypeName'] ?? '';
    $dateRange = $data['dateRange'] ?? '';

    if (empty($reportIds)) {
        throw new Exception('No reports selected', 400);
    }

    require 'database.php';

    // Function to format species display
    function formatSpeciesDisplay($speciesData) {
        if (empty($speciesData)) return 'Not specified';
        
        $speciesMap = [
            'Rhizophora Apiculata' => 'Bakawan Lalake',
            'Rhizophora Mucronata' => 'Bakawan Babae',
            'Avicennia Marina' => 'Bungalon',
            'Sonneratia Alba' => 'Palapat'
        ];
        
        // Check if it's a JSON string (new multiple species format)
        $decoded = json_decode($speciesData, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            // Convert scientific names to common names for display
            $displayNames = array_map(function($species) use ($speciesMap) {
                return isset($speciesMap[trim($species)]) ? $speciesMap[trim($species)] : trim($species);
            }, $decoded);
            
            return implode(', ', $displayNames);
        }
        
        // Check if it's a comma-separated string (multiple species stored as string)
        if (strpos($speciesData, ',') !== false) {
            $speciesArray = explode(',', $speciesData);
            $displayNames = array_map(function($species) use ($speciesMap) {
                $trimmed = trim($species);
                return isset($speciesMap[$trimmed]) ? $speciesMap[$trimmed] : $trimmed;
            }, $speciesArray);
            
            return implode(', ', $displayNames);
        }
        
        // Handle single species
        return isset($speciesMap[trim($speciesData)]) ? $speciesMap[trim($speciesData)] : trim($speciesData);
    }

    // Prepare placeholders for IN clause
    $placeholders = implode(',', array_fill(0, count($reportIds), '?'));

    // Query reports based on type
    if ($reportType === 'mangrove') {
        $query = "SELECT
                    m.report_id,
                    m.species,
                    m.area_no,
                    m.city_municipality,
                    m.barangays,
                    m.mangrove_status,
                    m.report_date,
                    m.latitude,
                    m.longitude,
                    m.area_m2,
                    m.priority,
                    m.remarks,
                    rn.action_type as status,
                    rn.notif_description
                FROM
                    mangrovereporttbl AS m
                LEFT JOIN (
                    SELECT
                        report_id,
                        action_type,
                        notif_description,
                        ROW_NUMBER() OVER (PARTITION BY report_id ORDER BY notif_date DESC) as rn
                    FROM
                        report_notifstbl
                ) AS rn ON m.report_id = rn.report_id AND rn.rn = 1
                WHERE
                    m.report_id IN ($placeholders)
                ORDER BY
                    m.report_date DESC";
    } else {
        $query = "SELECT
                    i.report_id,
                    i.incident_type,
                    i.area_no,
                    i.city_municipality,
                    i.barangays,
                    i.latitude,
                    i.longitude,
                    i.priority,
                    i.report_date,
                    i.description,
                    rn.action_type as status,
                    rn.notif_description
                FROM
                    illegalreportstbl AS i
                LEFT JOIN (
                    SELECT
                        report_id,
                        action_type,
                        notif_description,
                        ROW_NUMBER() OVER (PARTITION BY report_id ORDER BY notif_date DESC) as rn
                    FROM
                        report_notifstbl
                ) AS rn ON i.report_id = rn.report_id AND rn.rn = 1
                WHERE
                    i.report_id IN ($placeholders)
                ORDER BY
                    i.report_date DESC";
    }

    // Prepare and execute statement
    $stmt = $connection->prepare($query);
    
    if (!$stmt) {
        throw new Exception('Database query preparation failed: ' . $connection->error);
    }

    // Bind parameters
    $types = str_repeat('s', count($reportIds));
    $stmt->bind_param($types, ...$reportIds);
    
    if (!$stmt->execute()) {
        throw new Exception('Database query execution failed: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $reports = $result->fetch_all(MYSQLI_ASSOC);

    // Generate HTML for PDF
    $html = generatePdfHtml($reports, $reportTypeName, $dateRange);

    // Configure DomPDF
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isPhpEnabled', true);
    
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Generate unique filename
    $filename = 'reports_' . date('Ymd_His') . '.pdf';
    $filepath = __DIR__ . '/generated_pdfs/' . $filename;
    
    // Ensure directory exists
    if (!file_exists(__DIR__ . '/generated_pdfs')) {
        if (!mkdir(__DIR__ . '/generated_pdfs', 0755, true)) {
            throw new Exception('Failed to create PDF directory');
        }
    }
    
    // Save PDF to server
    if (file_put_contents($filepath, $dompdf->output()) === false) {
        throw new Exception('Failed to save PDF file');
    }
    
    // Clear any output buffer
    ob_end_clean();
    
    // Return download link
    echo json_encode([
        'success' => true,
        'downloadUrl' => 'download_pdf.php?file=' . urlencode($filename),
        'filename' => $filename
    ]);

} catch (Exception $e) {
    // Clean any output buffer before sending error
    ob_end_clean();
    
    http_response_code($e->getCode() ?: 500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}

function generatePdfHtml($reports, $reportTypeName, $dateRange) {
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Reports Compilation</title>
        <style>
            @page {
                margin: 1cm;
                size: A4;
            }
            body { 
                font-family: Arial, sans-serif; 
                margin: 0;
                padding: 0;
                color: #333;
                font-size: 11pt;
                line-height: 1.4;
            }
            .header {
                text-align: center;
                margin-bottom: 15px;
                padding-bottom: 10px;
                border-bottom: 2px solid #2c3e50;
            }
            .header h1 {
                color: #2c3e50;
                margin: 0 0 5px 0;
                font-size: 16pt;
                font-weight: bold;
            }
            .header-info {
                font-size: 10pt;
                color: #555;
                margin-bottom: 5px;
            }
            .header-info div {
                display: inline-block;
                margin: 0 15px;
            }
            .report-container {
                margin-bottom: 20px;
                border: 1px solid #ddd;
                border-radius: 5px;
                page-break-inside: avoid;
                background: white;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            .report-header {
                background-color: #2c3e50;
                color: white;
                padding: 8px 12px;
                border-bottom: 1px solid #ddd;
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-size: 11pt;
            }
            .report-id {
                font-weight: bold;
            }
            .report-date {
                color: #f8f8f8;
            }
            .report-content {
                padding: 12px;
            }
            .report-title {
                color: #2c3e50;
                font-size: 14pt;
                margin: 0 0 12px 0;
                padding-bottom: 6px;
                border-bottom: 1px solid #eee;
                font-weight: bold;
            }
            .details-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 12px;
                font-size: 10.5pt;
            }
            .details-table th {
                text-align: left;
                padding: 6px 8px;
                background-color: #f5f5f5;
                border: 1px solid #ddd;
                width: 30%;
            }
            .details-table td {
                padding: 6px 8px;
                border: 1px solid #ddd;
            }
            .status-section {
                margin-top: 12px;
                padding-top: 12px;
                border-top: 1px solid #eee;
                font-size: 10.5pt;
            }
            .current-status {
                display: flex;
                align-items: center;
                margin-bottom: 8px;
            }
            .status-label {
                font-weight: bold;
                margin-right: 10px;
                min-width: 80px;
            }
            .status-badge {
                display: inline-block;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 10pt;
                font-weight: bold;
            }
            .status-received { background-color: #3498db; color: white; }
            .status-investigating { background-color: #f39c12; color: white; }
            .status-action-taken { background-color: #2ecc71; color: white; }
            .status-resolved { background-color: #27ae60; color: white; }
            .status-rejected { background-color: #e74c3c; color: white; }
            .status-history {
                margin-top: 10px;
            }
            .status-history h4 {
                margin: 8px 0;
                color: #2c3e50;
                font-size: 11pt;
                font-weight: bold;
            }
            .status-item {
                margin-bottom: 8px;
                padding-bottom: 8px;
                border-bottom: 1px dashed #eee;
            }
            .status-item:last-child {
                border-bottom: none;
            }
            .status-header {
                display: flex;
                justify-content: space-between;
                margin-bottom: 4px;
            }
            .status-notifier {
                font-size: 9.5pt;
                color: #777;
                margin-bottom: 3px;
            }
            .status-description {
                font-size: 10pt;
                line-height: 1.4;
                padding: 5px;
                background-color: #f9f9f9;
                border-radius: 3px;
            }
            .footer {
                margin-top: 15px;
                text-align: right;
                font-size: 9.5pt;
                color: #777;
                border-top: 1px solid #eee;
                padding-top: 8px;
            }
            .map-coordinates {
                font-size: 9pt;
                color: #777;
                font-style: italic;
            }
            .remarks-cell {
                white-space: pre-wrap;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>REPORTS COMPILATION</h1>
            <div class="header-info">
                <div><strong>Report Type:</strong> ' . htmlspecialchars($reportTypeName) . '</div>
                <div><strong>Date Range:</strong> ' . htmlspecialchars($dateRange) . '</div>
                <div><strong>Generated On:</strong> ' . (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('F j, Y g:i A') . '</div>
            </div>
        </div>';

    foreach ($reports as $report) {
        $statusClass = 'status-' . strtolower(str_replace(' ', '-', $report['status'] ?? 'received'));
        $dt = new DateTime($report['report_date'], new DateTimeZone('Asia/Manila'));
        $reportDate = $dt->format('M j, Y g:i A');
        
        $html .= '
        <div class="report-container">
            <div class="report-header">
                <span class="report-id">REPORT #' . htmlspecialchars($report['report_id']) . '</span>
                <span class="report-date">' . $reportDate . '</span>
            </div>
            <div class="report-content">
                <div class="report-title">' . 
                (isset($report['species']) ? 'MANGROVE REPORT' : 'INCIDENT REPORT') . '</div>';
        
        if (isset($report['species'])) {
            // Mangrove report table
            $html .= '
                <table class="details-table">
                    <tr>
                        <th>Species</th>
                        <td>' . htmlspecialchars(formatSpeciesDisplay($report['species'])) . '</td>
                        <th>Location</th>
                        <td>' . htmlspecialchars($report['city_municipality']) . '</td>
                    </tr>
                    <tr>
                        <th>Barangay(s) / Nearest Barangays</th>
                        <td>
                            ' . htmlspecialchars($report['barangays']) . '
                            <div class="map-coordinates">
                                Nearest to coordinates: ' . htmlspecialchars(round($report['latitude'], 6)) . ', ' . htmlspecialchars(round($report['longitude'], 6)) . '
                            </div>
                        </td>
                        <th>Area Number</th>
                        <td>' . htmlspecialchars($report['area_no']) . '</td>
                    </tr>
                    <tr>
                        <th>Mangrove Status</th>
                        <td>' . htmlspecialchars($report['mangrove_status']) . '</td>
                        <th>Area Size</th>
                        <td>' . (isset($report['area_m2']) ? number_format($report['area_m2']) . ' m²' : 'N/A') . '</td>
                    </tr>
                    <tr>
                        <th>Priority</th>
                        <td>' . htmlspecialchars($report['priority']) . '</td>
                        <th>Coordinates</th>
                        <td>
                            ' . htmlspecialchars(round($report['latitude'], 6)) . ', ' . 
                            htmlspecialchars(round($report['longitude'], 6)) . '
                            <div class="map-coordinates">(Latitude, Longitude)</div>
                        </td>
                    </tr>
                    <tr>
                        <th>Remarks</th>
                        <td colspan="3" class="remarks-cell">' . 
                            (isset($report['remarks']) ? nl2br(htmlspecialchars($report['remarks'])) : 'No remarks provided') . '
                        </td>
                    </tr>
                </table>';
        } else {
            // Incident report table
            $html .= '
                <table class="details-table">
                    <tr>
                        <th>Incident Type</th>
                        <td>' . htmlspecialchars($report['incident_type']) . '</td>
                        <th>Location</th>
                        <td>' . htmlspecialchars($report['city_municipality']) . '</td>
                    </tr>
                    <tr>
                        <th>Barangay(s)</th>
                        <td>' . htmlspecialchars($report['barangays']) . '</td>
                        <th>Area Number</th>
                        <td>' . htmlspecialchars($report['area_no']) . '</td>
                    </tr>
                    <tr>
                        <th>Priority</th>
                        <td>' . htmlspecialchars($report['priority']) . '</td>
                        <th>Coordinates</th>
                        <td>
                            ' . htmlspecialchars(round($report['latitude'], 6)) . ', ' . 
                            htmlspecialchars(round($report['longitude'], 6)) . '
                            <div class="map-coordinates">(Latitude, Longitude)</div>
                        </td>
                    </tr>
                    <tr>
                        <th>Description</th>
                        <td colspan="3" class="remarks-cell">' . 
                            (isset($report['description']) ? nl2br(htmlspecialchars($report['description'])) : 'No description provided') . '
                        </td>
                    </tr>
                </table>';
        }
        
        // Status section (keep your existing status section code)
        $html .= '
                <div class="status-section">
                    <div class="current-status">
                        <div class="status-label">Current Status:</div>
                        <div class="status-badge ' . $statusClass . '">' . 
                            htmlspecialchars($report['status'] ?? 'Received') . '
                        </div>
                    </div>';
        
        // Status history - fetch all status updates for this report
        $statusHistory = getStatusHistory($report['report_id']);
        
        $html .= '
                    <div class="status-history">
                        <h4>Status History</h4>';
        
        if (!empty($statusHistory)) {
            foreach ($statusHistory as $history) {
                $historyDate = date('n/j/Y, g:i A', strtotime($history['notif_date']));
                $html .= '
                        <div class="status-item">
                            <div class="status-header">
                                <strong>' . htmlspecialchars($history['action_type']) . '</strong>
                                <small>' . $historyDate . '</small>
                            </div>
                            <div class="status-notifier">
                                ' . htmlspecialchars($history['notifier_name'] ?? 'System') . '
                            </div>
                            <div class="status-description">
                                ' . nl2br(htmlspecialchars($history['notif_description'] ?? 'Status updated')) . '
                            </div>
                        </div>';
            }
        } else {
            $html .= '
                        <div class="status-item">
                            <div class="status-description">No status history available</div>
                        </div>';
        }
        
        $html .= '
                    </div>
                </div>
            </div>
        </div>';
    }

    $html .= '
        <div class="footer">
            Generated by ' . htmlspecialchars($_SESSION["name"] ?? 'System') . ' (' .
            htmlspecialchars($_SESSION["accessrole"] ?? 'User') . ') on ' . 
            (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('F j, Y g:i A') . '
        </div>
    </body>
    </html>';

    return $html;
}

// Helper function to get only the latest status
function getLatestStatus($reportId) {
    global $connection;
    
    $query = "SELECT 
                rn.action_type, 
                rn.notif_date, 
                rn.notif_description,
                COALESCE(a.fullname, aa.admin_name, 'System') as notifier_name
              FROM report_notifstbl rn
              LEFT JOIN accountstbl a ON rn.account_id = a.account_id AND rn.notifier_type = 'accountstbl'
              LEFT JOIN adminaccountstbl aa ON rn.account_id = aa.admin_id AND rn.notifier_type = 'adminaccountstbl'
              WHERE rn.report_id = ?
              ORDER BY rn.notif_date DESC
              LIMIT 1";
    
    $stmt = $connection->prepare($query);
    $stmt->bind_param('s', $reportId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

// Helper function to truncate long text
function truncateText($text, $length) {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . '...';
}

function getStatusHistory($reportId) {
    global $connection;
    
    $query = "SELECT 
                rn.action_type, 
                rn.notif_date, 
                rn.notif_description,
                COALESCE(a.fullname, aa.admin_name, 'System') as notifier_name
              FROM report_notifstbl rn
              LEFT JOIN accountstbl a ON rn.notified_by = a.account_id AND rn.notifier_type = 'accountstbl'
              LEFT JOIN adminaccountstbl aa ON rn.notified_by = aa.admin_id AND rn.notifier_type = 'adminaccountstbl'
              WHERE rn.report_id = ?
              ORDER BY rn.notif_date DESC";
    
    $stmt = $connection->prepare($query);
    $stmt->bind_param('s', $reportId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_all(MYSQLI_ASSOC);
}
?>