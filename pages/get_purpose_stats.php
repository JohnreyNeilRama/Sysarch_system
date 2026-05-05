<?php
session_start();
ob_start();

// Check if admin is logged in
if(!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true){
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Include database connection
include '../includes/connect.php';

// Set timezone to Philippines (Asia/Manila)
date_default_timezone_set('Asia/Manila');

// Get time range from request
$range = isset($_GET['range']) ? $_GET['range'] : 'today';

// Get current date info
$today = date('Y-m-d');
$current_year = date('Y');
$current_month = date('m');
$current_week_start = date('Y-m-d', strtotime('monday this week'));
if($current_week_start > $today) {
    $current_week_start = date('Y-m-d', strtotime('monday last week'));
}

// Build query based on time range
$where_clause = '';
switch($range) {
    case 'today':
        $where_clause = "WHERE sit_in_date = '$today'";
        break;
    case 'week':
        $where_clause = "WHERE sit_in_date >= '$current_week_start' AND sit_in_date <= '$today'";
        break;
    case 'month':
        $first_day_of_month = date('Y-m-01');
        $where_clause = "WHERE sit_in_date >= '$first_day_of_month' AND sit_in_date <= '$today'";
        break;
    case 'year':
    default:
        $first_day_of_year = date('Y-01-01');
        $where_clause = "WHERE sit_in_date >= '$first_day_of_year' AND sit_in_date <= '$today'";
        break;
}

// Get most visited lab for this range
$most_visited_lab = "None";
$most_visited_count = 0;
$lab_sql = "SELECT lab, COUNT(*) as session_count FROM sit_in " . $where_clause . " GROUP BY lab ORDER BY session_count DESC LIMIT 1";
$lab_result = $conn->query($lab_sql);
if($lab_result && $row = $lab_result->fetch_assoc()) {
    $most_visited_lab = $row['lab'];
    $most_visited_count = $row['session_count'];
}

// Get purpose statistics
$purpose_data = array(
    'C Programming' => 0,
    'Java Programming' => 0,
    'Python Programming' => 0,
    'Web Development' => 0,
    'Database' => 0,
    'Research' => 0,
    'Assignment' => 0,
    'Examination' => 0,
    'Other' => 0
);

// Check if status column exists
$check_status = $conn->query("SHOW COLUMNS FROM sit_in LIKE 'status'");
$has_status = ($check_status->num_rows > 0);

// Build query - count ALL sit-ins within time range (not just active ones)
$sql = "SELECT purpose, COUNT(*) as count FROM sit_in";
if(!empty($where_clause)) {
    $sql .= " " . $where_clause;
}
$sql .= " GROUP BY purpose";

$result = $conn->query($sql);
if(!$result) {
    echo json_encode(['error' => 'Query failed: ' . $conn->error, 'sql' => $sql]);
    exit;
}

while($row = $result->fetch_assoc()){
    $purpose = $row['purpose'];
    if(isset($purpose_data[$purpose])){
        $purpose_data[$purpose] = $row['count'];
    } else {
        $purpose_data['Other'] += $row['count'];
    }
}

// Return as JSON
header('Content-Type: application/json');
ob_clean();
echo json_encode(array(
    'labels' => array_keys($purpose_data),
    'values' => array_values($purpose_data),
    'most_visited_lab' => $most_visited_lab,
    'most_visited_count' => $most_visited_count
));

$conn->close();
