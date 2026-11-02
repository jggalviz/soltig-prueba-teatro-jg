<?php
// Conexión PDO y utilidades comunes para las vistas.
//
// La configuración se toma de variables de entorno para poder desplegar en la
// nube (Render, Aiven, Railway, etc.). Si no están definidas se usan los valores
// típicos de un entorno local (XAMPP / WAMP / Laragon).

// 1) Valores de respaldo para desarrollo local.
$dbHost = 'localhost';
$dbPort = '3306';
$dbName = 'TEATRO';
$dbUser = 'root';
$dbPass = '';

// 2) Opciones de TLS. Aiven exige conexiones cifradas y entrega la URI de
//    conexión con la opción ?ssl-mode=REQUIRED.
$dbSslMode = getenv('DB_SSL_MODE') ?: '';   // required | verify_ca | verify_identity | disabled
$dbSslCa   = getenv('DB_SSL_CA') ?: '';     // ruta al ca.pem descargado de la consola de Aiven

// 3) Algunos proveedores entregan una sola URL de conexión. La de Aiven tiene
//    esta forma:
//    mysql://avnadmin:clave@mysql-proyecto.a.aivencloud.com:12345/defaultdb?ssl-mode=REQUIRED
$dbUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_URL') ?: '';

if ($dbUrl !== '') {
    $partes = parse_url($dbUrl);

    if (is_array($partes)) {
        $dbHost = $partes['host'] ?? $dbHost;
        $dbPort = isset($partes['port']) ? (string) $partes['port'] : $dbPort;

        // Aiven y otros proveedores pueden entregar usuario y clave codificados
        // (%40, %23, %2F...), por eso se decodifican.
        $dbUser = isset($partes['user']) ? rawurldecode($partes['user']) : $dbUser;
        $dbPass = isset($partes['pass']) ? rawurldecode($partes['pass']) : $dbPass;

        if (isset($partes['path']) && $partes['path'] !== '' && $partes['path'] !== '/') {
            // En Aiven la base de datos de la URI es "defaultdb".
            $dbName = rawurldecode(ltrim($partes['path'], '/'));
        }

        // El modo SSL puede venir en la propia URI (?ssl-mode=REQUIRED).
        if ($dbSslMode === '' && isset($partes['query'])) {
            parse_str($partes['query'], $parametros);

            $modoUri = $parametros['ssl-mode'] ?? $parametros['sslmode'] ?? '';

            if ($modoUri !== '') {
                $dbSslMode = (string) $modoUri;
            }
        }
    } else {
        error_log('conexion.php: no se pudo interpretar DATABASE_URL/MYSQL_URL; se usan las variables DB_* o los valores locales.');
    }
}

// 4) Las variables individuales tienen prioridad sobre la URL.
$dbHost = getenv('DB_HOST') ?: $dbHost;
$dbPort = getenv('DB_PORT') ?: $dbPort;
$dbName = getenv('DB_NAME') ?: $dbName;
$dbUser = getenv('DB_USER') ?: $dbUser;
$dbPass = getenv('DB_PASS') ?: $dbPass;

$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName);

$opciones = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
];

// 5) TLS de Aiven: si no se define DB_SSL_CA se busca un ca.pem junto al
//    proyecto (se puede desplegar con la imagen) y, si tampoco existe, se
//    negocia TLS sin validar la CA del servidor.
$dbSslMode = str_replace('-', '_', strtolower(trim($dbSslMode)));

if ($dbSslCa === '' && is_file(__DIR__ . DIRECTORY_SEPARATOR . 'ca.pem')) {
    $dbSslCa = __DIR__ . DIRECTORY_SEPARATOR . 'ca.pem';
}

$sslDeshabilitado = in_array($dbSslMode, ['disabled', 'disable', 'off'], true);
$sslRequerido     = in_array($dbSslMode, ['required', 'require', 'preferred', 'verify_ca', 'verify_identity'], true);
$usaCa            = false;

if (!$sslDeshabilitado && $dbSslCa !== '') {
    if (is_readable($dbSslCa) && defined('PDO::MYSQL_ATTR_SSL_CA')) {
        $opciones[PDO::MYSQL_ATTR_SSL_CA] = $dbSslCa;
        $usaCa = true;
    } else {
        error_log('conexion.php: DB_SSL_CA no es legible o pdo_mysql no soporta SSL_CA (' . $dbSslCa . ').');
    }
}

if (!$sslDeshabilitado && !$usaCa && $sslRequerido) {
    if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
        $opciones[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    } else {
        error_log('conexion.php: pdo_mysql no soporta SSL_VERIFY_SERVER_CERT; la conexión no se cifrará.');
    }

    if ($dbSslMode === 'verify_ca' || $dbSslMode === 'verify_identity') {
        error_log('conexion.php: se pidió verificar la CA de Aiven pero DB_SSL_CA no está disponible; se conecta con TLS sin verificación.');
    }
}

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $opciones);
} catch (PDOException $error) {
    error_log('Conexión PDO fallida: ' . $error->getMessage());
    http_response_code(500);
    exit('No fue posible conectar con la base de datos. Verifique las variables de entorno DB_HOST, DB_PORT, DB_NAME, DB_USER y DB_PASS (o DATABASE_URL); con Aiven revise además el SSL (DB_SSL_CA / DB_SSL_MODE).');
}

