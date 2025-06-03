<?php
/**
 * Plugin Name: Verificador de Empleados
 * Description: Plugin para verificar empleados por ID a través de una URL con código QR. Usa URLs como /verificar-empleado/123.
 * Version: 1.3.2
 * Author: Luis Fernando Lizardi Mejía | Manuel Saldivar Martínez
 */

// === REGLA DE REESCRITURA ===
function ve_agregar_rewrite_rule() {
    add_rewrite_rule('^verificar-empleado/([0-9]+)/?$', 'index.php?pagename=verificar-empleado&empleado_id=$matches[1]', 'top');
}
add_action('init', 've_agregar_rewrite_rule');

// === VARIABLE DE QUERY PERSONALIZADA ===
function ve_agregar_query_vars($vars) {
    $vars[] = 'empleado_id';
    return $vars;
}
add_filter('query_vars', 've_agregar_query_vars');

// === ACTIVACIÓN Y DESACTIVACIÓN ===
function ve_activar_plugin() {
    ve_agregar_rewrite_rule();
    flush_rewrite_rules();

    global $wpdb;
    $tabla = $wpdb->prefix . 'empleados';
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $tabla (
        id INT AUTO_INCREMENT PRIMARY KEY,
        numero VARCHAR(100),
        nombre VARCHAR(255),
        puesto VARCHAR(255),
        departamento VARCHAR(255),
        estatus VARCHAR(100),
        foto VARCHAR(500)
    ) $charset;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 've_activar_plugin');

function ve_desactivar_plugin() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 've_desactivar_plugin');

// === SHORTCODE: VERIFICACIÓN DE EMPLEADO ===
function ve_verificar_empleado_shortcode() {
    global $wpdb;
    $tabla = $wpdb->prefix . 'empleados';
    $empleado_id = get_query_var('empleado_id');

    ob_start();

    if (!$empleado_id) {
        echo '<p style="text-align: center;">Escanee un código QR válido para ver la información del empleado.</p>';
        return ob_get_clean();
    }

    $empleado = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabla WHERE id = %d", $empleado_id));

    $plugin_url = plugin_dir_url(__FILE__);
    $foto_url = $empleado ? $empleado->foto : '';
    if ($empleado && strtolower($empleado->estatus) === 'inactivo') {
        $foto_url = $plugin_url . 'inactivo.png';
    } elseif ($empleado && empty($foto_url)) {
        $foto_url = $plugin_url . 'activo.png';
    }
    $fondo_url = $plugin_url . 'fondo.png';

    if (!$empleado) {
        echo '<p style="text-align: center; color: red;">Empleado no encontrado.</p>';
        return ob_get_clean();
    }

    ?>
    <head>
    <link rel="stylesheet" href="cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        @media (max-width:600px){
            .ve-container{font-size:16px;}
            .ve-photo img{max-width:100%;}
        }
    </style>
    </head>
    <div class="ve-container" style="max-width: 600px; width:100%; margin: 0 auto; text-align: center; font-family: sans-serif; font-size: 18px; padding:15px; box-sizing:border-box;">
    <h2 style="font-size: 26px;">Verificación de Empleado</h2>
    <div class="ve-photo" style="background:url('<?php echo esc_url($fondo_url); ?>') center/contain no-repeat; display:inline-block; padding:10px; border-radius:10px; margin-bottom:15px;">
        <img src="<?php echo esc_url($foto_url); ?>" style="max-width:180px; width:100%; height:auto; border-radius:10px;">
    </div>
    <p><strong required>Número de Empleado:</strong><br> <?php echo esc_html($empleado->numero); ?></p>
    <p><strong required>Nombre:</strong><br> <?php echo esc_html($empleado->nombre); ?></p>
    <p><strong required>Departamento:</strong><br> <?php echo esc_html($empleado->departamento); ?></p>
    <p><strong required>Puesto:</strong><br> <?php echo esc_html($empleado->puesto); ?></p>
    <p><strong required>Estado:</strong><br>
        <?php
        $color = strtolower($empleado->estatus) === 'activo' ? 'green' : 'red';
        echo "<span style='color:$color; font-weight:bold;'>" . esc_html($empleado->estatus) . "</span>";
        ?>
    </p>

    <?php if (strtolower($empleado->estatus) === 'inactivo') : ?>
        <div style='margin-top: 20px; padding: 15px; border: 1px solid #dc3545; border-radius: 10px; background-color: #f8d7da; color: #721c24; font-size: 16px;'>
            <p><strong>Advertencia:</strong> Este ciudadano ya no pertenece a la CAAMTH.</p>
        </div>
    <?php endif; ?>

    <div style='margin-top: 20px; padding: 15px; border: 1px solid #ccc; border-radius: 10px; background-color: #f1f1f1; color: #333; font-size: 16px;'>
        <p><strong>Importante:</strong></p>
        <p>Si detecta que la persona está haciendo mal uso de esta credencial o se ve involucrada en algún <strong>hecho de corrupción</strong>, <strong>comuníquelo</strong> al Órgano Interno de Control:</p>
        <p>
            📞 <a href="tel:7792226844" style="color:#007bff; text-decoration:underline;">779 222 6844</a><br>
            📧 <a href="mailto:oic.caamth@gmail.com" style="color:#007bff; text-decoration:underline;">oic.caamth@gmail.com</a>
        </p>
    </div>
