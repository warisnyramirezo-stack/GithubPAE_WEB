<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$host = "localhost";
$user = "root";
$pass = ""; // XAMPP default; change if needed
$db   = "pae_web"; // database from provided SQL dump

try {
    $conexion = new mysqli($host, $user, $pass, $db);
    $conexion->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    error_log('DB connection error: ' . $e->getMessage());
    die('Error de conexión. Revisa el registro de errores.');
}
?>