<?php
// Conexión PDO y utilidades comunes para las vistas.
//
// La configuración se toma de variables de entorno para poder desplegar en la
// nube (Render, Railway, etc.). Si no están definidas se usan los valores
// típicos de un entorno local (XAMPP / WAMP / Laragon).

// 1) Valores de respaldo para desarrollo local.
$dbHost = 'localhost';
$dbPort = '3306';
$dbName = 'TEATRO';
$dbUser = 'root';
$dbPass = '';

// 2) Algunos proveedores entregan una sola URL de conexión, por ejemplo:
//    mysql://usuario:clave@host:3306/TEATRO
$dbUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_URL') ?: '';

if ($dbUrl !== '') {
    $partes = parse_url($dbUrl);

    if (is_array($partes)) {
        $dbHost = $partes['host'] ?? $dbHost;
        $dbPort = isset($partes['port']) ? (string) $partes['port'] : $dbPort;
        $dbUser = $partes['user'] ?? $dbUser;
        $dbPass = $partes['pass'] ?? $dbPass;

        if (isset($partes['path']) && $partes['path'] !== '' && $partes['path'] !== '/') {
            $dbName = ltrim($partes['path'], '/');
        }
    }
}

// 3) Las variables individuales tienen prioridad sobre la URL.
$dbHost = getenv('DB_HOST') ?: $dbHost;
$dbPort = getenv('DB_PORT') ?: $dbPort;
$dbName = getenv('DB_NAME') ?: $dbName;
$dbUser = getenv('DB_USER') ?: $dbUser;
$dbPass = getenv('DB_PASS') ?: $dbPass;

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
    exit('No fue posible conectar con la base de datos. Verifique las variables de entorno DB_HOST, DB_PORT, DB_NAME, DB_USER y DB_PASS (o DATABASE_URL).');
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