</div>

    <?php

    return ob_get_clean();
}
add_shortcode('verificar_empleado', 've_verificar_empleado_shortcode');

// === SHORTCODE: FORMULARIO PARA AGREGAR EMPLEADO ===
function formulario_agregar_empleado() {
    global $wpdb;
    $tabla = $wpdb->prefix . 'empleados';

    // Cargar catalogo.js
    wp_enqueue_script('catalogo-js', plugin_dir_url(__FILE__) . 'catalogo.js', [], null, true);


    ob_start();

if (isset($_POST['guardar_empleado']) && check_admin_referer('guardar_empleado_action','guardar_empleado_nonce')) {

        if (
            // El formulario no permite campos vacíos, ya que, al momento de recargar la página, reenviaba la información previamente cargada (habría duplicidad de registros)

            strlen($_POST['numero_empleado']) >= 1&&
            strlen($_POST['nombre']) >=1&&
            strlen($_POST['puesto']) >=1&&
            strlen($_POST['departamento']) >=1&&
            strlen($_POST['estatus'])>=1

        ){

        $numero = sanitize_text_field($_POST['numero_empleado']);
        $nombre = sanitize_text_field($_POST['nombre']);
        $puesto = sanitize_text_field($_POST['puesto']);
        $departamento = sanitize_text_field($_POST['departamento']);
        $estatus = sanitize_text_field($_POST['estatus']);
        $foto_url = '';

        if (!empty($_FILES['foto']['name'])) {
            $file = $_FILES['foto'];
            $allowed_types = ['image/jpeg', 'image/png'];
            if (in_array($file['type'], $allowed_types)) {
                $upload = wp_upload_bits($file['name'], null, file_get_contents($file['tmp_name']));
                if (!$upload['error']) {
                    $foto_url = $upload['url'];
                } else {
                    echo "<p style='color:red;'>Error al subir la imagen: " . esc_html($upload['error']) . "</p>";
                }
            } else {
                echo "<p style='color:red;'>Solo se permiten archivos JPG o PNG.</p>";
            }
        }

        $wpdb->insert($tabla, [
            'numero' => $numero,
            'nombre' => $nombre,
            'puesto' => $puesto,
            'departamento' => $departamento,
            'estatus' => $estatus,
            'foto' => $foto_url
        ]);

        $empleado_id = $wpdb->insert_id;

        wp_redirect(add_query_arg([
            'exito' => 1,
            'id' => $empleado_id
        ], home_url($_SERVER['REQUEST_URI'])));

        } else {
            echo "<p>Favor de completar el formulario</p>";
        }

    }
    
    if (isset($_GET['exito']) && $_GET['exito'] == 1 && isset($_GET['id'])) {
        $empleado_id = intval($_GET['id']);
        $url = home_url("/verificar-empleado/$empleado_id/");

        echo '<p style="color: green; font-size: 18px;">Empleado agregado correctamente.</p>';
        echo "<p><strong>Enlace directo:</strong><br> <a href='$url' target='_blank'>$url</a></p>";
        echo "<p><strong>QR para verificar:</strong><br>";
        echo "<img src='https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=$url' alt='QR del empleado'></p>";
    }

    // Editar empleado

    if (isset($_POST['id_empleado']) && check_admin_referer('editar_empleado_action','editar_empleado_nonce')) {
        $id_edit = intval($_POST['id_empleado']);
        $nombre_edit = sanitize_text_field($_POST['nombre']);
        $departamento_edit = sanitize_text_field($_POST['edit_departamento']);
        $puesto_edit = sanitize_text_field($_POST['edit_puesto']);
        $estatus_edit = sanitize_text_field($_POST['edit_estatus']);
        $foto_act = $wpdb->get_row($wpdb->prepare("SELECT foto FROM $tabla WHERE id = %d", $id_edit));
        $foto_url_edit = $foto_act ? $foto_act->foto : '';

        // Si se marca la casilla, se elimina la foto actual del empleado

        if (!empty($_POST['remove_photo'])){

            // Extracción de la URL de la foto
            $foto_path = str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $foto_url_edit);

            // Si existe un archivo, se elimina

            if (file_exists($foto_path)){
                unlink($foto_path);
            }

            // Se borra la referencia en la base de datos

            $foto_url_edit = '';

        }

        // Revisión si se sube una nueva foto

        if (!empty($_FILES['edit_foto']['name'])) {
            $file = $_FILES['edit_foto'];
            $allowed_types = ['image/jpeg', 'image/png'];

            if (in_array($file['type'], $allowed_types)) {
                $upload = wp_upload_bits($file['name'], null, file_get_contents($file['tmp_name']));
                if (!$upload['error']) {
                    $foto_url_edit = esc_url_raw($upload['url']);
                } else {
                    echo "<p style='color:red;'>Error al subir la imagen: " . esc_html($upload['error']) . "</p>";
                }
            } else {
                echo "<p> style='color:red;'>Solo se permiten archivos JPG o PNG.</p>";
            }
        }

        $wpdb->update(
            $tabla,
            [
                'nombre' => $nombre_edit,
                'departamento' => $departamento_edit,
                'puesto' => $puesto_edit,
                'estatus' => $estatus_edit,
                'foto' => $foto_url_edit
            ],
            ['id' => $id_edit]
        );

        // Se redirige para evitar confirmación del formulario (evitar registros duplicados)

        wp_redirect(home_url(add_query_arg(['actualizado' => 1])));

    }

    ?>
    <form method="post" enctype="multipart/form-data" style="max-width: 600px; margin: 0 auto; font-size: 18px;">
    <?php wp_nonce_field("guardar_empleado_action","guardar_empleado_nonce"); ?>
        <input type="text" name="numero_empleado" placeholder="Número de empleado" required><br><br>
        <input type="text" name="nombre" placeholder="Nombre completo" required><br><br>

        <label for="departamento">Departamento:</label><br>
        <select name="departamento" id="departamento" required>
            <option value="">Seleccione un departamento</option>
        </select><br><br>

        <label for="puesto">Puesto:</label><br>
        <select name="puesto" id="puesto" required>
            <option value="">Seleccione un puesto</option>
        </select><br><br>

        <label for="estatus">Estatus:</label><br>
        <select name="estatus" required>
            <option value="Activo">Activo</option>
            <option value="Inactivo">Inactivo</option>
        </select><br><br>

        <label>Foto del empleado:</label><br>
        <input type="file" name="foto" accept="image/jpeg,image/png"><br><br>
        <button type="submit" name="guardar_empleado" style="font-size: 18px;">Guardar Empleado</button>
    </form><br>

