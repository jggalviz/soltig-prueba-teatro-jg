<?php
require __DIR__ . '/conexion.php';

$obraId = (int) ($_POST['cod_obra'] ?? $_GET['id'] ?? 0);
$obra = null;
$comprador = '';
$errores = [];
$error = null;
$numeroVenta = null;

if ($obraId > 0) {
    $stmt = $pdo->prepare(
        'SELECT cod_obra, nombre, fecha_obra, aforo, disponibles, sala FROM obra WHERE cod_obra = ?'
    );
    $stmt->execute([$obraId]);
    $obra = $stmt->fetch() ?: null;
}

if ($obra === null) {
    http_response_code(404);
}

if ($obra !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $comprador = trim((string) ($_POST['comprador'] ?? ''));

    if ($comprador === '') {
        $errores[] = 'Ingrese la cédula o el nombre del comprador.';
    } elseif (mb_strlen($comprador) > 80) {
        $errores[] = 'El dato del comprador admite hasta 80 caracteres.';
    }

    if ($errores === []) {
        try {
            $pdo->beginTransaction();

            $bloqueo = $pdo->prepare('SELECT disponibles FROM obra WHERE cod_obra = ? FOR UPDATE');
            $bloqueo->execute([$obraId]);
            $disponibles = $bloqueo->fetchColumn();

            if ($disponibles === false) {
                throw new RuntimeException('La función indicada ya no existe.');
            }

            if ((int) $disponibles < 1) {
                throw new RuntimeException('Esta función ya no tiene entradas disponibles.');
            }

            $insertar = $pdo->prepare('INSERT INTO ventas (cod_obra, comprador) VALUES (?, ?)');
            $insertar->execute([$obraId, $comprador]);
            $numeroVenta = (int) $pdo->lastInsertId();

            $actualizar = $pdo->prepare('UPDATE obra SET disponibles = disponibles - 1 WHERE cod_obra = ?');
            $actualizar->execute([$obraId]);

            $pdo->commit();

            $obra['disponibles'] = (int) $disponibles - 1;
        } catch (Throwable $fallo) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Venta no registrada: ' . $fallo->getMessage());
            $error = $fallo instanceof RuntimeException
                ? $fallo->getMessage()
                : 'No se pudo completar la compra. Intente nuevamente.';

            $relectura = $pdo->prepare('SELECT disponibles FROM obra WHERE cod_obra = ?');
            $relectura->execute([$obraId]);
            $obra['disponibles'] = (int) $relectura->fetchColumn();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Comprar entradas | Teatro</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="barra">
        <div class="contenedor">
            <span class="marca">Teatro</span>
            <span class="seccion">Boletería</span>
        </div>
    </header>

    <main class="contenedor angosto">
        <a class="volver" href="index.php">&larr; Volver a la cartelera</a>

        <?php if ($numeroVenta !== null): ?>
            <section class="confirmacion">
                <h1>¡Compra realizada con éxito!</h1>
                <p>Su número de entrada es:</p>
                <p class="entrada">#<?= $numeroVenta ?></p>
                <ul class="resumen">
                    <li><strong>Obra:</strong> <?= h($obra['nombre']) ?></li>
                    <li><strong>Función:</strong> <?= h(fechaLarga($obra['fecha_obra'])) ?></li>
                    <li><strong>Sala:</strong> <?= h($obra['sala']) ?></li>
                    <li><strong>Comprador:</strong> <?= h($comprador) ?></li>
                </ul>
                <p class="nota">Conserve este número: lo necesitará para ingresar a la sala.</p>
                <a class="boton" href="index.php">Volver a la cartelera</a>
            </section>
        <?php elseif ($obra === null): ?>
            <p class="aviso aviso-error">La función solicitada no existe o fue retirada de la cartelera.</p>
        <?php else: ?>
            <h1><?= h($obra['nombre']) ?></h1>

            <dl class="detalles detalles-ficha">
                <div>
                    <dt>Fecha y hora</dt>
                    <dd><?= h(fechaLarga($obra['fecha_obra'])) ?></dd>
                </div>
                <div>
                    <dt>Sala</dt>
                    <dd><?= h($obra['sala']) ?></dd>
                </div>
                <div>
                    <dt>Aforo</dt>
                    <dd><?= (int) $obra['aforo'] ?> butacas</dd>
                </div>
                <div>
                    <dt>Disponibles</dt>
                    <dd><?= (int) $obra['disponibles'] ?> entradas</dd>
                </div>
            </dl>

            <?php if ($errores !== []): ?>
                <div class="aviso aviso-error">
                    <?php foreach ($errores as $mensaje): ?>
                        <p><?= h($mensaje) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($error !== null): ?>
                <p class="aviso aviso-error"><?= h($error) ?></p>
            <?php endif; ?>

            <?php if ((int) $obra['disponibles'] > 0): ?>
                <form class="formulario" method="post" action="comprar.php">
                    <input type="hidden" name="cod_obra" value="<?= (int) $obra['cod_obra'] ?>">

                    <label for="comprador">Cédula o nombre del comprador</label>
                    <input
                        type="text"
                        id="comprador"
                        name="comprador"
                        value="<?= h($comprador) ?>"
                        maxlength="80"
                        placeholder="Ej. 1712345678"
                        autocomplete="off"
                        required>

                    <button class="boton" type="submit">Confirmar compra</button>
                </form>
            <?php else: ?>
                <p class="aviso">Las entradas para esta función están agotadas.</p>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <footer class="pie">
        <div class="contenedor">Teatro &middot; boletería en línea</div>
    </footer>
</body>
</html>
