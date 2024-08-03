<?php
// configuración PHP reporte de errores
// reporte de errores
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ALL  & ~MYSQLI_REPORT_INDEX);
ini_set('display_errors', '1');

// Constantes y variables globales
// valores de la BD mysql
// bddeben ajustarse para el servidor donde se va a desplegar
//la aplicación
$url_bd = "localhost";
$puerto_bd = "3306";
$usuario_bd = "root";
$password_bd = "Root.123";
$nombre_bd = "bd_asistencia_alumnos";

// variable para la conexión a BD
$conexion_bd = null;

// mapa con los nombres de los meses por número
$nombres_meses = [
  "01" => "Enero",
  "02" => "Febrero",
  "03" => "Marzo",
  "04" => "Abril",
  "05" => "Mayo",
  "06" => "Junio",
  "07" => "Julio",
  "08" => "Agosto",
  "09" => "Septiembre",
  "10" => "Octubre",
  "11" => "Noviembre",
  "12" => "Diciembre",
];

// cálculo del año que sale de la fecha actual
$anio_actual = date('Y', time());

// array reporte para el armado de tabla en HTML
$reporte_asistencia = [];
$guardado_reporte = false;

// campos de fecha del formulario
$dia_reporte = 0;
$mes_reporte = 0;
$anio_reporte = 0;
$fecha_reporte = null;
$fecha_valida = true;

// funciones generales de BD
function iniciar_conexion_mysql($host, $puerto, $usuario, $password, $bd) {
  $conn =  new mysqli($host, $usuario, $password, $bd, $puerto);
  if ($conn->connect_errno) {
    echo("Error intentando conectar a BD: {$conn->connect_errno}: {$conn->connect_error}");
    return false;
  }
  return $conn;
} 

// cierra la conexión de la BD al final de todo
function cerrar_conexion_mysql($conn) {
  if ($conn) {
    $conn->close();
  }
} 

function ultimo_error($conn) {
  return "Error de BD {$conn->connect_errno}: {$conn->connect_error}";
}

function ejecutar_query_select($conn, $query) {
  $resultado_query = $conn->query($query);  
  // verificar error
  // si hay error se imprime y se sale del programa
  if ($resultado_query === false) {
    echo ultimo_error($conn);
    exit(1);
  }
  return $resultado_query;
}

function ejecutar_query_update($conn, $query) {
  $resultado_query = $conn->query($query);  
  // verificar error
  // si hay error se imprime y se sale del programa
  if (!$resultado_query) {
    echo ultimo_error($conn);
    exit(1);
  }
}

function ultimo_id_insert($conn) {
  $query = "SELECT last_insert_id() N";
  $resultado_query = ejecutar_query_select($conn, $query);
  $fila = $resultado_query->fetch_assoc();
  $resultado_query->close();
  if (!$fila || empty($fila['N'])) {
    return -1;
  } else {
    return (int)$fila['N'];
  }
}

function cargar_lista_personas($conn) {
  $query = "
      SELECT `persona`.`id`,
        `persona`.`dni`,
        `persona`.`nombre`,
        `persona`.`apellido`,
        `persona`.`email`
      FROM `persona`
      ORDER BY `persona`.`dni`";
  
  // cargar la lista de personas desde el resultado
  $lista_personas = [];
  $resultado_query = ejecutar_query_select($conn, $query);
  while ($persona = $resultado_query->fetch_assoc()) {
    $lista_personas[] = $persona;
  }
  $resultado_query->close();
  return $lista_personas;
}

function cargar_persona_por_id($conn, $id_persona) {
  $id_persona_escape = "'" . $conn->real_escape_string($id_persona) . "'";
  $query = "
      SELECT `persona`.`id`,
        `persona`.`dni`,
        `persona`.`nombre`,
        `persona`.`apellido`,
        `persona`.`email`
      FROM `persona`
      WHERE `persona`.`id` = {$id_persona_escape} 
      LIMIT 1";
  
  // cargar la lista de personas desde el resultado
  $resultado_query = ejecutar_query_select($conn, $query);
  $persona = $resultado_query->fetch_assoc();
  $resultado_query->close();
  return $persona;
}

