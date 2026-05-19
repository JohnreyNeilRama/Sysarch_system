<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['lab_room'] = '526';
chdir('pages/api');
ob_start();
include 'get_lab_computers.php';
$output = ob_get_clean();
echo $output;
?>
