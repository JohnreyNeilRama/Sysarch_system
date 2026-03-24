<?php
session_start();

// Check if admin is logged in
if(!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true){
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Include database connection
include '../includes/connect.php';

// Get time range from request
$range = isset($_GET['range']) ? $_GET['range'] : 'today';

// Get current date info
$today = date('Y-m-d');
$current_year = date('Y');
$current_month = date('m');
$current_week_start = date('Y-m-d', strtotime('monday this week'));

// Build query based on time range
$where_clause = '';
switch($range) {
    case 'today':
        $where_clause = "WHERE sit_in_date = '$today'";
        break;
    case 'week':
        $where_clause = "WHERE sit_in_date >= '$current_week_start'";
        break;
    case 'month':
        $where_clause = "WHERE sit_in_date LIKE '$current_year-$current_month%'";
        break;
    case 'year':
        $where_clause = "WHERE sit_in_date LIKE '$current_year%'";
        break;
    case 'all':
    default:
        $where_clause = '';
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

$sql = "SELECT purpose, COUNT(*) as count FROM sit_in";
if(!empty($where_clause)) {
    $sql .= " " . $where_clause;
}
$sql .= " GROUP BY purpose";

$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();

while($row = $result->fetch_assoc()){
    $purpose = $row['purpose'];
    if(isset($purpose_data[$purpose])){
        $purpose_data[$purpose] = $row['count'];
    } else {
        $purpose_data['Other'] += $row['count'];
    }
}
$stmt->close();

// Return as JSON
header('Content-Type: application/json');
echo json_encode(array(
    'labels' => array_keys($purpose_data),
    'values' => array_values($purpose_data)
));

$conn->close();