function cargar_registro_asistencia($conn, $dia, $mes, $anio) {
  $fecha_formateada = sprintf("%02d-%02d-%02d", $anio, $mes, $dia);
  $fecha_bd =  "'" . $conn->real_escape_string($fecha_formateada) . "'" ;
  $query = "SELECT 
      `registro_asistencia`.`id`,
      `registro_asistencia`.`fecha`
    FROM 
      `registro_asistencia` 
    WHERE
       `registro_asistencia`.`fecha` = {$fecha_bd}";
  $resultado_query = ejecutar_query_select($conn, $query);
  
  // sacar el primero
  // si no hay devuelve nulo
  $registro_asistencia = $resultado_query->fetch_assoc();
  $resultado_query->close();
  return $registro_asistencia;
}

function cargar_asistencia_persona($conn, $id_persona, $id_registro_asistencia) {
  $id_persona_escape = "'" .$conn->real_escape_string($id_persona) . "'";
  $id_registro_asistencia_escape = "'" .$conn->real_escape_string($id_registro_asistencia) . "'";
  $query = "SELECT `asistencia_persona`.`id`,
        `asistencia_persona`.`id_persona`,
        `asistencia_persona`.`id_registro_asistencia`,
        `asistencia_persona`.`asistencia`,
        `asistencia_persona`.`observaciones`,
		`asistencia_persona`.`supervisor_asignado`
    FROM `asistencia_persona`
    WHERE `asistencia_persona`.`id_persona` = {$id_persona_escape}
    AND `asistencia_persona`.`id_registro_asistencia` = {$id_registro_asistencia_escape}";
  $resultado_query = ejecutar_query_select($conn, $query);
  
  // sacar el primero
  // si no hay devuelve nulo
  $asistencia_persona = $resultado_query->fetch_assoc();
  $resultado_query->close();
  return $asistencia_persona;
}

function insertar_registro_asistencia($conn, $dia, $mes, $anio) {
  $fecha_bd = "'" . $conn->real_escape_string(sprintf("%02d-%02d-%02d", $anio, $mes, $dia)) . "'";
  $query = "INSERT INTO `registro_asistencia`
      (`fecha`)
      VALUES
      ({$fecha_bd})";
  ejecutar_query_update($conn, $query);
  return ultimo_id_insert($conn);
}

function insertar_asistencia_persona($conn, $asistencia_persona) {
  $id_persona_escape = "'" . $conn->real_escape_string($asistencia_persona['id_persona']) . "'";
  $id_registro_asistencia_escape = "'" . $conn->real_escape_string($asistencia_persona['id_registro_asistencia']) . "'";
  $asistencia_escape = empty(trim($asistencia_persona['asistencia']))
      ? 'null'
      : "'" . $conn->real_escape_string($asistencia_persona['asistencia']) . "'";
  $observaciones_escape = empty(trim($asistencia_persona['observaciones']))
      ? 'null'
      : "'" . $conn->real_escape_string($asistencia_persona['observaciones']) . "'";
  $supervisor_asignado_escape = empty(trim($asistencia_persona['supervisor_asignado']))
      ? 'null'
      : "'" . $conn->real_escape_string($asistencia_persona['supervisor_asignado']) . "'";
  $query = "INSERT INTO `asistencia_persona`
      (`id_persona`,
      `id_registro_asistencia`,
      `asistencia`,
      `observaciones`,
	  `supervisor_asignado`)
      VALUES
      ({$id_persona_escape},
      {$id_registro_asistencia_escape},
      {$asistencia_escape},
      {$observaciones_escape},
	  {$supervisor_asignado_escape})";
  ejecutar_query_update($conn, $query);
  return ultimo_id_insert($conn);
}

function editar_asistencia_persona($conn, $asistencia_persona) {
  $id_escape = "'" . $conn->real_escape_string($asistencia_persona['id']) . "'";
  $asistencia_escape = empty(trim($asistencia_persona['asistencia']))
      ? 'null'
      : "'" . $conn->real_escape_string($asistencia_persona['asistencia']) . "'";
  $observaciones_escape = empty(trim($asistencia_persona['observaciones']))
      ? 'null'
      : "'" . $conn->real_escape_string($asistencia_persona['observaciones']) . "'";
  $supervisor_asignado_escape = empty(trim($asistencia_persona['supervisor_asignado']))
      ? 'null'
      : "'" . $conn->real_escape_string($asistencia_persona['supervisor_asignado']) . "'";
  $query = "UPDATE `asistencia_persona`
      SET
      `asistencia` = {$asistencia_escape},
      `observaciones` = {$observaciones_escape},
	  `observaciones` = {$supervisor_asignado_escape}
      WHERE `id` = {$id_escape}";
  ejecutar_query_update($conn, $query);
  return ultimo_id_insert($conn);
}