<?php 

$total_empleados = $wpdb->get_var("SELECT COUNT(*) FROM $tabla");
$total_empleados_activos = $wpdb->get_var("SELECT COUNT(*) FROM $tabla WHERE estatus = 'Activo'");
$total_empleados_inactivos = $wpdb->get_var("SELECT COUNT(*) FROM $tabla WHERE estatus = 'Inactivo'");
$empleados_lista = $wpdb->get_results("SELECT * FROM $tabla ORDER BY numero", ARRAY_A);
if ($empleados_lista !== null) {
    echo '<div style="display:flex; gap:15px; flex-wrap:wrap; margin-bottom:20px;">';
    echo '<div style="flex:1; min-width:150px; border:1px solid #ccc; padding:10px; border-radius:6px;">Total de empleados: ' . esc_html($total_empleados) . '</div>';
    echo '<div style="flex:1; min-width:150px; border:1px solid #ccc; padding:10px; border-radius:6px;">Empleados activos: ' . esc_html($total_empleados_activos) . '</div>';
    echo '<div style="flex:1; min-width:150px; border:1px solid #ccc; padding:10px; border-radius:6px;">Empleados inactivos: ' . esc_html($total_empleados_inactivos) . '</div>';
    echo '</div>';
} else {
    echo "Error en la consulta";
}

?>

