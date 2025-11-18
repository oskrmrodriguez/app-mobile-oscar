<?php
require_once("../motor_db/conexion_db.php");

$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

function s($v){ return trim((string)($v ?? '')); }

switch ($action) {

  /* ===========================================
   *  LOGIN
   * =========================================== */
  case 'login':
    $user = s($input['username'] ?? '');
    $pass = s($input['password'] ?? '');
    if ($user === '' || $pass === '') j_err('Faltan datos');

    // Traemos también el rol del usuario00000000000
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
    if (!$stmt) {
        j_err('Error interno al preparar consulta', 500);
    }
    $stmt->bind_param("s", $user);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) j_err('Usuario no existe', 401);
    $row = $res->fetch_assoc();

    if ($row['EstadoUsuario'] !== 'ACTIVO') j_err('Usuario inactivo', 403);
    if ($pass !== $row['UsuContra']) j_err('Contraseña incorrecta', 401);

    // Rol desde la tabla; normalizamos y ponemos fallback
    $rol = $row['RolNom'] ?? null;
    if ($rol !== null) {
        $rol = trim($rol);
    }

    // Si no tiene rol, definimos:
    //  - IdUsuarios = 1 -> ADMINISTRADOR
    //  - cualquier otro -> USUARIO
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

    // Verificar si el usuario ya existe
    $stmt = $conn->prepare("SELECT IdUsuarios FROM usuarios WHERE UsuUser = ? LIMIT 1");
    if (!$stmt) {
        j_err('Error interno al preparar consulta', 500);
    }
    $stmt->bind_param("s", $user);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
      j_err('El usuario ya existee jajaj', 409);
    }

    // Insertar nuevo usuario — ajusta columnas si agregas más
    $ins = $conn->prepare(
      "INSERT INTO usuarios (UsuUser, UsuContra, EstadoUsuario) 
       VALUES (?, ?, 'ACTIVO')"
    );
    if (!$ins) {
        j_err('Error interno al preparar inserción', 500);
    }
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
    if (!$upd) {
        j_err('Error interno al preparar actualización', 500);
    }
    $upd->bind_param("ss", $new, $user);
    $upd->execute();

    if ($upd->affected_rows > 0) j_ok(["msg" => "Contraseña actualizada"]);
    j_err('Usuario no encontrado', 404);
    break;


  /* ===========================================
   *  DEFAULT
   * =========================================== */
  default:
    j_err('Acción no soportada. Usa action=login | register | recover', 404);
}
?>
