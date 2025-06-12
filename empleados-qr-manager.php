<?php
/**
 * Plugin Name: Verificador de Empleados
 * Description: Plugin para verificar empleados por ID a través de una URL con código QR. Usa URLs como /verificar-empleado/123.
 * Version: 1.4.0
 * Author: Luis Fernando Lizardi Mejía | Manuel Saldivar Martínez
 */

// === SESIÓN Y CONSTANTES DE LOGIN ===

define('VE_LOGIN_CREDENTIALS', [
    'RH' => '&>1yBj|h5M7\\',
    'Diseño' => 'ANdymAorCe',
    'Sistemas' => 'loSeNUsHOl'
]);


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

    add_role('rol_idcaamth', 'ID CAAMTH', ['read' => true]);

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

    // Crear usuarios predeterminados si no existen
    foreach (VE_LOGIN_CREDENTIALS as $user => $pass) {
        if (!username_exists($user)) {
            wp_insert_user([
                'user_login' => $user,
                'user_pass'  => $pass,
                'role'       => 'rol_idcaamth',
            ]);
        }
    }
}
register_activation_hook(__FILE__, 've_activar_plugin');

function ve_desactivar_plugin() {
    flush_rewrite_rules();
    remove_role('rol_idcaamth');
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

        .ve-photo{
            position: relative;
        }

        .ve-photo::before{
            content: "";
            position: absolute;
            inset: 0;
            background-image: url('<?php echo esc_url($fondo_url); ?>');
            background-size: 100% 100%;
            background-position: center bottom;
            background-repeat: no-repeat;
            opacity: 0.6;
            z-index: 0;
        }

        .ve-photo img{
            position: relative;
            z-index: 1;
        }
    </style>
    </head>
    <div class="ve-container" style="max-width: 600px; width:100%; margin: 0 auto; text-align: center; font-family: sans-serif; font-size: 18px; padding:15px; box-sizing:border-box;">
    <h2 style="font-size: 26px;">Verificación de Empleado</h2>
    <div class="ve-photo" style="display:inline-block; padding:20px; border-radius:10px; margin-bottom:15px;">
        <img id="ve-img" src="<?php echo esc_url($foto_url); ?>" style="max-width:180px; width:100%; height:auto; border-radius:10px;">
    </div>
    <p><strong required>Número de Empleado:</strong><br> <span id="ve-numero"><?php echo esc_html($empleado->numero); ?></span></p>
    <p><strong required>Nombre:</strong><br> <span id="ve-nombre"><?php echo esc_html($empleado->nombre); ?></span></p>
    <p><strong required>Departamento:</strong><br> <span id="ve-departamento"><?php echo esc_html($empleado->departamento); ?></span></p>
    <p><strong required>Puesto:</strong><br> <span id="ve-puesto"><?php echo esc_html($empleado->puesto); ?></span></p>
    <p><strong required>Estado:</strong><br>
        <?php
        $color = strtolower($empleado->estatus) === 'activo' ? 'green' : 'red';
        echo "<span id='ve-estatus' style='color:$color; font-weight:bold;'>" . esc_html($empleado->estatus) . "</span>";
        ?>
    </p>

    <?php if (strtolower($empleado->estatus) === 'inactivo') : ?>
        <div id="ve-status-box" style='margin-top:20px;padding:15px;border-radius:10px;background-color:#ff4d4d;border:1px solid #ff9999;color:#fff;font-size:16px;'>
            <p><strong>Advertencia: Este ciudadano ya no pertenece a la CAAMTH.</strong></p>
        </div>
    <?php else : ?>
        <div id="ve-status-box" style='margin-top:20px;padding:15px;border-radius:10px;background-color:#008000;color:#fff;font-size:16px;'>
            <p><strong>Este ciudadano pertenece a la CAAMTH.</strong></p>
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

<script>
document.addEventListener('DOMContentLoaded', function(){
    const empId = <?php echo intval($empleado->id); ?>;
    const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';

    function refreshData(){
        fetch(ajaxUrl + '?action=ve_get_empleado&id=' + empId)
            .then(res => res.json())
            .then(data => {
                if(data && data.id){
                    document.getElementById('ve-numero').textContent = data.numero;
                    document.getElementById('ve-nombre').textContent = data.nombre;
                    document.getElementById('ve-departamento').textContent = data.departamento;
                    document.getElementById('ve-puesto').textContent = data.puesto;
                    const statusEl = document.getElementById('ve-estatus');
                    statusEl.textContent = data.estatus;
                    const color = data.estatus.toLowerCase() === 'activo' ? 'green' : 'red';
                    statusEl.style.color = color;
                    const box = document.getElementById('ve-status-box');
                    if (data.estatus.toLowerCase() === 'inactivo') {
                        box.style.backgroundColor = '#ff4d4d';
                        box.style.border = '1px solid #ff9999';
                        box.style.color = '#fff';
                        box.innerHTML = '<p><strong>Advertencia: Este ciudadano ya no pertenece a la CAAMTH.</strong></p>';
                    } else {
                        box.style.backgroundColor = '#008000';
                        box.style.border = 'none';
                        box.style.color = '#fff';
                        box.innerHTML = '<p><strong>Este ciudadano pertenece a la CAAMTH.</strong></p>';
                    }
                    let foto = data.foto;
                    if(data.estatus.toLowerCase() === 'inactivo'){
                        foto = '<?php echo $plugin_url; ?>inactivo.png';
                    }else if(!foto){
                        foto = '<?php echo $plugin_url; ?>activo.png';
                    }
                    document.getElementById('ve-img').src = foto;
                }
            });
    }

    setInterval(refreshData, 5000);
});
</script>

    <?php

    return ob_get_clean();
}
add_shortcode('verificar_empleado', 've_verificar_empleado_shortcode');

