<?php
// Conexión PDO y utilidades comunes para las vistas.

$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'TEATRO';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';

$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName);

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
    ]);
} catch (PDOException $error) {
    error_log('Conexión PDO fallida: ' . $error->getMessage());
    http_response_code(500);
    exit('No fue posible conectar con la base de datos. Revise la configuración de conexion.php.');
}

function h(?string $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function fechaLarga(string $fecha): string
{
    $dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    $meses = [
        'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
    ];

    $momento = new DateTime($fecha);
    $texto = sprintf(
        '%s %d de %s de %s, %s h',
        $dias[(int) $momento->format('w')],
        (int) $momento->format('j'),
        $meses[(int) $momento->format('n') - 1],
        $momento->format('Y'),
        $momento->format('H:i')
    );

    return ucfirst($texto);
}