<!-- Búsqueda de empleados -->

    <input type="text" id="search-input" placeholder="Buscar por nombre o número de empleado" style="margin-top: 20px; padding: 8px; font-size: 16px; width: 325px;"><br>
    <div id="search-results"></div>

    <style>

        /* Estilo para el combobox de departamentos */

        .combo-container {
            position: relative;
            display: inline-block;
        }

        .combo-toggle {            
            padding: 10px 15px;
            border: 1px solid #ccc;
            cursor: pointer;
            background-color: #282878;
            border-radius: 5px;
            font-weight: bold;
            color: #ffffff;
        }

        .combo-toggle:hover {
            background-color: #121227;
        }

        .combo-options {
            display: none;
            position: absolute;
            top: 110%;
            left: 0;
            background-color: white;
            border: 1px solid #ccc;
            border-radius: 5px;
            padding: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            z-index: 1000;
            color: #ffffff;
        }

        .combo-options button {
            display: block;
            width: 100%;
            margin: 5px 0;
            padding: 8px;
            border: none;
            background-color: #282878;
            border-radius: 3px;
            cursor: pointer;
            text-align: left;
            color: #ffffff;
        }

        .combo-options button:hover {
            background-color: #121227;
        }

    </style>

<!-- Lista de botones para filtrar por departamento -->

<div class="combo-container">
    
    <div class="combo-toggle" onClick="toggleCombo()">Filtrar por departamento</div>

        <div class="combo-options" id="combo-options">
            <button onClick="filtrarPorDepartamento('Todos')">Todos los departamentos</button>
            <button onClick="filtrarPorDepartamento('Dirección General')">Dirección General</button>
            <button onClick="filtrarPorDepartamento('Gerencia de Atención a Usuarios')">Gerencia de Atención a Usuarios</button>
            <button onClick="filtrarPorDepartamento('Gerencia de Hacienda Pública')">Gerencia de Hacienda Pública</button>
            <button onClick="filtrarPorDepartamento('Gerencia de Infraestructura y Patrimonio Hídrico')">Gerencia de Infraestructura y Patrimonio Hídrico</button>
            <button onClick="filtrarPorDepartamento('Subdirección General Jurídica')">Subdirección General Jurídica</button>
            <button onClick="filtrarPorDepartamento('Subdirección General de Planeación y Proyectos Estratégicos')">Subdirección General de Planeación y Proyectos Estratégicos</button>
            <button onClick="filtrarPorDepartamento('Órgano Interno de Control')">Órgano Interno de Control</button>
        </div>

</div><br>

<style> /* Estilo para el combobox de estatus */

    .combo-container-status {
        position: relative;
        display: inline-block;
    }

    /* Botón para desplegar las opciones del combo */

    .combo-toggle-status {
        padding: 10px 15px;
        border: 1px solid #ccc;
        cursor: pointer;
        background-color: #282878;
        border-radius: 5px;
        font-weight: bold;
        color: #ffffff;
    }

    .combo-toggle-status:hover {
        background-color: #121227;
    }

    /* Contenedor de los botones de filtro por estatus */

    .status-options {
        display: none;
        position: absolute;
        top: 110%;
        left: 0;
        background-color: #ffffff;
        border: 1px solid #ccc;
        border-radius: 5px;
        padding: 10px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        z-index: 1000;
        color: #ffffff;
    }

    /* Configuración general de los botones */

    .status-options button {
        display: block;
        width: 100%;
        margin: 5px 0;
        padding: 8px;
        border: none;
        border-radius: 3px;
        cursor: pointer;
        text-align: left;
        color: #ffffff;
    }

    /* Botón de filtro general */

    button.general {
        background-color: #282878;
    }

    button.general:hover {
        background-color: #121227;
    }

    /* Botón de filtro activo */

    button.activo {
        background-color: #92d637;
    }

    button.activo:hover {
        background-color: #659a27;
    }

    /* Botón de filtro inactivo */

    button.inactivo {
        background-color: #E62645;
    }

    button.inactivo:hover {
        background-color: #6F1132;
    }

</style>

<!-- Lista de botones para filtrar por estatus -->

<div class="combo-container-status">
    <div class="combo-toggle-status" onClick="toggleComboStatus()">Filtrar por estatus</div>

    <div class="status-options" id="combo-status">
        <button class="general" onClick="filtrarPorEstatus('General')">General</button>
        <button class="activo" onClick="filtrarPorEstatus('Activo')">Activo</button>
        <button class="inactivo" onClick="filtrarPorEstatus('Inactivo')">Inactivo</button>
    </div>

</div>

<br>

<style> /* Estilo para la tabla de empleados */

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    font-family: Arial, sans-serif;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    border-radius: 10px;
    overflow: hidden;
}