// funciones de fecha
function fecha_valida($dia, $mes, $anio) {
  $d = (int)$dia;
  $m = (int)$mes;
  $a = (int)$anio;
  $anio_actual = date('Y', time());
  $anio_inicio = (int)$anio_actual;
  $anio_fin = $anio_inicio - 5;
  // validar rangos
  if ($d < 1  || $d > 31) {
    return false;
  }
  if ($m < 1  || $m > 12) {
    return false;
  }
  if ($a < $anio_fin  || $m > $anio_inicio) {
    return false;
  }

  //verificar dia segun mes
  // meses de 31
  if ($m == 1 || $m == 3 || $m == 5 || $m == 7 || $m == 8 || $m == 10 || $m == 12) {
    return $d <= 31;
  }
  // meses de 30
  if ($m == 4 || $m == 6 || $m == 9 || $m == 11) {
    return $d <= 30;
  }
  // febrero
  return ($a % 4 == 0) ? $d <= 29 : $d <= 28;
}

function cargar_fecha_desde_formulario($arr_form = []) {
  // parametros de acción
  // fecha asistencia viene como parámetro si está vacía no se carga nada
  $dia = isset($arr_form['dia']) ? trim($arr_form['dia']) : '';
  $mes = isset($arr_form['mes']) ? trim($arr_form['mes']) : '';
  $anio = isset($arr_form['anio']) ? trim($arr_form['anio']) : '';

  // carga de fecha si viene desde parámetro de URL
  if (empty($dia) || empty($mes) || empty($anio)) {
    $dia_reporte = 0;
    $mes_reporte = 0;
    $anio_reporte = 0;
    $fecha_reporte = null;
    $fecha_valida = true;
    $fecha_futura = false;
  } else {
    // carga de BD
    $dia_reporte = sprintf("%02d", $dia);
    $mes_reporte = sprintf("%02d", $mes);
    $anio_reporte = sprintf("%04d", $anio);
    $fecha_valida = fecha_valida($dia_reporte, $mes_reporte, $anio_reporte);
    $fecha_reporte = $fecha_valida
      ? mktime(0, 0, 0, (int)$mes_reporte, (int)$dia_reporte, (int)$anio_reporte)
      : null;
    $fecha_futura = !is_null($fecha_reporte) && $fecha_reporte > time();  
  }

  return [
    'dia_reporte' => $dia_reporte,
    'mes_reporte' => $mes_reporte,
    'anio_reporte' => $anio_reporte,
    'fecha_valida' => $fecha_valida,
    'fecha_reporte' => $fecha_reporte,
    'fecha_futura' => $fecha_futura
  ];
}


/*
 * ******************************************************************
 * 
 *  Inicio de programa principal
 * 
 * ******************************************************************
 * 
 */
// conexión a BD
$conexion_bd = iniciar_conexion_mysql($url_bd, $puerto_bd, $usuario_bd, $password_bd, $nombre_bd);
if (!$conexion_bd) {
  echo("<br>");
  echo("La aplicación no puede cargar.");
  exit(1);
}

