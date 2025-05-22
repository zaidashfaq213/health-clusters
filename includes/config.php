<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "hospital_management";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// Set character set to utf8mb4 for proper Arabic text handling
$conn->set_charset('utf8mb4');

function makeUrlFriendly($string) {
    $string = strtolower(trim($string));
    $string = preg_replace('/[^a-z0-9-]+/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return $string;
}
?>