<?php
include 'includes/connect.php';
$res = $conn->query('SELECT lab, COUNT(*) as count FROM sit_in GROUP BY lab');
while($row = $res->fetch_assoc()) {
    echo $row['lab'] . ': ' . $row['count'] . "\n";
}
?>
