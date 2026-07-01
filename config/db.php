<?php
// config/db.php — Conexión PDO centralizada
// Incluir con: require_once __DIR__ . '/../config/db.php';

define('DB_HOST', 'localhost');
define('DB_NAME', 'dblogitrack');
define('DB_USER', 'root');   // En producción: usuario con permisos mínimos
define('DB_PASS', '');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // Lanza excepciones en errores SQL
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // Devuelve arrays asociativos
            PDO::ATTR_EMULATE_PREPARES   => false,                    // Prepared statements reales
        ]
    );
} catch (PDOException $e) {
    // En producción: loguear el error, no mostrarlo al usuario
    error_log("Error de conexión BD: " . $e->getMessage());
    die(json_encode(['error' => 'Error de conexión a la base de datos.']));
}