// cargar parámetros de formulario
// si $_POST es no vacío es que viene un guardado o edición
// de reporte de asistencia
if (!empty($_POST)) {
  /*********************************
   * Carga del reporte cuando vienen datos de la tabla
   * 
   *********************************/

  // sacar fecha de parámetros
  $arr_fecha = cargar_fecha_desde_formulario($_POST);
  $dia_reporte = $arr_fecha['dia_reporte'];
  $mes_reporte = $arr_fecha['mes_reporte'];
  $anio_reporte = $arr_fecha['anio_reporte'];
  $fecha_valida = $arr_fecha['fecha_valida'];
  $fecha_reporte = $arr_fecha['fecha_reporte'];
  $fecha_futura = $arr_fecha['fecha_futura'];
  
  
  // ver si existe o no el reporte
  $registro_asistencia = cargar_registro_asistencia($conexion_bd, $dia_reporte, $mes_reporte, $anio_reporte);
  
  // si el registro de asistencia para la fecha es nuevo, se crea
  $id_registro_asistencia = is_null($registro_asistencia)
      ? insertar_registro_asistencia($conexion_bd, $dia_reporte, $mes_reporte, $anio_reporte)
      : $registro_asistencia['id'];

  // se itera sobre todas las personas en la tabla
  // y se registra
  foreach ($_POST['id_persona'] as $i => $id_persona) {
    // sacar los campos de registro de asistencia
    $asistencia = $_POST['asistencia'][$i];
    $observaciones = $_POST['observaciones'][$i];
	$supervisor_asignado = $_POST['supervisor_asignado'][$i];
    
    // se inserta o actualiza asistencia de persona
    $persona = cargar_persona_por_id($conexion_bd, $id_persona);
    $asistencia_persona = cargar_asistencia_persona($conexion_bd, $id_persona, $id_registro_asistencia);
    if (is_null($asistencia_persona)) {
      // se crea por primera vez
      $asistencia_persona = [
        'id_persona'  => $id_persona,
        'id_registro_asistencia' => $id_registro_asistencia,
        'asistencia' => $asistencia,
        'observaciones' => $observaciones,
		'supervisor_asignado'=> $supervisor_asignado
      ];
      $id_asistencia_persona = insertar_asistencia_persona($conexion_bd, $asistencia_persona);
      $asistencia_persona['id'] = $id_asistencia_persona;
    } else {
      // se actualiza
      $asistencia_persona['asistencia'] = $asistencia;
      $asistencia_persona['observaciones'] = $observaciones;
	  $asistencia_persona['supervisor_asignado'] = $supervisor_asignado;
      editar_asistencia_persona($conexion_bd, $asistencia_persona);
    }

    // se agrega el item de asistencia al reporte
    // que se va a imprimir en la tabla
    $reporte_asistencia[] = [
      'id_persona' => $persona['id'],
      'dni_persona' => $persona['dni'],
      'nombre_persona' => $persona['nombre'],
      'apellido_persona' => $persona['apellido'],
      'email_persona' => $persona['email'],
      'id_asistencia_persona' => $asistencia_persona['id'],
      'asistencia' => $asistencia_persona['asistencia'],
      'observaciones' => $asistencia_persona['observaciones'],
	  'supervisor_asignado' => $asistencia_persona['supervisor_asignado']
    ];
  }

  $guardado_reporte = true;
} else {
  /*********************************
   * Carga del reporte cuando no vienen datos de la tabla
   * 
   *********************************/
  // parametros de acción
  $arr_fecha = cargar_fecha_desde_formulario($_GET);
  $dia_reporte = $arr_fecha['dia_reporte'];
  $mes_reporte = $arr_fecha['mes_reporte'];
  $anio_reporte = $arr_fecha['anio_reporte'];
  $fecha_valida =$arr_fecha['fecha_valida'];
  $fecha_reporte = $arr_fecha['fecha_reporte'];
  $fecha_futura = $arr_fecha['fecha_futura'];

  // carga del reporte asistencia si hay
  $lista_personas= cargar_lista_personas($conexion_bd);
  $registro_asistencia = is_null($fecha_reporte)
      ? null
      : cargar_registro_asistencia($conexion_bd, $dia_reporte, $mes_reporte, $anio_reporte);
  
  // si no existe registro de asistencia para la fecha, se pone el id -1
  // para marcar la creación
  $id_registro_asistencia = is_null($registro_asistencia) 
      ? -1 
      : $registro_asistencia['id'];
  $existe_registro_asistencia = ($id_registro_asistencia > 0);

  // se itera sobre cada persona para armar el reporte
  foreach ($lista_personas as $i => $persona) {
    // si hay registro de asistencia, se carga
    // el valor de asistencia de la persona
    // si no se genera un array con valores vacíos
    $asistencia_persona = cargar_asistencia_persona($conexion_bd, $persona['id'], $id_registro_asistencia);
    if (is_null($asistencia_persona)) {
      $asistencia_persona = [
        'id' => '-1', 
        'id_persona' => '-1',
        'id_asistencia_persona' => '-1',
        'asistencia'  => '',
        'observaciones' => '',
		'supervisor_asignado'=> ''];
    }
    
    // se agrega el item de asistencia al reporte
    // que se va a imprimir en la tabla
    $reporte_asistencia[] = [
      'id_persona' => $persona['id'],
      'dni_persona' => $persona['dni'],
      'nombre_persona' => $persona['nombre'],
      'apellido_persona' => $persona['apellido'],
      'email_persona' => $persona['email'],
      'id_asistencia_persona' => $asistencia_persona['id'],
      'asistencia' => $asistencia_persona['asistencia'],
      'observaciones' => $asistencia_persona['observaciones'],
	  'supervisor_asignado' => $asistencia_persona['supervisor_asignado'],
    ];
  }

  $guardado_reporte = false;
}
// flag para mostrar reporte
$mostrar_reporte = !empty($reporte_asistencia) 
    && $fecha_valida
    && !$fecha_futura
    && $dia_reporte != 0 
    && $mes_reporte != 0
    && $anio_reporte != 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="css/style.css">
  <title>Registro asistencia</title>
