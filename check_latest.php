<?php
include 'includes/connect.php';
$res = $conn->query('SELECT * FROM sit_in ORDER BY id DESC LIMIT 5');
while($row = $res->fetch_assoc()) {
    echo $row['id'] . ': ' . $row['sit_in_date'] . ' ' . $row['sit_in_time'] . ' ' . $row['purpose'] . "\n";
}
?>