/* Encabezado */
thead {
    background-color: #282878; 
    color: white;
}

thead th {
    padding: 12px 15px;
    text-align: left;
    font-weight: bold;
}

/* Filas de datos */
tbody tr {
    border-bottom: 1px solid #ddd;
    transition: background-color 0.3s;
}

tbody tr:hover {
    background-color: #f1f1f1;
}

/* Celdas */
td {
    padding: 12px 15px;
}

/* Botones de acción */
button {
    padding: 6px 12px;
    margin-right: 5px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: background-color 0.3s, transform 0.2s;
}

button:hover {
    transform: scale(1.05);
}

/* Botón ver */

.button-see{
    background-color: #282878;
    color: white;
}

.button-see:hover{
    background-color: #121227;
}

/* Botón editar */
button[title="Editar empleado"] {
    background-color: #C09B57;
    color: white;
}

button[title="Editar empleado"]:hover {
    background-color: #937847;
}

/* Botón eliminar */
.button-delete {
    margin-bottom: 1px;
    background-color: #E62645;
    color: white;
}

.button-delete:hover {
    background-color: #6F1132;
}

/* Botón descargar */

.button-download {
    padding: 6px 12px;
    margin-right: 5px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    text-decoration: none;
    background-color: #007bff;
    color: white;
    text-decoration: none;
    
}

.button-download a:hover {
    background-color: #0056b3;
}

</style>

<table>

    <thead>
        <tr>
            <th>Número de empleado</th>
            <th>Nombre del empleado</th>
            <th>Departamento</th>
            <th>Puesto</th>
            <th>Estatus</th>
            <th>Acciones</th>
        </tr>
    </thead>

<tbody>
<!-- Contenido traído desde la base de datos -->
<?php foreach ($empleados_lista as $mostrar_empleados) { ?>
            <tr>
                <td><?php echo esc_html($mostrar_empleados['numero']); ?></td>
                <td><?php echo esc_html($mostrar_empleados['nombre']); ?></td>
                <td><?php echo esc_html($mostrar_empleados['departamento']); ?></td>
                <td><?php echo esc_html($mostrar_empleados['puesto']); ?></td>
                <td><?php echo esc_html($mostrar_empleados['estatus']); ?></td>
                <td>
                    <?php
                        $json_empleado = htmlspecialchars(json_encode($mostrar_empleados), ENT_QUOTES, 'UTF-8');
                        $id_empleado = intval($mostrar_empleados['id']);
                        $numero_empleado = intval($mostrar_empleados['numero']);
                        $nombre_empleado = htmlspecialchars($mostrar_empleados['nombre']);
                        $ver_url_empleado = esc_url(home_url("/verificar-empleado/$id_empleado/"));
                        $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=$ver_url_empleado";
                    ?>
                    <button type="button" class="button-see" onClick="window.open('<?php echo $ver_url_empleado; ?>', '_blank')">Ver</button>
                    <button type="button" title="Editar empleado" onclick="openEditModal(<?php echo $json_empleado; ?>)">Editar</button>
                    <button type="button" class="button-delete" data-id="<?php echo $id_empleado; ?>">Eliminar</button>
                    <button type="button" class="button-download" onClick="descargarQR('<?php echo $qr_url; ?>', 'qr_<?php echo $nombre_empleado?>_<?php echo $numero_empleado; ?>.png')">QR</button>
                </td>
            </tr>
<?php } ?>

</tbody>

</table>

<!-- Modal para editar usuarios -->

<style>

/* Estilo para el modal de editar usuario */

#editEmployeeModal {
    display: none;
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
    padding: 30px;
    z-index: 9999;
    width: 90%;
    max-width: 500px;
    font-family: Arial, sans-serif;
    max-height: 90vh;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}

/* Encabezado de los inputs */

#editEmployeeModal label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
    color: #333;
}

/* Inputs */

#editEmployeeModal input[type="text"],
#editEmployeeModal select {
    width: 100%;
    min-width: 200px;
    max-width: 100%;
    box-sizing: border-box;
    padding: 8px 10px;
    margin-bottom: 15px;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 14px;
}

/* Botones para guardar y cancelar cambios */

#editEmployeeModal button {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    margin-right: 10px;
    font-size: 14px;
    cursor: pointer;
}

#editEmployeeModal button[type="submit"] {
    background-color: #282878;
    color: white;
}

#editEmployeeModal button[type="submit"]:hover{
    background-color: #121227;
}

#editEmployeeModal button[type="button"] {
    background-color: #E62645;
    color: white;
}

