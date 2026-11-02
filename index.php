<?php
require __DIR__ . '/conexion.php';

$consulta = $pdo->query(
    'SELECT cod_obra, nombre, fecha_obra, aforo, disponibles, sala
       FROM obra
      WHERE disponibles > 0
        AND fecha_obra > NOW()
      ORDER BY fecha_obra ASC'
);
$obras = $consulta->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cartelera | Teatro</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="barra">
        <div class="contenedor">
            <span class="marca">Teatro</span>
            <span class="seccion">Cartelera</span>
        </div>
    </header>

    <main class="contenedor">
        <h1>Próximas funciones</h1>
        <p class="intro">Funciones con entradas disponibles. Elija una y reserve su puesto.</p>

        <?php if (count($obras) === 0): ?>
            <p class="aviso">Por el momento no hay funciones con entradas disponibles.</p>
        <?php else: ?>
            <div class="rejilla">
                <?php foreach ($obras as $obra): ?>
                    <article class="ficha">
                        <h2><?= h($obra['nombre']) ?></h2>
                        <dl class="detalles">
                            <div>
                                <dt>Fecha y hora</dt>
                                <dd><?= h(fechaLarga($obra['fecha_obra'])) ?></dd>
                            </div>
                            <div>
                                <dt>Sala</dt>
                                <dd><?= h($obra['sala']) ?></dd>
                            </div>
                            <div>
                                <dt>Entradas</dt>
                                <dd>
                                    <span class="etiqueta<?= (int) $obra['disponibles'] <= 10 ? ' etiqueta-baja' : '' ?>">
                                        <?= (int) $obra['disponibles'] ?> de <?= (int) $obra['aforo'] ?> disponibles
                                    </span>
                                </dd>
                            </div>
                        </dl>
                        <a class="boton" href="comprar.php?id=<?= (int) $obra['cod_obra'] ?>">Comprar entradas</a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <footer class="pie">
        <div class="contenedor">Teatro &middot; boletería en línea</div>
    </footer>
</body>
</html>