// === SHORTCODE: FORMULARIO DE LOGIN ===
add_shortcode('ve_login', function () {
    if (is_user_logged_in()) {
        wp_redirect(home_url('/agregar-empleado'));
        exit;
    }

    $plugin_url = plugin_dir_url(__FILE__);
    $logo_url   = $plugin_url . 'logo.png';

    $args = array(
        'echo'           => false,
        'redirect'       => home_url('/agregar-empleado'),
        'form_id'        => 've-login-form',
        'label_username' => __('Usuario'),
        'label_password' => __('Contraseña'),
        'label_remember' => __('Recordarme'),
        'label_log_in'   => __('Iniciar Sesión'),
        'remember'       => true,
    );

    $form = wp_login_form($args);

    // Añadir clases personalizadas al formulario y sus campos
    $form = str_replace(
        '<form name="loginform"',
        '<form name="loginform" class="login-form"',
        $form
    );
    $form = str_replace(
        'id="user_login"',
        'id="user_login" class="login-input"',
        $form
    );
    $form = str_replace(
        'id="user_pass"',
        'id="user_pass" class="login-input"',
        $form
    );
    $form = str_replace(
        'id="wp-submit"',
        'id="wp-submit" class="login-submit"',
        $form
    );

    ob_start();
    ?>
    <div class="login-wrapper">
        <img src="<?php echo esc_url($logo_url); ?>" alt="Logo" class="login-logo" />
        <h2 class="login-title"><strong>ID-Caamth</strong></h2>
        <?php echo $form; ?>
    </div>
    <style>
        .login-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            box-sizing: border-box;
            background: #f0f0f0;
        }
        .login-logo {
            max-width: 180px;
            width: 100%;
            height: auto;
            margin: 0 auto 10px;
            display: block;
        }
        .login-title {
            margin-bottom: 20px;
            font-size: 18px;
            text-align: center;
        }
        .login-form {
            background: rgba(255, 255, 255, 0.9);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            max-width: 400px;
            width: 100%;
            text-align: center;
        }
        .login-form label {
            display: block;
            text-align: left;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .login-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            margin-bottom: 15px;
            box-sizing: border-box;
            transition: border-color .3s;
        }
        .login-input:focus {
            border-color: #282878;
            outline: none;
        }
        .login-submit {
            width: 100%;
            background: #282878;
            color: #fff;
            border: none;
            border-radius: 5px;
            padding: 10px 0;
            cursor: pointer;
            transition: background .3s;
        }
        .login-submit:hover {
            background: #121227;
        }
        .login-form .forgetmenot {
            margin-bottom: 15px;
            text-align: left;
        }
        @media (max-width: 600px) {
            .login-form {
                padding: 20px;
            }
        }
    </style>
    <?php
    return ob_get_clean();
});

