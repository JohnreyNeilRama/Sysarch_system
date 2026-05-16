<?php
session_start();
include '../../includes/connect.php';

// Check if admin is logged in
if(!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true){
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$response = [];

// 1. Total Counts
$response['counts'] = [
    'students' => $conn->query("SELECT COUNT(*) FROM students")->fetch_row()[0],
    'sitin' => $conn->query("SELECT COUNT(*) FROM sit_in")->fetch_row()[0],
    'reservations' => $conn->query("SELECT COUNT(*) FROM reservations")->fetch_row()[0]
];

// 2. Activity Trends (Last 7 Days)
$activity_trends = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $count = $conn->query("SELECT COUNT(*) FROM sit_in WHERE sit_in_date = '$date'")->fetch_row()[0];
    $activity_trends[] = [
        'date' => date('M d', strtotime($date)),
        'count' => $count
    ];
}
$response['activity_trends'] = $activity_trends;

// 3. Peak Usage Hours
$peak_hours = [];
$peak_query = "SELECT HOUR(sit_in_time) as hour, COUNT(*) as count FROM sit_in GROUP BY HOUR(sit_in_time) ORDER BY hour ASC";
$peak_result = $conn->query($peak_query);
$hours_data = array_fill(0, 24, 0);
while($row = $peak_result->fetch_assoc()) {
    $hours_data[$row['hour']] = (int)$row['count'];
}
$response['peak_hours'] = $hours_data;

// 4. Leaderboard (Top 10 Students by Points)
$top_students = [];
$student_query = "SELECT first_name, last_name, points_earned FROM students WHERE points_earned > 0 ORDER BY points_earned DESC LIMIT 10";
$student_result = $conn->query($student_query);
if ($student_result) {
    while($row = $student_result->fetch_assoc()) {
        $top_students[] = [
            'student_name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'points' => (int)$row['points_earned']
        ];
    }
}
$response['top_students'] = $top_students;

// 5. Lab Usage
$lab_usage = [];
$lab_query = "SELECT lab, COUNT(*) as count FROM sit_in GROUP BY lab ORDER BY count DESC";
$lab_result = $conn->query($lab_query);
while($row = $lab_result->fetch_assoc()) {
    $lab_usage[] = $row;
}
$response['lab_usage'] = $lab_usage;

echo json_encode($response);
$conn->close();
?>
