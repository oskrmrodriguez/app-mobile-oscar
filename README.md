# Backend PHP para app móvil

Estructura mínima del proyecto para desplegar en Render (o similar).

## Estructura de carpetas

- `index.php` → Endpoint simple de prueba (`/`).
- `api_rest/sesion.php` → Endpoint principal con acciones `login`, `register`, `recover`.
- `motor_db/conexion_db.php` → Archivo de conexión a la base de datos (MySQL/Postgres).

## Cómo usar este zip

1. Clona el repositorio o descomprime este contenido.
2. Reemplaza estos archivos por los tuyos:

   - `motor_db/conexion_db.php`
   - `api_rest/sesion.php`

3. Sube el proyecto a GitHub / GitLab.
4. En Render, crea un **Web Service** apuntando a este repo.
5. Usa como root del servicio el directorio del proyecto (donde está `index.php`).

