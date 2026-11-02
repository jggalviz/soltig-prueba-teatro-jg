# Teatro - Venta de entradas

Aplicación web en PHP nativo (PDO) y MySQL para publicar la cartelera de un teatro y registrar la venta de entradas, descontando los puestos disponibles de cada función.

## Requisitos

- PHP 8.0 o superior con la extensión `pdo_mysql` habilitada.
- MySQL 5.7+ o MariaDB 10.3+.
- Apache/Nginx, o el servidor embebido de PHP para pruebas locales.

## 1. Importar la base de datos

Desde la consola de MySQL:

```bash
mysql -u root -p < schema.sql
```

O desde phpMyAdmin: pestaña **Importar** → seleccionar `schema.sql` → **Continuar**.

El script crea la base `TEATRO`, las tablas `obra` y `ventas` (InnoDB, con clave foránea de `ventas.cod_obra` hacia `obra.cod_obra`) y cinco funciones de prueba: dos con entradas disponibles, una con pocas entradas, una agotada y una con fecha pasada. Las fechas se generan con `NOW()`, por lo que siempre habrá una función futura y una vencida.

> `schema.sql` empieza con `DROP DATABASE IF EXISTS TEATRO;`. Si ya tiene datos reales en esa base, comente esa línea antes de importar.

## 2. Configurar la conexión

`conexion.php` toma la configuración de variables de entorno y, si no existen, usa los valores típicos de un entorno local:

| Variable  | Valor por defecto |
|-----------|-------------------|
| `DB_HOST` | `localhost`       |
| `DB_PORT` | `3306`            |
| `DB_NAME` | `TEATRO`          |
| `DB_USER` | `root`            |
| `DB_PASS` | *(vacío)*         |

Opciones para ajustarla:

- Editar los valores de respaldo directamente en `conexion.php`.
- Exportar variables de entorno antes de arrancar el servidor:

```bash
# Linux / macOS
export DB_USER=teatro_user
export DB_PASS=clave_segura

# Windows PowerShell
$env:DB_USER = "teatro_user"
$env:DB_PASS = "clave_segura"
```

La conexión se abre con `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION` y `charset=utf8mb4`.

## 3. Ejecutar la aplicación

### Servidor embebido de PHP

```bash
php -S localhost:8000
```

Luego abrir <http://localhost:8000/index.php>.

### XAMPP / WAMP / Laragon

Copiar la carpeta del proyecto dentro de `htdocs` (o el directorio público equivalente), iniciar Apache y MySQL, y visitar:

```
http://localhost/soltig-prueba-teatro-jg/index.php
```

## 4. Recorrido de la aplicación

- `index.php`: lista únicamente las funciones con `disponibles > 0` y `fecha_obra > NOW()`, ordenadas por fecha.
- `comprar.php?id=X`: muestra el detalle de la función y el formulario del comprador.
- Al enviar el formulario, `comprar.php` procesa el `POST` dentro de una transacción:
  1. `beginTransaction()`.
  2. `SELECT ... FOR UPDATE` sobre la obra y verificación de `disponibles > 0` (evita sobreventa si dos personas compran a la vez).
  3. `INSERT` en `ventas` con el comprador.
  4. `UPDATE obra SET disponibles = disponibles - 1`.
  5. `commit()` y confirmación con el número de entrada obtenido de `lastInsertId()`.
  - Ante cualquier fallo se ejecuta `rollBack()` y se muestra el error al usuario.

## 5. Pruebas sugeridas

1. En la cartelera no deben aparecer *Fuenteovejuna* (fecha pasada) ni *El avaro* (0 disponibles).
2. Comprar una entrada y comprobar en MySQL que `ventas` tiene un registro nuevo y que `disponibles` bajó en 1:

```sql
SELECT * FROM ventas ORDER BY numero_venta DESC LIMIT 5;
SELECT cod_obra, nombre, disponibles FROM obra;
```

3. Intentar comprar en una función agotada (`comprar.php?id=3`): debe mostrarse el aviso de que no hay entradas.
4. Dejar el campo del comprador vacío y confirmar que se valida antes de escribir en la base.
5. Abrir dos pestañas con la misma función y confirmar que cada compra descuenta una entrada distinta.

## Estructura del proyecto

```
schema.sql      Base de datos, tablas y datos de prueba
conexion.php    Conexión PDO y utilidades de formato/escape
index.php       Cartelera de funciones disponibles
comprar.php     Formulario, registro de la venta y confirmación
css/style.css   Estilos de la interfaz
README.md       Este documento
```

## Notas

- Las consultas usan sentencias preparadas; los datos de salida se escapan con `htmlspecialchars`.
- No se usa Composer ni dependencias externas: solo PHP, PDO y MySQL.
- El campo `comprador` admite cédula, DNI o nombre, hasta 80 caracteres.