#editEmployeeModal button[type="button"]:hover{
    background-color: #6F1132;
}

/* Contenedor para mostrar las fotos a manera de columna */

.photo-preview-container {
    display: flex;
    gap: 20px;
    margin-bottom: 15px;
}

/* Bloque de cada foto */

.photo-block {
    display: flex;
    flex-direction: column;
    align-items: center;
}

/* Tamaño de la imagen consistente */

.photo-block img {
    max-width: 150px;
    border-radius: 8px;
    margin-top: 5px;
}

/* Botón para cargar nueva foto */

.photo-update {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    margin-right: 10px;
    font-size: 14px;
    cursor: pointer;
    background-color: #0056b3;
    color: white;
    align-items: center;
}

@media (max-width: 600px){
    #editEmployeeModal input[type="text"],
    #editEmployeeModal select {
        width: 100%;
    }
}

</style>

<div id="editEmployeeModal" style="display: none;">
    <form action="" id="editEmployeeForm" method="POST" enctype="multipart/form-data">
        <?php wp_nonce_field("editar_empleado_action","editar_empleado_nonce"); ?>
        <input type="hidden" name="id_empleado" id="edit_id_empleado" class="nombre-empleado">
        <label>Nombre completo: </label><input type="text" name="nombre" id="edit_nombre"><br>
        <label for="edit_departamento">Departamento: </label>
        <select name="edit_departamento" id="edit_departamento">
            <option value="">Editar departamento</option>
        </select><br>
        <label for="edit_puesto">Puesto: </label>
        <select name="edit_puesto" id="edit_puesto">
            <option value="">Editar puesto</option>
        </select><br>
        <label for="edit_estatus">Estatus: </label>
        <select name="edit_estatus" id="edit_estatus">
            <option value="Activo">Activo</option>
            <option value="Inactivo">Inactivo</option>
        </select><br>
        <div class="photo-preview-container">
            <div class="photo-block">
                <label for="edit_foto">Foto actual</label>
                <img id="edit_foto_preview" src="" alt="Foto del empleado" style="max-width: 150px; display: none; margin-bottom: 10px;">
                <div id="remove_photo_container" style="display: none; margin-top: 5px;">
                    <label for="remove_photo_container" class="delete_foto">
                        <input type="checkbox" name="remove_photo" id="remove_photo">
                        Eliminar foto
                    </label>
                </div>
            </div>
            <div class="photo-block">
                <label id="new_foto_label" style="display:none;">Nueva foto a actualizar</label>
                <img id="new_foto_preview" src="" alt="Nueva foto del empleado" style="max-width: 150px; display: none; margin-bottom: 10px;">
            </div>
        </div>
        <label for="edit_foto">Cargar nueva foto:</label>
        <input type="file" name="edit_foto" id="edit_foto" accept="image/jpeg, image/png" class="photo-update"><br><br>
            <button type="submit">Guardar cambios</button>
            <button type="button" onClick="closeEditModal()">Cancelar</button>
    </form>