// 6) Inicialización del esquema. Se ejecuta justo después de conectar para que
//    la aplicación funcione sobre una base vacía (por ejemplo, el defaultdb de
//    Aiven). Las tablas se crean con las columnas del catálogo (descripcion,
//    precio, imagen, cantidad y total) y además con las que ya consultan
//    index.php y comprar.php (fecha_obra, aforo, disponibles, sala y comprador),
//    así las vistas siguen funcionando sin cambios.
try {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS obra (
            cod_obra    INT AUTO_INCREMENT PRIMARY KEY,
            nombre      VARCHAR(150) NOT NULL,
            descripcion TEXT,
            precio      DECIMAL(10,2) NOT NULL,
            imagen      VARCHAR(255),
            fecha_obra  DATETIME NOT NULL,
            aforo       INT NOT NULL,
            disponibles INT NOT NULL,
            sala        VARCHAR(60) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS ventas (
            cod_venta INT AUTO_INCREMENT PRIMARY KEY,
            cod_obra  INT NOT NULL,
            cantidad  INT NOT NULL DEFAULT 1,
            total     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            fecha     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            comprador VARCHAR(80) NOT NULL DEFAULT '',
            INDEX idx_ventas_obra (cod_obra),
            CONSTRAINT fk_ventas_obra FOREIGN KEY (cod_obra) REFERENCES obra (cod_obra)
                ON UPDATE CASCADE
                ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // 7) Datos de prueba: solo se cargan si la cartelera está vacía.
    if ((int) $pdo->query('SELECT COUNT(*) FROM obra')->fetchColumn() === 0) {
        $semilla = [
            [
                'nombre'      => 'Sueño de una noche de verano',
                'descripcion' => 'Comedia de William Shakespeare sobre el amor, la magia y los enredos de una noche en el bosque.',
                'precio'      => 12.50,
                'imagen'      => 'img/sueno-una-noche-de-verano.jpg',
                'dias'        => 7,
                'hora'        => 19,
                'minutos'     => 30,
                'aforo'       => 100,
                'sala'        => 'Sala principal',
            ],
            [
                'nombre'      => 'La casa de Bernarda Alba',
                'descripcion' => 'Drama de Federico García Lorca sobre el luto, la autoridad y el deseo encerrado entre cuatro paredes.',
                'precio'      => 10.00,
                'imagen'      => 'img/la-casa-de-bernarda-alba.jpg',
                'dias'        => 14,
                'hora'        => 20,
                'minutos'     => 0,
                'aforo'       => 80,
                'sala'        => 'Sala secundaria',
            ],
            [
                'nombre'      => 'El Principito',
                'descripcion' => 'Adaptación teatral del relato de Antoine de Saint-Exupéry, pensada para toda la familia.',
                'precio'      => 8.00,
                'imagen'      => 'img/el-principito.jpg',
                'dias'        => 21,
                'hora'        => 17,
                'minutos'     => 0,
                'aforo'       => 120,
                'sala'        => 'Sala principal',
            ],
        ];

        // Si la tabla viene del esquema anterior (sin descripcion/precio/imagen)
        // se inserta solo con las columnas que existen.
        $columnasObra   = $pdo->query('SHOW COLUMNS FROM obra')->fetchAll(PDO::FETCH_COLUMN);
        $tieneCatalogo  = in_array('precio', $columnasObra, true) && in_array('descripcion', $columnasObra, true);

        $insertar = $tieneCatalogo
            ? $pdo->prepare('INSERT INTO obra (nombre, descripcion, precio, imagen, fecha_obra, aforo, disponibles, sala) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            : $pdo->prepare('INSERT INTO obra (nombre, fecha_obra, aforo, disponibles, sala) VALUES (?, ?, ?, ?, ?)');

        foreach ($semilla as $fila) {
            $fecha = new DateTime('+' . $fila['dias'] . ' days');
            $fecha->setTime($fila['hora'], $fila['minutos']);
            $fechaTexto = $fecha->format('Y-m-d H:i:s');

            if ($tieneCatalogo) {
                $insertar->execute([
                    $fila['nombre'],
                    $fila['descripcion'],
                    $fila['precio'],
                    $fila['imagen'],
                    $fechaTexto,
                    $fila['aforo'],
                    $fila['aforo'],
                    $fila['sala'],
                ]);
            } else {
                $insertar->execute([
                    $fila['nombre'],
                    $fechaTexto,
                    $fila['aforo'],
                    $fila['aforo'],
                    $fila['sala'],
                ]);
            }
        }
    }
} catch (PDOException $error) {
    // No se interrumpe la petición: si falla la creación, el error queda en el log.
    error_log('conexion.php: no se pudo inicializar el esquema: ' . $error->getMessage());
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
