<?php
$db = new SQLite3('sitin_monitoring.db');

// Query all students
$results = $db->query("SELECT * FROM students");

echo "<h2>Registered Students</h2>";
echo "<table border='1'>";
echo "<tr><th>ID</th><th>Last Name</th><th>First Name</th><th>Course</th><th>Year</th><th>Email</th></tr>";

while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
    echo "<tr>";
    echo "<td>" . $row['id_number'] . "</td>";
    echo "<td>" . $row['last_name'] . "</td>";
    echo "<td>" . $row['first_name'] . "</td>";
    echo "<td>" . $row['course'] . "</td>";
    echo "<td>" . $row['year_level'] . "</td>";
    echo "<td>" . $row['email'] . "</td>";
    echo "</tr>";
}
echo "</table>";
?>
