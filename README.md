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

También se puede entregar una sola URL de conexión, como las que generan varias plataformas: `DATABASE_URL` (o `MYSQL_URL`), con el formato `mysql://usuario:clave@host:3306/TEATRO`. El orden de prioridad es: variables `DB_*` (si tienen valor) → URL de conexión → valores por defecto de la tabla.

### Conexiones con SSL (Aiven)

Aiven entrega la URI de conexión con `?ssl-mode=REQUIRED`, por ejemplo `mysql://avnadmin:clave@mysql-xxxx.a.aivencloud.com:12345/defaultdb?ssl-mode=REQUIRED`, y exige TLS. `conexion.php` agrega dos variables para ese caso:

| Variable      | Uso |
|---------------|-----|
| `DB_SSL_MODE` | `required` (cifra sin validar la CA), `verify_ca` o `verify_identity` (cifra y valida), `disabled` (sin TLS). Si no se define, se toma del `ssl-mode` de la URI. |
| `DB_SSL_CA`   | Ruta al `ca.pem` descargado de la consola de Aiven; se pasa a PDO con `PDO::MYSQL_ATTR_SSL_CA`. Si no se define y existe `ca.pem` en la raíz del proyecto, se usa ese archivo. |

- Con la URI de Aiven basta definir `DATABASE_URL`: el host, el puerto y la base (`defaultdb`) se extraen con `parse_url()`.
- Usuario y clave se decodifican (`%40`, `%23`, `%2F`…), así que una contraseña con caracteres especiales funciona aunque venga escapada en la URI.
- Sin `ca.pem` la conexión se cifra pero no valida el certificado del servidor (`PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false`); si se pidió `verify_ca`/`verify_identity` sin CA se deja constancia en el log.
- En local no se envía ninguna opción SSL, por lo que el comportamiento es idéntico al de siempre.

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

## 6. Despliegue en Render (Docker)

El repositorio incluye un `Dockerfile` basado en `php:8.2-apache` con las extensiones `pdo` y `pdo_mysql`, que copia el proyecto en `/var/www/html` y expone el puerto **80**.

1. En Render: **New → Web Service** y conectar el repositorio `jggalviz/soltig-prueba-teatro-jg`.
2. Runtime **Docker**: Render detecta el `Dockerfile` de la raíz. No hace falta comando de arranque; la imagen oficial levanta Apache en el puerto 80.
3. Crear una base MySQL gestionada (o usar una externa) y cargar el esquema:

```bash
mysql -h <host> -P <puerto> -u <usuario> -p --default-character-set=utf8mb4 < schema.sql
```

> El parámetro `--default-character-set=utf8mb4` es obligatorio: sin él los acentos y la "ñ" se guardan corruptos.

4. Definir las variables de entorno del servicio: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` o, si el proveedor entrega una sola cadena, `DATABASE_URL` (y `DB_SSL_MODE` / `DB_SSL_CA` si la base exige TLS).
5. Probar la URL pública (`/index.php`) y, si la conexión falla, revisar los logs del servicio: `conexion.php` registra el error de PDO con `error_log()` sin mostrar credenciales al usuario.

### Usar MySQL de Aiven como base de datos

1. Crear el servicio **MySQL** en Aiven y copiar su *Service URI* (`mysql://avnadmin:clave@mysql-xxxx.a.aivencloud.com:12345/defaultdb?ssl-mode=REQUIRED`).
2. En Render, definir **solo** `DATABASE_URL` con esa URI: el host, el puerto, el usuario, la clave y la base (`defaultdb`) se extraen automáticamente.
3. TLS: descargar el `ca.pem` desde la consola de Aiven (*Overview → CA certificate*) y guardarlo en la raíz del repositorio —`conexion.php` lo detecta solo y lo pasa como `PDO::MYSQL_ATTR_SSL_CA`—, o indicar su ruta con `DB_SSL_CA`. Si no se agrega, la conexión igual se cifra pero sin validar el certificado del servidor.
4. Importar el esquema en `defaultdb` (Aiven no permite crear bases nuevas): usar una copia de `schema.sql` sin las sentencias `DROP DATABASE`, `CREATE DATABASE` y `USE`.

```bash
grep -v -E '^(DROP DATABASE|CREATE DATABASE|USE )' schema.sql > schema_aiven.sql
mysql -h <host> -P <puerto> -u avnadmin -p --default-character-set=utf8mb4 defaultdb < schema_aiven.sql
```

> `schema_aiven.sql` es un archivo temporal: no se versiona.


## Estructura del proyecto

```
schema.sql      Base de datos, tablas y datos de prueba
conexion.php    Conexión PDO y utilidades de formato/escape
index.php       Cartelera de funciones disponibles
comprar.php     Formulario, registro de la venta y confirmación
css/style.css   Estilos de la interfaz
Dockerfile      Imagen php:8.2-apache para desplegar en la nube
ca.pem          Opcional: certificado CA de Aiven para conexiones TLS
README.md       Este documento
```

## Notas

- Las consultas usan sentencias preparadas; los datos de salida se escapan con `htmlspecialchars`.
- No se usa Composer ni dependencias externas: solo PHP, PDO y MySQL.
- El campo `comprador` admite cédula, DNI o nombre, hasta 80 caracteres.
