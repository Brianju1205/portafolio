# Práctica 4 y 5 - Tarjeta Digital de Empleado y Punto de Venta

Interfaz para generar el RFC de una persona y su tarjeta digital de empleado, y un punto de venta con tabla de productos, hechos con la plantilla Sakai.

Desarrollo de aplicaciones web y móviles - UNACH ETDA Campus IV
Brian Antonio Juarez Garcia

## Práctica 4 - Tarjeta Digital de Empleado

Demo: https://briandev-portafolio.vercel.app/pract4/#/tarjeta

Archivo: `src/views/pages/TarjetaEmpleado.vue`

- Captura de nombre, apellidos, fecha de nacimiento y sexo
- Cálculo del RFC con homoclave y dígito verificador
- Tarjeta digital con foto opcional y código de barras del RFC
- Dashboard de la plantilla Sakai

## Práctica 5 - Punto de Venta

Demo: https://briandev-portafolio.vercel.app/pract4/#/punto-venta

Archivo: `src/views/pages/PuntoVenta.vue`

- Registro de productos con nombre, cantidad y precio
- Tabla con consecutivo, precio unitario, cantidad y precio parcial
- Edición y eliminación de productos

## Tecnologías

Vue 3, PrimeVue 4, Tailwind CSS, Vite, JsBarcode

## Ejecutar

```
npm install
npm run dev
```
