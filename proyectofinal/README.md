# CañaData-IA

Proyecto final de Desarrollo de aplicaciones web y móviles.

Aplicación web para consultar y analizar la producción de caña de azúcar en México con los datos de Infocaña (CONADESUCA) de las zafras 2015-2016 a 2025-2026, y generar recomendaciones con un modelo de IA local (Ollama + Llama 3.2 3B).

## Tecnologías

- HTML5 y CSS3
- jQuery y AJAX
- PHP para leer los CSV y hacer los cálculos
- Ollama con el modelo llama3.2:3b

## Cómo funciona

1. El usuario elige el ingenio, la zafra, el indicador y escribe su pregunta.
2. jQuery manda los datos por AJAX en formato JSON a `api/consultar.php`.
3. PHP lee los 11 archivos CSV de la carpeta `data/`, toma el cierre de cada ingenio por zafra y calcula promedios, máximos, mínimos, el ranking y la tendencia.
4. Con esos resultados arma el contexto y se lo manda a la API de Ollama.
5. Llama 3B explica los resultados y da una recomendación.
6. La respuesta regresa en JSON y jQuery la muestra en la página.

Los CSV traen el avance semanal acumulado, por eso se usa la última semana de cada ingenio en cada zafra. La zafra 2025-2026 todavía está en curso, así que sus datos son parciales.

## Cómo correrlo

1. Instalar Ollama y descargar el modelo:

```
ollama pull llama3.2:3b
```

2. Copiar la carpeta `proyectofinal` a `C:\xampp\htdocs\` y encender Apache, o correr desde la carpeta:

```
C:\xampp\php\php.exe -S localhost:8080
```

3. Abrir http://localhost/proyectofinal o http://localhost:8080

## Estructura

- `index.html` interfaz
- `css/estilos.css` estilos
- `js/app.js` lógica con jQuery
- `api/lib.php` lectura de CSV
- `api/catalogos.php` lista de ingenios, zafras e indicadores
- `api/consultar.php` cálculos y consulta a Ollama
- `api/estado.php` revisa si Ollama está encendido
- `data/` archivos CSV

## Fuente de datos

https://www.datos.gob.mx/dataset/avance_produccion_cana_azucar_infocana
