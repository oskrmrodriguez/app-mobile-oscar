<?php
require_once("../motor_db/conexion_db.php");

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$id     = isset($_GET['id']) ? intval($_GET['id']) : 0;
$input  = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

function s($v){ return trim((string)($v ?? '')); }

// OJO: aquí asumo que j_ok y j_err ya están definidos en conexion_db.php.
// Si no, puedes copiar estas versiones:
//
// function j_ok($data = []) {
//     echo json_encode(["ok"=>true] + $data);
//     exit;
// }
//
// function j_err($msg, $code=400) {
//     http_response_code($code);
//     echo json_encode(["ok"=>false,"error"=>$msg]);
//     exit;
// }

switch ($action) {

  /* ===========================================
   *  LOGIN
   * =========================================== */
  case 'login':
    $user = s($input['username'] ?? '');
    $pass = s($input['password'] ?? '');
    if ($user === '' || $pass === '') j_err('Faltan datos');

    $sql = "SELECT 
                u.IdUsuarios,
                u.UsuUser,
                u.UsuContra,
                u.EstadoUsuario,
                u.UsuPersonaId,
                r.RolNom
            FROM usuarios u
            LEFT JOIN usuarios_roles ur ON ur.UsuarioId = u.IdUsuarios
            LEFT JOIN roles r          ON r.IdRol      = ur.RolId
            WHERE u.UsuUser = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) j_err('Error interno al preparar consulta', 500);

    $stmt->bind_param("s", $user);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) j_err('Usuario no existe', 401);
    $row = $res->fetch_assoc();

    if ($row['EstadoUsuario'] !== 'ACTIVO') j_err('Usuario inactivo', 403);
    if ($pass !== $row['UsuContra']) j_err('Contraseña incorrecta', 401);

    $rol = $row['RolNom'] ?? null;
    if ($rol !== null) $rol = trim($rol);

    if ($rol === null || $rol === '') {
        if ((int)$row['IdUsuarios'] === 1) {
            $rol = 'ADMINISTRADOR';
        } else {
            $rol = 'USUARIO';
        }
    }

    $token = base64_encode($row['IdUsuarios'].'|'.time());

    j_ok([
      "token" => $token,
      "user"  => [
        "id"        => (int)$row['IdUsuarios'],
        "username"  => $row['UsuUser'],
        "personaId" => $row['UsuPersonaId'] ? (int)$row['UsuPersonaId'] : null,
        "rol"       => $rol
      ]
    ]);
    break;


  /* ===========================================
   *  REGISTRO
   * =========================================== */
  case 'register':
    $user  = s($input['username'] ?? '');
    $pass  = s($input['password'] ?? '');
    $email = s($input['email'] ?? ''); // OPCIONAL

    if ($user === '' || $pass === '') {
      j_err('Faltan datos para registro');
    }

    $stmt = $conn->prepare("SELECT IdUsuarios FROM usuarios WHERE UsuUser = ? LIMIT 1");
    if (!$stmt) j_err('Error interno al preparar consulta', 500);

    $stmt->bind_param("s", $user);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
      j_err('El usuario ya existee jajaj', 409);
    }

    $ins = $conn->prepare(
      "INSERT INTO usuarios (UsuUser, UsuContra, EstadoUsuario) 
       VALUES (?, ?, 'ACTIVO')"
    );
    if (!$ins) j_err('Error interno al preparar inserción', 500);

    $ins->bind_param("ss", $user, $pass);

    if ($ins->execute()) {
      j_ok([
        "msg" => "Usuario registrado correctamente",
        "id"  => $ins->insert_id
      ]);
    }

    j_err('No se pudo registrar el usuario', 500);
    break;


  /* ===========================================
   *  RECUPERAR CONTRASEÑA
   * =========================================== */
  case 'recover':
    $user = s($input['username'] ?? '');
    $new  = s($input['new_password'] ?? '');
    if ($user === '' || $new === '') j_err('Faltan datos');

    $upd = $conn->prepare("UPDATE usuarios SET UsuContra=? WHERE UsuUser=?");
    if (!$upd) j_err('Error interno al preparar actualización', 500);

    $upd->bind_param("ss", $new, $user);
    $upd->execute();

    if ($upd->affected_rows > 0) j_ok(["msg" => "Contraseña actualizada"]);
    j_err('Usuario no encontrado', 404);
    break;


  /* ===========================================
   *  CRUD PERSONAS - LIST
   * =========================================== */
  case 'personas_list':
        $sql = "SELECT 
                    IdPersona    AS id,
                    PerNom       AS nombres,
                    PerApe       AS apellidos,
                    PerNumDoc    AS documento,
                    PerFechareg  AS fecha
                FROM personas
                ORDER BY IdPersona DESC";

        $res = $conn->query($sql);
        if (!$res) {
            j_err("Error en consulta: " . $conn->error, 500);
        }

        $list = [];
        while ($row = $res->fetch_assoc()) {
            $list[] = $row;
        }

        // La app espera un ARRAY puro
        echo json_encode($list, JSON_UNESCAPED_UNICODE);
        exit;

  /* ===========================================
   *  CRUD PERSONAS - CREATE
   * =========================================== */
  case 'personas_create':
        $nombres   = s($input['nombres'] ?? '');
        $apellidos = s($input['apellidos'] ?? '');
        $documento = s($input['documento'] ?? '');
        $fecha     = s($input['fecha'] ?? date("Y-m-d"));
        $hora      = date("H:i:s");

        if ($nombres === '' || $apellidos === '' || $documento === '') {
            j_err("Todos los campos son obligatorios");
        }

        $stmt = $conn->prepare(
            "INSERT INTO personas (PerNom, PerApe, PerNumDoc, PerFechareg, PerHorareg, EstadoPersona)
             VALUES (?, ?, ?, ?, ?, 'ACTIVO')"
        );
        if (!$stmt) j_err("Error en prepare(): " . $conn->error, 500);

        $stmt->bind_param("sssss", $nombres, $apellidos, $documento, $fecha, $hora);

        if ($stmt->execute()) {
            j_ok(["msg" => "Persona creada", "id" => $stmt->insert_id]);
        }

        j_err("No se pudo crear persona", 500);
        break;

  /* ===========================================
   *  CRUD PERSONAS - UPDATE
   * =========================================== */
  case 'personas_update':
        if ($id === 0) j_err("ID inválido");

        $nombres   = s($input['nombres'] ?? '');
        $apellidos = s($input['apellidos'] ?? '');
        $documento = s($input['documento'] ?? '');
        $fecha     = s($input['fecha'] ?? date("Y-m-d"));

        $stmt = $conn->prepare(
            "UPDATE personas 
             SET PerNom = ?, PerApe = ?, PerNumDoc = ?, PerFechareg = ?
             WHERE IdPersona = ?"
        );
        if (!$stmt) j_err("Error en prepare(): " . $conn->error, 500);

        $stmt->bind_param("ssssi", $nombres, $apellidos, $documento, $fecha, $id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            j_ok(["msg" => "Persona actualizada"]);
        }

        j_err("No se pudo actualizar persona", 500);
        break;

  /* ===========================================
   *  CRUD PERSONAS - DELETE
   * =========================================== */
  case 'personas_delete':
        if ($id === 0) j_err("ID inválido");

        $stmt = $conn->prepare("DELETE FROM personas WHERE IdPersona = ?");
        if (!$stmt) j_err("Error en prepare(): " . $conn->error, 500);

        $stmt->bind_param("i", $id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            j_ok(["msg" => "Persona eliminada"]);
        }

        j_err("No se pudo eliminar persona", 500);
        break;


  /* ===========================================
   *  DEFAULT
   * =========================================== */
  default:
    j_err('Acción no soportada. Usa action=login | register | recover | personas_*', 404);
}
?>
