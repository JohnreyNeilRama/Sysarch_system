<?php
$c = file_get_contents('pages/admin_dashboard.php');
echo "Active count: " . substr_count($c, 'Active') . "\n";
echo "sit_in count: " . substr_count($c, 'sit_in') . "\n";
echo "reservation count: " . substr_count($c, 'reservation') . "\n";
echo "get_computers count: " . substr_count($c, 'get_computers') . "\n";
?>