</head>

<body>
  <main>
    <div>
      <h1>
        Sistema de Asistencia
      </h1>
    </div>
    <div class="tabs">
      <input class="tab_radio" type="radio" name="tabs" id="tab1" checked="checked">
      <label for="tab1">Registro de Asistencia</label>
      <div class="tab-content">
        <!-- Espacio para mensajes de error -->
        <?php 
        if (!$fecha_valida) {
        ?>
          <div class="panel_error">
          La fecha seleccionada Día: <em><?php echo $dia_reporte?></em> 
          Mes: <em><?php echo $mes_reporte?></em> 
          Año: <em><?php echo $anio_reporte?></em> 
          Es inválida. Intente seleccionando otra fecha válida.
          </div>
          <hr class="clear_both">
        <?php 
        } elseif($fecha_futura) {
        ?>
          <div class="panel_error">
          La fecha seleccionada es futura. Debe seleccionar la fecha actual o una fecha anterior
          </div>
          <hr class="clear_both">
        <?php 
        } elseif ($guardado_reporte) {
        ?>
          <div class="panel_success">
            Se actualizó el reporte de asistencia para la fecha 
            <em><?php echo $dia_reporte?></em> 
            Mes: <em><?php echo $mes_reporte?></em> 
            Año: <em><?php echo $anio_reporte?></em>.
          </div>
          <hr class="clear_both">
        <?php 
        } 
        ?>
        <div>
          <!-- Formulario de selección de fecha -->
          <form id="form_fecha_reporte" method="get" action="index.php">
            <p>Seleccione la fecha para registrar la asistencia</p>
            Día
            <!-- Combo selección días -->
            <select name="dia" id="select_dia_reporte">
              <option value="">--</option>
              <?php
              for ($i = 1; $i <= 31; $i++) {
                $valor = sprintf("%02d", $i);
                $selected = ($fecha_valida && $valor == $dia_reporte) ? "selected" : "";
              ?>
                <option value="<?php echo $valor ?>" <?php echo $selected ?>>
                  <?php echo $valor ?>
                </option>
              <?php
              }
              ?>
            </select>
            &nbsp;
            Mes:
            <select name="mes" id="select_mes_reporte">
              <option value="">--</option>
              <?php
              for ($i = 1; $i <= 12; $i++) {
                $valor = sprintf("%02d", $i);
                $nombre_mes = $nombres_meses[$valor];
                $selected = ($fecha_valida && $valor == $mes_reporte) ? "selected" : "";
              ?>
                <option value="<?php echo $valor ?>" <?php echo $selected ?>>
                  <?php echo $nombre_mes ?>
                </option>
              <?php
              }
              ?>
            </select>
            &nbsp;
            Año
            <select name="anio" id="select_anio_reporte">
              <option value="">--</option>
              <?php
              $anio_inicio = (int)$anio_actual;
              $anio_fin = $anio_inicio - 5;
              for ($i = $anio_inicio; $i >= $anio_fin; $i--) {
                $valor = sprintf("%04d", $i);
                $selected = ($fecha_valida && $valor == $anio_reporte) ? "selected" : "";
              ?>
                <option value="<?php echo $valor ?>" <?php echo $selected ?>>
                  <?php echo $valor ?>
                </option>
              <?php
              }
              ?>
            </select>
            <input type="submit" value="Buscar Reporte Asistencia">
          </form>
        </div>

        <!-- Tabla con el reporte -->
        <?php
        if ($mostrar_reporte) {
        ?>
          <div>
            <h2> Reporte de horas </h2>
          </div>
        
          <!-- Armado de la tabla del reporte de horas -->
          <form id="form_registro_asistencia" method="post" action="index.php">
            <table id="tabla_reporte">
              <!-- Fila de encabezado -->
              <tr>
                <th>DNI</th>
                <th>Nombre</th>
                <th>Email</th>
				 <th>Supervisor</th>
                <th>Asistencia</th>
                <th>Observaciones</th>
              </tr>
              <?php 
              // Ciclo tabla
              foreach ($reporte_asistencia as $i => $item_persona) {
                // datos dela perosna
                $id_persona = $item_persona['id_persona'];
                $dni_persona = $item_persona['dni_persona'];
                $nombre_persona = "{$item_persona['nombre_persona']} {$item_persona['apellido_persona']}";
                $email_persona = $item_persona['email_persona'];
                $asistencia_persona = $item_persona['asistencia'];
                $observaciones_persona = $item_persona['observaciones'];
                $supervisor_asignado_persona = $item_persona['supervisor_asignado'];
                $id_asistencia_persona = $item_persona['id_asistencia_persona'];
                // los campos selected para opciones de combo
                $selected_vacio = empty($asistencia_persona) 
                    ? 'selected'
                    : '';
                $selected_presente = $asistencia_persona == 'presente' 
                    ? 'selected'
                    : '';
                $selected_ausente = $asistencia_persona == 'ausente' 
                    ? 'selected'
                    : '';
                $selected_retraso = $asistencia_persona == 'retraso' 
                    ? 'selected'
                    : '';
              ?>
                <tr class="fila_persona">
                  <td><?php echo $dni_persona ?></td>
                  <td><?php echo $nombre_persona ?></td>
                  <td><?php echo $email_persona ?></td>
                  <td>
                    <!-- Caja texto supervisor -->
                    <input type="text" maxlength="50" name="supervisor_asignado[]" id="" value="<?php echo $supervisor_asignado_persona?>">
                  </td>
				   <td>
                    <!-- Campo oculto id persona -->
                    <input type="hidden" name="id_persona[]" value="<?php echo $id_persona ?>">
                    <!-- Campo oculto id registro_asistencia -->
                    <input type="hidden" name="id_aistencia_persona[]" value="<?php echo $id_asistencia_persona ?>">
                    <!-- Combo para seleccionar asistencia -->  
                    <select name="asistencia[]">
                      <option value="" <?php echo $selected_vacio?>>--</option>
                      <option value="presente" <?php echo $selected_presente?>>Presente</option>
                      <option value="ausente" <?php echo $selected_ausente?>>Ausente</option>
                      <option value="retraso" <?php echo $selected_retraso?>>Con retraso</option>
                    </select>
                  </td>
                  <td>
                    <!-- Caja texto observaciones -->
                    <input type="text" maxlength="50" name="observaciones[]" id="" value="<?php echo $observaciones_persona?>">
                  </td>
                </tr>
              <?php 
              }
              ?>
            </table>
            <!-- ocultos de fecha -->
            <input type="hidden" name="dia" value="<?php echo $dia_reporte ?>"> 
            <input type="hidden" name="mes" value="<?php echo $mes_reporte ?>"> 
            <input type="hidden" name="anio" value="<?php echo $anio_reporte ?>">
            <div id="barra_guardar">
            Guardar Registro de asistencia para la fecha             <?php echo $dia_reporte ?>-<?php echo $mes_reporte ?>-<?php echo $anio_reporte ?>
            &nbsp;
            <input type="submit" value="Guardar">
            </div>
            
          </form>
        <?php
        }
        ?>
      </div>
    </div>
  </main>
</body>
</html>
<?php 
cerrar_conexion_mysql($conexion_bd);
?>