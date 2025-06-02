# Verificador de Empleados

Este plugin de WordPress permite gestionar empleados y generar un código QR para su verificación.

## Instalación
1. Copia los archivos del repositorio en el directorio `wp-content/plugins/` de tu instalación de WordPress.
2. Desde el panel de administración de WordPress, activa el plugin **Verificador de Empleados**.

Al activarse se crea la tabla `wp_empleados` donde se almacenan los datos.

## Uso
- Utiliza el shortcode `[agregar_empleado]` para mostrar el formulario de alta y edición.
- Utiliza el shortcode `[verificar_empleado]` para mostrar la información del empleado a partir de su ID.

Los códigos QR apuntan a la ruta `/verificar-empleado/{id}/` en tu sitio.

Para un funcionamiento completo, añade las imágenes `activo.png`, `inactivo.png` y `fondo.png` en la carpeta del plugin.

## Requerimientos
- WordPress 5.0 o superior.
- PHP 7.0 o superior.

