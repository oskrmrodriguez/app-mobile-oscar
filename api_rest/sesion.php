<?php
require_once("../motor_db/conexion_db.php");

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$id     = isset($_GET['id']) ? intval($_GET['id']) : 0;

$input  = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

function s($v){ return trim((string)($v ?? '')); }

function j_ok($data = []) {
    echo json_encode(["ok"=>true] + $data);
    exit;
}

function j_err($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(["ok"=>false, "error"=>$msg]);
    exit;
}

switch ($action) {

    /* ============================================
     * LOGIN
     * ============================================ */
    case 'login':
        $user = s($input['username'] ?? '');
        $pass = s($input['password'] ?? '');

        if ($user==='' || $pass==='') j_err('Faltan datos');

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
        $stmt->bind_param("s", $user);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows===0) j_err("Usuario no existe", 401);
        $row = $res->fetch_assoc();

        if ($row['EstadoUsuario']!=='ACTIVO') j_err("Usuario inactivo", 403);
        if ($pass!==$row['UsuContra']) j_err("Contraseña incorrecta", 401);

        // Rol por fallback
        $rol = $row['RolNom'] ?? '';
        if (!$rol) {
            $rol = ($row['IdUsuarios']==1) ? 'ADMINISTRADOR' : 'USUARIO';
        }

        j_ok([
            "token" => base64_encode($row['IdUsuarios']."|".time()),
            "user" => [
                "id"        => (int)$row['IdUsuarios'],
                "username"  => $row['UsuUser'],
                "personaId" => $row['UsuPersonaId'] ? (int)$row['UsuPersonaId'] : null,
                "rol"       => $rol
            ]
        ]);
    break;


    /* ============================================
     * REGISTRO
     * ============================================ */
    case 'register':
        $user = s($input['username'] ?? '');
        $pass = s($input['password'] ?? '');
        $nombre = s($input['nombre'] ?? '');
        $apellido = s($input['apellido'] ?? '');
        $documento = s($input['documento'] ?? '');

        if ($user==='' || $pass==='') j_err("Faltan datos para registro");

        // Verificar duplicado
        $stmt = $conn->prepare("SELECT IdUsuarios FROM usuarios WHERE UsuUser=? LIMIT 1");
        $stmt->bind_param("s", $user);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows>0) j_err("El usuario ya existe", 409);

        // 1️⃣ Crear persona
        $fecha = date("Y-m-d");
        $hora = date("H:i:s");

        $stmt = $conn->prepare(
            "INSERT INTO personas(PerNom, PerApe, PerNumDoc, PerFechareg, PerHorareg, EstadoPersona)
             VALUES (?, ?, ?, ?, ?, 'ACTIVO')"
        );
        $stmt->bind_param("sssss", $nombre, $apellido, $documento, $fecha, $hora);
        if(!$stmt->execute()) j_err("Error creando persona");

        $personaId = $stmt->insert_id;

        // 2️⃣ Crear usuario
        $ins = $conn->prepare(
            "INSERT INTO usuarios(UsuUser, UsuContra, EstadoUsuario, UsuPersonaId)
             VALUES (?, ?, 'ACTIVO', ?)"
        );
        $ins->bind_param("ssi", $user, $pass, $personaId);
        $ins->execute();

        j_ok(["msg"=>"Usuario registrado con persona asociada"]);
    break;


    /* ============================================
     * RECUPERAR
     * ============================================ */
    case 'recover':
        $user = s($input['username'] ?? '');
        $new  = s($input['new_password'] ?? '');
        if ($user==='' || $new==='') j_err('Faltan datos');

        $upd = $conn->prepare("UPDATE usuarios SET UsuContra=? WHERE UsuUser=?");
        $upd->bind_param("ss", $new, $user);
        $upd->execute();

        if ($upd->affected_rows>0) j_ok(["msg"=>"Contraseña actualizada"]);
        j_err("Usuario no encontrado", 404);
    break;


    /* ======================================================
     * CRUD PERSONAS (compatibles con tu PersonasActivity.kt)
     * ====================================================== */

    /* ---------- LIST ---------- */
    case 'personas_list':
        $res = $conn->query(
            "SELECT 
                IdPersona AS id,
                PerNom AS nombres,
                PerApe AS apellidos,
                PerNumDoc AS documento,
                PerFechareg AS fecha
            FROM personas
            ORDER BY IdPersona DESC"
        );

        $arr = [];
        while($row=$res->fetch_assoc()) $arr[]=$row;

        // La app quiere SOLO un array, nada más
        echo json_encode($arr, JSON_UNESCAPED_UNICODE);
        exit;

    /* ---------- CREATE ---------- */
    case 'personas_create':
        $n = s($input['nombres'] ?? '');
        $a = s($input['apellidos'] ?? '');
        $d = s($input['documento'] ?? '');
        if ($n==='' || $a==='' || $d==='') j_err("Campos obligatorios");

        $fecha = date("Y-m-d");
        $hora = date("H:i:s");

        $stmt = $conn->prepare(
            "INSERT INTO personas(PerNom, PerApe, PerNumDoc, PerFechareg, PerHorareg, EstadoPersona)
             VALUES (?, ?, ?, ?, ?, 'ACTIVO')"
        );
        $stmt->bind_param("sssss", $n, $a, $d, $fecha, $hora);
        $stmt->execute();

        j_ok(["msg"=>"Persona creada"]);
    break;

    /* ---------- UPDATE ---------- */
    case 'personas_update':
        if ($id===0) j_err("ID inválido");

        $n = s($input['nombres'] ?? '');
        $a = s($input['apellidos'] ?? '');
        $d = s($input['documento'] ?? '');

        $stmt = $conn->prepare(
            "UPDATE personas
             SET PerNom=?, PerApe=?, PerNumDoc=?
             WHERE IdPersona=?"
        );
        $stmt->bind_param("sssi", $n, $a, $d, $id);
        $stmt->execute();

        j_ok(["msg"=>"Persona actualizada"]);
    break;

    /* ---------- DELETE ---------- */
    case 'personas_delete':
        if ($id===0) j_err("ID inválido");

        $stmt = $conn->prepare("DELETE FROM personas WHERE IdPersona=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        j_ok(["msg"=>"Persona eliminada"]);
    break;



    /* ============================================
     * DEFAULT
     * ============================================ */
    default:
        j_err("Acción no soportada");
}
?>
