# Práctica 3 - Gestión de Documentos

Sistema web para registrar y dar seguimiento a los documentos que llegan a una oficina (oficios, circulares, solicitudes, etc.).

Desarrollo de aplicaciones web y móviles - UNACH ETDA Campus IV
Brian Antonio Juarez Garcia

Demo: https://briandev-portafolio.vercel.app/pract3/

## Funciones

- Registrar documentos con no. de oficio, fecha, asunto, remitente, destinatario y estatus
- Tomar foto del documento desde el celular (se convierte a PDF) o subir un PDF
- Estatus: Recibido, En proceso y Atendido
- Buscar y filtrar por oficio, asunto, remitente, destinatario, fecha y estatus
- Ver el PDF dentro de la página y descargarlo
- Diseño responsivo para celular, tableta y computadora

## Tecnologías

- HTML, CSS y jQuery
- PHP (PDO)
- MySQL
- jsPDF para convertir las fotos a PDF y pdf.js para mostrar los PDF

Los PDF se guardan en la base de datos para que funcione también en Vercel.

## Carpetas

```
index.html
css/        estilos
js/         app.js
api/        archivos PHP (documentos, pdf, resumen)
sql/        script de la base de datos y datos de prueba
```

## Instalación

### Con XAMPP

1. Copiar la carpeta en `htdocs`.
2. En phpMyAdmin importar `sql/01_esquema.sql` y luego `sql/02_datos_prueba.sql`.
3. Copiar `api/config.example.php` como `api/config.local.php` y poner el usuario y contraseña de MySQL.
4. Abrir http://localhost/pract3/

### Con Docker

```
docker compose up -d
```

Abrir http://localhost:8080

### En Vercel

Se usa el runtime `vercel-php` y una base de datos MySQL en TiDB Cloud. Los datos de conexión van en las variables de entorno `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` y `DB_SSL`.

## Base de datos

Tabla `documentos`: id_documento, no_oficio, fecha, asunto, remitente, destinatario, estatus, archivo_pdf.

Tabla `documentos_archivos`: guarda el contenido del PDF de cada documento.