</div>

    <script> // Llenar el select de departamento y puesto cuando se crea un usuario 
        document.addEventListener("DOMContentLoaded", function() {
            const catalogo = window.catalogo || {};
            const departamentoSelect = document.getElementById("departamento");
            const puestoSelect = document.getElementById("puesto");

            // Rellenar departamentos
            Object.keys(catalogo).forEach(dep => {
                const option = document.createElement("option");
                option.value = dep;
                option.textContent = dep;
                departamentoSelect.appendChild(option);
            });

            // Cuando cambia el departamento
            departamentoSelect.addEventListener("change", function() {
                const puestos = catalogo[this.value] || [];
                puestoSelect.innerHTML = '<option value="">Seleccione un puesto</option>';
                puestos.forEach(puesto => {
                    const opt = document.createElement("option");
                    opt.value = puesto;
                    opt.textContent = puesto;
                    puestoSelect.appendChild(opt);
                });
            });
        });
    </script>

    <script> // Modal para editar empleado
    
    // Se muestra la vista previa de la imagen que se colocará para actualizar la foto del empleado

    document.getElementById('edit_foto').addEventListener('change', function(event) {
        const file = event.target.files[0];
        const preview = document.getElementById('new_foto_preview');
        const label = document.getElementById('new_foto_label');

        if (file && file.type.startsWith('image/')){
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
                label.style.display = 'block';
            };
            reader.readAsDataURL(file);
        } else {
            preview.style.display = 'none';
            label.style.display = 'none';
            preview.src = '';
        }
    });

        function openEditModal(empleado){

            document.getElementById('edit_id_empleado').value = empleado.id || ''; // Se asigna el valor al modal
            document.getElementById('edit_nombre').value = empleado.nombre || '';
            document.getElementById('edit_departamento').value = empleado.departamento || '';
            document.getElementById('edit_puesto').value = empleado.puesto || '';
            document.getElementById('edit_estatus').value = empleado.estatus || '';

            // Vista de la foto del trabajador

            const preview = document.getElementById('edit_foto_preview');
            const removePhotoContainer = document.getElementById('remove_photo_container');
            const removePhotoCheckbox = document.getElementById('remove_photo');

            if (empleado.foto && empleado.foto.trim() !== "") {
                preview.src = empleado.foto;
                preview.style.display = 'block';
                removePhotoContainer.style.display = 'block';
                removePhotoCheckbox.checked = false; // Se reinicia el checkbox 
            } else {
                preview.style.display = 'none';
                removePhotoContainer.style.display = 'none';
            }

            document.getElementById('edit_departamento').dispatchEvent(new Event('change'));
            document.getElementById('editEmployeeModal').style.display = 'block';

            const departamentoEdit = document.getElementById('edit_departamento');
            const puestoSelect = document.getElementById('edit_puesto');

            departamentoEdit.value = empleado.departamento; // Se asigna el valor del departamento al usuario

            const changeEvent = new Event('change');
            departamentoEdit.dispatchEvent(changeEvent);

            puestoSelect.value = empleado.puesto;

        }

        function closeEditModal(){
            document.getElementById('editEmployeeModal').style.display = 'none';

            // Se limpia la vista previa de la nueva imagen

            document.getElementById('edit_foto').value = '';
            document.getElementById('new_foto_preview').style.display = 'none';
            document.getElementById('new_foto_preview').src = '';
            document.getElementById('new_foto_label').style.display = 'none';
            removePhotoCheckbox.checked = false;
            removePhotoContainer.style.display = 'none';
        }

    </script>

    <script>

        // Filtro por departamentos

    function filtrarPorDepartamento(departamento){
        const rows = document.querySelectorAll("tbody tr");

        rows.forEach(row => {
            const cell = row.cells[2]; // Selecciona la columna dentro de la tabla creada en HTML || Indice 2 = Columna "departamento"
            const cellDep = cell.textContent.trim();

            if (departamento === "Todos" || cellDep === departamento) { // Si se presiona todos los departamentos, no se aplica filtro alguno
                row.style.display = "";
            } else {
                row.style.display = "none"; // Para el resto de departamentos
            }

        });

    }
    </script>

    <script>

        // Función para desplegar y replegar el combobox

        function toggleCombo(){
            const menu = document.getElementById('combo-options');
            menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
        }

        document.addEventListener('click', function (e) {
            const container = document.querySelector('.combo-container');

            if (!container.contains(e.target)) {
                document.getElementById('combo-options').style.display = 'none';
            }
        });
    </script>

    <script> // Filtro por estatus

    function filtrarPorEstatus(estatus){
        const rows = document.querySelectorAll("tbody tr");

        rows.forEach(rowEstatus => {
            const cell = rowEstatus.cells[4]; // Columna del estatus (dentro del array es el índice 4)
            const cellEstatus = cell.textContent.trim();

            if (estatus === "General" || cellEstatus === estatus){
                rowEstatus.style.display = "";
            } else {
                rowEstatus.style.display = "none"; 
            }
        });
    }

    </script>

    <script> // Desplegar y replegar el combobox de estatus
    
    function toggleComboStatus(){
        const menuEstatus = document.getElementById('combo-status');
        menuEstatus.style.display = menuEstatus.style.display === 'block' ? 'none' : 'block';
    }

    document.addEventListener('click', function (e) {
        const contenedorEstatus = document.querySelector('.combo-container-status');

        if (!contenedorEstatus.contains(e.target)){
            document.getElementById('combo-status').style.display = 'none';
        }
    });
    </script>

    <script>
        
        // Búsqueda de usuarios por nombre o número de empleado

        document.addEventListener('DOMContentLoaded', function() {
            const input = document.getElementById("search-input");

            input.addEventListener("keyup", function() {
                const query = input.value;

                fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=buscar_empleado&query='+encodeURIComponent(query)). // Consulta para seleccionar el usuario
                then(response => response.text())
                .then(data => {
                    const tbody = document.querySelector("tbody");
                    tbody.innerHTML = data;
                });
            });
        });
    </script>

    <script> // Eliminar usuario
    document.addEventListener('DOMContentLoaded', function () {
        document.addEventListener('click', function (e) {
            if (e.target.classList.contains('button-delete')){
                const id = e.target.dataset.id;

                if (confirm('¿Desea eliminar este empleado?')) {
                    fetch('<?php echo admin_url('admin-ajax.php');?>?action=ve_eliminar_empleado&id=' + encodeURIComponent(id), {
                        method: 'POST'
                    })
                    .then(res => res.text())
                    .then(response => {
                        const row = e.target.closest('tr');
                        if (row){
                            row.remove();
                        }
                    }).catch(error => {
                        console.error('Error al eliminar:', error);
                    });
                }
            }
        });
    });

    </script>

    <script> // Función que permite la descarga del código QR
        function descargarQR(url, filename){
            const img = new Image(); // En producción, se debe generar una imagen para poder realizar la descarga del QR
            img.crossOrigin = "Anonymous";
            img.onload = function() {
                const canvas = document.createElement('canvas');
                canvas.width = img.width;
                canvas.height = img.height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0);
                canvas.toBlob(function(blob) {
                    const blobUrl = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = blobUrl;
                    a.download = filename;
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    URL.revokeObjectURL(blobURL);
                }, 'image/png');
            };

            img.onerror = function() {
                console.error('No se pudo cargar la imagen del QR');
            };

            img.src = url + '&cache_buster=' + new Date().getTime(); // No se cachea 
            
        }
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('agregar_empleado', 'formulario_agregar_empleado');