// === SHORTCODE: FORMULARIO PARA AGREGAR EMPLEADO ===
function formulario_agregar_empleado() {
    if (!is_user_logged_in()) {
        $redirect = home_url($_SERVER['REQUEST_URI']);
        wp_redirect(wp_login_url($redirect));
        exit;
    }
    global $wpdb;
    $tabla = $wpdb->prefix . 'empleados';

    // Cargar catálogos
    wp_enqueue_script('catalogo-departamentos-js', plugin_dir_url(__FILE__) . 'catalogo_departamentos.js', [], null, true);
    wp_enqueue_script('catalogo-puestos-js', plugin_dir_url(__FILE__) . 'catalogo_puestos.js', [], null, true);

    ob_start();

    if (isset($_GET['ve_logout'])) {
        wp_logout();
        wp_redirect(wp_login_url());
        exit;
    }

    echo '<p style="text-align:right;"><a href="?ve_logout=1">Cerrar sesión</a></p>';

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
        echo "<img class='qr-code-img' src='https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=$url' alt='QR del empleado'></p>";
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
<?php     if (isset($_POST['cargar_masivo']) && check_admin_referer('ve_carga_masiva_action','ve_carga_masiva_nonce')) {
        if (!empty($_FILES['archivo_empleados']['tmp_name'])) {
            $rows = ve_parse_simple_xlsx($_FILES['archivo_empleados']['tmp_name']);
            if ($rows) {
                $encabezados = ["Número de empleado","Nombre","Departamento","Puesto","Estatus"];
                $valid = true;
                foreach ($encabezados as $i => $enc) {
                    if (strtolower($rows[0][$i] ?? '') !== strtolower($enc)) {
                        $valid = false;
                        break;
                    }
                }
                if ($valid) {
                    unset($rows[0]);
                    foreach ($rows as $fila) {
                        if (count($fila) < 5) continue;
                        $numero = sanitize_text_field($fila[0]);
                        $nombre = sanitize_text_field($fila[1]);
                        $departamento = sanitize_text_field($fila[2]);
                        $puesto = sanitize_text_field($fila[3]);
                        $estatus = sanitize_text_field($fila[4]);
                        if ($numero && $nombre && $departamento && $puesto && $estatus) {
                            $wpdb->insert($tabla, [
                                'numero' => $numero,
                                'nombre' => $nombre,
                                'departamento' => $departamento,
                                'puesto' => $puesto,
                                'estatus' => $estatus,
                                'foto' => ''
                            ]);
                        }
                    }
                    echo "<p style='color:green;'>Carga masiva completada.</p>";
                } else {
                    echo "<p style='color:red;'>Encabezados del archivo inválidos.</p>";
                }
            } else {
                echo "<p style='color:red;'>Archivo no válido.</p>";
            }
        } else {
            echo "<p style='color:red;'>Seleccione un archivo .xlsx.</p>";
        }
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
$empleados_lista = $wpdb->get_results("SELECT * FROM $tabla ORDER BY CAST(numero AS UNSIGNED)", ARRAY_A);
if ($empleados_lista !== null) {
    echo '<div style="display:flex; gap:15px; flex-wrap:wrap; margin-bottom:20px;">';
    echo '<div style="flex:1; min-width:150px; border:1px solid #ccc; padding:10px; border-radius:6px;">Total de empleados: ' . esc_html($total_empleados) . '</div>';
    echo '<div style="flex:1; min-width:150px; border:1px solid #ccc; padding:10px; border-radius:6px;">Empleados activos: ' . esc_html($total_empleados_activos) . '</div>';
    echo '<div style="flex:1; min-width:150px; border:1px solid #ccc; padding:10px; border-radius:6px;">Empleados inactivos: ' . esc_html($total_empleados_inactivos) . '</div>';
    echo '</div>';
} else {
    echo "Error en la consulta";
}

// Pasar datos de QR de todos los empleados a JavaScript
$empleados_data = [];
if ($empleados_lista) {
    foreach ($empleados_lista as $e) {
        $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . rawurlencode(home_url("/verificar-empleado/".$e['id']."/"));
        $empleados_data[] = [
            'id'     => intval($e['id']),
            'qr_url' => $qr_url,
            'numero' => $e['numero'],
            'nombre' => $e['nombre']
        ];
    }
}
echo '<script>window.empleadosData = ' . json_encode($empleados_data) . ';</script>';

?>

<!-- Búsqueda de empleados -->

    <input type="text" id="search-input" placeholder="Buscar por nombre o número de empleado" style="margin-top: 20px; padding: 8px; font-size: 16px; width: 325px;"><br>
    <p style="margin-top:10px;">
        <a class="button-download" href="<?php echo esc_url( admin_url('admin-ajax.php?action=ve_exportar_empleados') ); ?>">Exportar a Excel</a>
        <a class="button-download" href="<?php echo esc_url( admin_url('admin-ajax.php?action=ve_layout_empleados') ); ?>">Layout de carga</a>
        <button type="button" id="btn-download-all" class="button-download">Descargar QRs</button>
        <span id="qr-progress" style="margin-left:10px;"></span>
    </p>
    <form method="post" enctype="multipart/form-data" style="margin-top:10px;">
        <?php wp_nonce_field("ve_carga_masiva_action","ve_carga_masiva_nonce"); ?>
        <input type="file" name="archivo_empleados" accept=".xlsx" required>
        <button type="submit" name="cargar_masivo" class="button-download">Subir empleados</button>
    </form>
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
            <tr data-id="<?php echo intval($mostrar_empleados['id']); ?>">
                <td><?php echo esc_html($mostrar_empleados['numero']); ?></td>
                <td><?php echo esc_html($mostrar_empleados['nombre']); ?></td>
                <td><?php echo esc_html($mostrar_empleados['departamento']); ?></td>
                <td><?php echo esc_html($mostrar_empleados['puesto']); ?></td>
                <td><?php echo esc_html($mostrar_empleados['estatus']); ?></td>
                <td>
                    <?php
                        $json_empleado = htmlspecialchars(json_encode($mostrar_empleados), ENT_QUOTES, 'UTF-8');
                        $id_empleado      = intval($mostrar_empleados['id']);
                        $numero_empleado  = intval($mostrar_empleados['numero']);
                        $nombre_empleado  = htmlspecialchars($mostrar_empleados['nombre']);
                        $base_filename    = $numero_empleado . '_' . remove_accents($mostrar_empleados['nombre']);
                        $base_filename    = preg_replace('/[^A-Za-z0-9 ]/', '', $base_filename);
                        $base_filename    = str_replace(' ', '_', trim($base_filename));
                        $ver_url_empleado = esc_url(home_url("/verificar-empleado/$id_empleado/"));
                        $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=$ver_url_empleado";
                    ?>
                    <button type="button" class="button-see" onClick="window.open('<?php echo $ver_url_empleado; ?>', '_blank')">Ver</button>
                    <button type="button" title="Editar empleado" onclick="openEditModal(<?php echo $json_empleado; ?>)">Editar</button>
                    <button type="button" class="button-delete" data-id="<?php echo $id_empleado; ?>">Eliminar</button>
                        <button type="button" class="button-download qr-btn" data-qr-url="<?php echo $qr_url; ?>" data-filename="<?php echo $base_filename; ?>.png" onClick="openQRModal('<?php echo $qr_url; ?>', '<?php echo $base_filename; ?>.png')">QR</button>
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

<style>
    #qrModal{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:9999;align-items:center;justify-content:center;padding:15px;}
    #qrModal .qr-content{background:#fff;border-radius:10px;padding:20px;text-align:center;max-width:400px;width:100%;}
    #qrModal img{max-width:100%;height:auto;margin:15px 0;display:none;}
    #qrModal button{margin:5px;padding:8px 15px;border:none;border-radius:6px;cursor:pointer;}
    #qrModal button.visualize{background:#282878;color:#fff;}
    #qrModal button.download{background:#007bff;color:#fff;}
    #qrModal button.close{background:#E62645;color:#fff;}
</style>

<div id="qrModal">
    <div class="qr-content">
        <h3>Código QR del Empleado</h3>
        <img id="qrImage" src="" alt="QR" />
        <div>
            <button type="button" class="visualize" id="qrViewBtn">Visualizar</button>
            <button type="button" class="download" id="qrDownloadBtn">Descargar</button>
            <button type="button" class="close" id="qrCloseBtn">Cerrar</button>
        </div>
    </div>
</div>

    <!-- Los catálogos se cargan desde catalogo_puestos.js -->

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
        document.addEventListener('DOMContentLoaded', function(){
            const editForm = document.getElementById('editEmployeeForm');
            if(!editForm) return;
            editForm.addEventListener('submit', function(e){
                e.preventDefault();
                const formData = new FormData(editForm);
                fetch("<?php echo admin_url('admin-ajax.php'); ?>?action=ve_actualizar_empleado", {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(resp => {
                    if(resp.success){
                        console.log("Empleado actualizado", resp.data);
                        refreshTable();
                        closeEditModal();
                    } else {
                        alert(resp.data || "Error al actualizar");
                    }
                })
                .catch(err => {
                    console.error('Error al actualizar:', err);
                    alert('Error al actualizar');
                });
            });
        });
    </script>

    <script>

        // Variables de estado para filtros y búsqueda
        let selectedDepartment = 'Todos';
        let selectedStatus = 'General';
        let searchQuery = '';
        const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';

        // Filtro por departamentos

    function sortTableByNumero(){
        const tbody = document.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        rows.sort((a,b)=>{
            const numA = parseInt(a.cells[0].textContent,10);
            const numB = parseInt(b.cells[0].textContent,10);
            return numA - numB;
        });
        rows.forEach(r=>tbody.appendChild(r));
    }

    function filtrarPorDepartamento(departamento){
        selectedDepartment = departamento;
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
        sortTableByNumero();

    }

    function refreshTable(){
        fetch(ajaxUrl + '?action=buscar_empleado&query=' + encodeURIComponent(searchQuery))
            .then(res => res.text())
            .then(data => {
                const tbody = document.querySelector('tbody');
                tbody.innerHTML = data;
                sortTableByNumero();
                filtrarPorDepartamento(selectedDepartment);
                filtrarPorEstatus(selectedStatus);
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
        selectedStatus = estatus;
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
            sortTableByNumero();
            const input = document.getElementById("search-input");

            input.addEventListener("keyup", function() {
                searchQuery = input.value;
                refreshTable();
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

    <script> // Función que permite obtener el blob del código QR
        function descargarQR(url){
            return new Promise((resolve, reject) => {
                const img = new Image();
                img.crossOrigin = "Anonymous";
                img.onload = function(){
                    const canvas = document.createElement('canvas');
                    canvas.width = img.width;
                    canvas.height = img.height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0);
                    canvas.toBlob(function(blob){
                        if(blob){
                            resolve(blob);
                        }else{
                            reject(new Error('No se pudo generar el blob'));
                        }
                    }, 'image/png');
                };
                img.onerror = function(){
                    reject(new Error('No se pudo cargar la imagen del QR'));
                };
                img.src = url + '&cache_buster=' + new Date().getTime();
            });
        }
    </script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    <script>
        function formatFilename(numero, nombre){
            const normalized = nombre.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            const cleaned = normalized.replace(/[^A-Za-z0-9 ]/g, '');
            const underscored = cleaned.trim().replace(/\s+/g, '_');
            return `${numero}_${underscored}.png`;
        }
        document.addEventListener('DOMContentLoaded', function(){
            const bulkBtn = document.getElementById('btn-download-all');
            const progress = document.getElementById('qr-progress');
            if (bulkBtn){
                bulkBtn.addEventListener('click', async function(){
                    const empleados = window.empleadosData || [];
                    if (!empleados.length){
                        alert('No hay QRs para descargar.');
                        return;
                    }
                    const zip = new JSZip();
                    const folder = zip.folder('QRCodes');
                    let fallidos = 0;
                    for (let i=0; i<empleados.length; i++){
                        const emp = empleados[i];
                        progress.textContent = `Descargando ${i+1} de ${empleados.length}...`;
                        try {
                            const blob = await descargarQR(emp.qr_url);
                            const filename = formatFilename(emp.numero, emp.nombre);
                            folder.file(filename, blob);
                        } catch(err){
                            console.error('Error QR', emp.id, err);
                            fallidos++;
                        }
                    }
                    progress.textContent = 'Generando ZIP...';
                    try {
                        const content = await zip.generateAsync({type:'blob'});
                        saveAs(content, 'Todos_los_QRs.zip');
                    } catch(err){
                        console.error('Error al generar ZIP:', err);
                        alert('Ocurrió un error al generar el ZIP.');
                        progress.textContent = '';
                        return;
                    }
                    progress.textContent = '';
                    if(fallidos){
                        alert(`No se pudieron descargar ${fallidos} QRs`);
                    }
                });
            }
        });
    </script>

    <script>
        function openQRModal(url, filename){
            const modal = document.getElementById('qrModal');
            const img = document.getElementById('qrImage');
            img.src = url;
            img.style.display = 'none';
            modal.dataset.filename = filename;
            modal.style.display = 'flex';
        }

        document.getElementById('qrViewBtn').addEventListener('click', function(){
            document.getElementById('qrImage').style.display = 'block';
        });

        document.getElementById('qrDownloadBtn').addEventListener('click', function(){
            const img = document.getElementById('qrImage');
            const filename = document.getElementById('qrModal').dataset.filename;
            descargarQR(img.src).then(blob => saveAs(blob, filename));
        });

        document.getElementById('qrCloseBtn').addEventListener('click', function(){
            document.getElementById('qrModal').style.display = 'none';
        });
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

    if ($query === '') {
        // Sin término de búsqueda: obtener todos
        $resultados = $wpdb->get_results("SELECT * FROM $tabla ORDER BY CAST(numero AS UNSIGNED)");
    } elseif (is_numeric($query)) {
        // Búsqueda exacta por número
        $resultados = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $tabla WHERE numero = %d ORDER BY CAST(numero AS UNSIGNED)",
            intval($query)
        ));
    } else {
        // Búsqueda parcial por nombre o número
        $like = '%' . $wpdb->esc_like($query) . '%';
        $resultados = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $tabla WHERE numero LIKE %s OR nombre LIKE %s ORDER BY CAST(numero AS UNSIGNED)",
            $like,
            $like
        ));
    }

    if ($resultados) {
        foreach ($resultados as $empleado) {
            $json_empleado = json_encode($empleado, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
            $id_empleado = intval($empleado->id);
            $numero_empleado = intval($empleado->numero);
            $nombre_empleado = htmlspecialchars($empleado->nombre);
            $ver_url_empleado = esc_url(home_url("/verificar-empleado/$id_empleado"));
            $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($ver_url_empleado);
            $qr_filename = "qr_" .$nombre_empleado . "_" . $numero_empleado . ".png";
            echo '<tr data-id="' . $id_empleado . '">';
            echo '<td>' . esc_html($empleado->numero) . '</td>';
            echo '<td>' . esc_html($empleado->nombre) . '</td>';
            echo '<td>' . esc_html($empleado->departamento) . '</td>';
            echo '<td>' . esc_html($empleado->puesto) . '</td>';
            echo '<td>' . esc_html($empleado->estatus) . '</td>';
            echo '<td> 
                    <button type="button" class="button-see" onClick="window.open(\''. esc_url(home_url("/verificar-empleado/". $empleado->id. "/")).'\', \'_blank\')">Ver</button>
                    <button type="button" title="Editar empleado" onclick=\'openEditModal(' . $json_empleado . ')\'>Editar</button>
                    <button type="button" class="button-delete" data-id="' . esc_attr($empleado->id) . '">Eliminar</button>
                    <button type="button" class="button-download qr-btn" data-qr-url="' . esc_url($qr_url) . '" data-filename="' . esc_attr($qr_filename) . '" onclick="openQRModal(\'' . esc_url($qr_url) . '\', \'' . esc_attr($qr_filename) . '\')">QR</button>
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

// === Obtener datos de empleado vía AJAX ===
add_action('wp_ajax_ve_get_empleado', 've_get_empleado');
add_action('wp_ajax_nopriv_ve_get_empleado', 've_get_empleado');

function ve_get_empleado(){
    global $wpdb;
    $tabla = $wpdb->prefix . 'empleados';
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($id > 0) {
        $empleado = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabla WHERE id = %d", $id));
        if ($empleado) {
            wp_send_json($empleado);
        }
    }
    wp_send_json_error('Empleado no encontrado');
}

// === Actualizar empleado vía AJAX ===
add_action('wp_ajax_ve_actualizar_empleado', 've_actualizar_empleado');

function ve_actualizar_empleado(){
    check_ajax_referer('editar_empleado_action','editar_empleado_nonce');

    global $wpdb;
    $tabla = $wpdb->prefix . 'empleados';

    $id = isset($_POST['id_empleado']) ? intval($_POST['id_empleado']) : 0;
    $nombre = sanitize_text_field($_POST['nombre']);
    $departamento = sanitize_text_field($_POST['edit_departamento']);
    $puesto = sanitize_text_field($_POST['edit_puesto']);
    $estatus = sanitize_text_field($_POST['edit_estatus']);

    $foto_url = $wpdb->get_var($wpdb->prepare("SELECT foto FROM $tabla WHERE id = %d", $id));

    if (!empty($_POST['remove_photo'])){
        $foto_path = str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $foto_url);
        if (file_exists($foto_path)){
            @unlink($foto_path);
        }
        $foto_url = '';
    }

    if (!empty($_FILES['edit_foto']['name'])) {
        $file = $_FILES['edit_foto'];
        $allowed = ['image/jpeg','image/png'];
        if (in_array($file['type'],$allowed)){
            $upload = wp_upload_bits($file['name'], null, file_get_contents($file['tmp_name']));
            if (!$upload['error']){
                $foto_url = esc_url_raw($upload['url']);
            } else {
                wp_send_json_error($upload['error']);
            }
        } else {
            wp_send_json_error('Formato de imagen inválido');
        }
    }

    $updated = $wpdb->update($tabla, [
        'nombre' => $nombre,
        'departamento' => $departamento,
        'puesto' => $puesto,
        'estatus' => $estatus,
        'foto' => $foto_url
    ], ['id' => $id]);

    if ($updated !== false) {
        $empleado = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabla WHERE id = %d", $id));
        wp_send_json_success($empleado);
    }
    wp_send_json_error('No se pudo actualizar');
}
function ve_xlsx_col($index){
    $index++;
    $col="";
    while($index>0){
        $index--;
        $col = chr(65 + ($index % 26)) . $col;
        $index = floor($index/26);
    }
    return $col;
}

function ve_generate_xlsx($rows){
    $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $sheet = "<worksheet xmlns='http://schemas.openxmlformats.org/spreadsheetml/2006/main'><sheetData>";
    foreach($rows as $r=> $row){
        $sheet .= "<row r='".($r+1)."'>";
        foreach($row as $c=> $v){
            $cell = ve_xlsx_col($c).($r+1);
            $val = htmlspecialchars($v, ENT_XML1);
            $sheet .= "<c r='$cell' t='inlineStr'><is><t>$val</t></is></c>";
        }
        $sheet .= '</row>';
    }
    $sheet .= '</sheetData></worksheet>';
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $zip->close();
    $data = file_get_contents($tmp);
    @unlink($tmp);
    return $data;
}

function ve_parse_simple_xlsx($file){
    $zip = new ZipArchive();
    if($zip->open($file) === TRUE){
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if($xml){
            $sxe = simplexml_load_string($xml);
            $rows = [];
            foreach($sxe->sheetData->row as $row){
                $r = [];
                foreach($row->c as $c){
                    if(isset($c->is->t)){
                        $r[] = (string)$c->is->t;
                    }elseif(isset($c->v)){
                        $r[] = (string)$c->v;
                    }else{
                        $r[] = '';
                    }
                }
                $rows[] = $r;
            }
            return $rows;
        }
    }
    return false;
}

// === Descargar listado completo en Excel ===
add_action('wp_ajax_ve_exportar_empleados', 've_exportar_empleados');
add_action('wp_ajax_nopriv_ve_exportar_empleados', 've_exportar_empleados');

function ve_exportar_empleados(){
    global $wpdb;
    $tabla = $wpdb->prefix . 'empleados';
    $empleados = $wpdb->get_results("SELECT id, numero, nombre, departamento, puesto, estatus, foto FROM $tabla ORDER BY numero", ARRAY_A);
    $rows = [];
    $rows[] = ['ID','Número de empleado','Nombre','Departamento','Puesto','Estatus','Foto'];
    foreach ($empleados as $emp){
        $rows[] = array_values($emp);
    }
    $xlsx = ve_generate_xlsx($rows);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="empleados.xlsx"');
    echo $xlsx;
    exit;
}


// === Descargar layout vacío para carga masiva ===
add_action('wp_ajax_ve_layout_empleados', 've_layout_empleados');
add_action('wp_ajax_nopriv_ve_layout_empleados', 've_layout_empleados');

function ve_layout_empleados(){
    $rows = [
        ['Número de empleado','Nombre','Departamento','Puesto','Estatus'],
        ['001','Juan Pérez','Dirección General','Auxiliar de Atención Ciudadana','Activo']
    ];
    $xlsx = ve_generate_xlsx($rows);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="layout_empleados.xlsx"');
    echo $xlsx;
    exit;
}






// === Descargar todos los QR en un ZIP ===
add_action('wp_ajax_ve_descargar_qrs', 've_descargar_qrs');
add_action('wp_ajax_nopriv_ve_descargar_qrs', 've_descargar_qrs');

function ve_descargar_qrs(){
    global $wpdb;
    $tabla = $wpdb->prefix . 'empleados';
    $empleados = $wpdb->get_results("SELECT id, numero, nombre FROM $tabla ORDER BY numero", ARRAY_A);
    $zip_file = tempnam(sys_get_temp_dir(), 'qrs');
    $zip = new ZipArchive();
    if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        wp_die('No se pudo crear el ZIP');
    }
        foreach ($empleados as $emp) {
            $id     = intval($emp['id']);
            $base   = $emp['numero'] . '_' . remove_accents($emp['nombre']);
            $base   = preg_replace('/[^A-Za-z0-9 ]/', '', $base);
            $nombre = str_replace(' ', '_', trim($base));
            $url    = home_url("/verificar-empleado/$id/");
            $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . rawurlencode($url);

        $response = wp_remote_get($qr_url);
        if (!is_wp_error($response)) {
            $img = wp_remote_retrieve_body($response);
            if (!empty($img)) {
                $zip->addFromString("{$nombre}.png", $img);
            }
        }
    }
    $zip->close();

    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="qrs.zip"');
    readfile($zip_file);
    @unlink($zip_file);
    exit;
}

