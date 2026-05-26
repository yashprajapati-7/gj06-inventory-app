<?php
// Copy this file to config.php and fill in your real values.
// NEVER commit config.php to GitHub.
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$conn = new mysqli(
    "YOUR_DB_HOST",       // e.g. sql110.infinityfree.com
    "YOUR_DB_USERNAME",   // e.g. if0_41656960
    "YOUR_DB_PASSWORD",
    "YOUR_DB_NAME"        // e.g. if0_41656960_inventory_app
);
if ($conn->connect_error) { die("Database connection failed: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");