// === MANEJADOR AJAX PARA BÚSQUEDA DE EMPLEADOS ===
add_action('wp_ajax_buscar_empleado', 've_buscar_empleado');
add_action('wp_ajax_nopriv_buscar_empleado', 've_buscar_empleado');

function ve_buscar_empleado() {
    global $wpdb;
    $tabla = $wpdb->prefix . 'empleados';

    $query = isset($_GET['query']) ? sanitize_text_field($_GET['query']) : '';

    // Buscar por número o nombre (parcial)
    $resultados = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $tabla WHERE numero LIKE %s OR nombre LIKE %s ORDER BY numero",
        '%' . $query . '%',
        '%' . $query . '%'
    ));

    if ($resultados) {
        foreach ($resultados as $empleado) {
            $json_empleado = json_encode($empleado, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
            $id_empleado = intval($empleado->id);
            $numero_empleado = intval($empleado->numero);
            $nombre_empleado = htmlspecialchars($empleado->nombre);
            $ver_url_empleado = esc_url(home_url("/verificar-empleado/$id_empleado"));
            $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($ver_url_empleado);
            $qr_filename = "qr_" .$nombre_empleado . "_" . $numero_empleado . ".png";
            echo '<tr>';
            echo '<td>' . esc_html($empleado->numero) . '</td>';
            echo '<td>' . esc_html($empleado->nombre) . '</td>';
            echo '<td>' . esc_html($empleado->departamento) . '</td>';
            echo '<td>' . esc_html($empleado->puesto) . '</td>';
            echo '<td>' . esc_html($empleado->estatus) . '</td>';
            echo '<td> 
                    <button type="button" class="button-see" onClick="window.open(\''. esc_url(home_url("/verificar-empleado/". $empleado->id. "/")).'\', \'_blank\')">Ver</button>
                    <button type="button" title="Editar empleado" onclick=\'openEditModal(' . $json_empleado . ')\'>Editar</button>
                    <button type="button" class="button-delete" data-id="' . esc_attr($empleado->id) . '">Eliminar</button>
                    <button type="button" class="button-download" onclick="descargarQR(\'' . esc_url($qr_url) . '\', \'' . esc_attr($qr_filename) . '\')">QR</button>
                  </td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="6" style="text-align:center;">No se encontraron resultados</td></tr>';
    }

    wp_die(); // Finaliza la petición AJAX correctamente
}

// === Acción de eliminar empleado vía AJAX ===

add_action('wp_ajax_ve_eliminar_empleado', 've_eliminar_empleado');
add_action('wp_ajax_nopriv_ve_eliminar_empleado', 've_eliminar_empleado');

function ve_eliminar_empleado(){
    global $wpdb;
    $tabla = $wpdb->prefix . 'empleados';
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($id > 0) {
        $wpdb->delete($tabla, ['id' => $id]);
        echo 'Empleado eliminado correctamente';
    } else {
        echo 'ID inválido';
    }

    wp_die();
}






