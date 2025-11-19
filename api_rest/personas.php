<?php
require_once("../motor_db/conexion_db.php");

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$id     = isset($_GET['id']) ? intval($_GET['id']) : 0;
$input  = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

function s($v) { return trim((string)($v ?? '')); }

function j_ok($data = []) {
    echo json_encode(["ok" => true] + $data);
    exit;
}

function j_err($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(["ok" => false, "error" => $msg]);
    exit;
}

switch ($action) {

    /* ===========================================
     * LISTAR PERSONAS
     * =========================================== */
    case 'list':
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

        // IMPORTANTE: la app espera UN ARRAY, no {ok:true, data:[]}
        echo json_encode($list, JSON_UNESCAPED_UNICODE);
        exit;

    /* ===========================================
     * CREAR PERSONA
     * =========================================== */
    case 'create':
        $nombres   = s($input['nombres'] ?? '');
        $apellidos = s($input['apellidos'] ?? '');
        $documento = s($input['documento'] ?? '');
        $fecha     = s($input['fecha'] ?? date("Y-m-d"));
        $hora      = date("H:i:s");

        if ($nombres === '' || $apellidos === '' || $documento === '') {
            j_err("Todos los campos son obligatorios");
        }

        // Ajustado a columnas reales
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
     * ACTUALIZAR PERSONA
     * =========================================== */
    case 'update':
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
     * ELIMINAR PERSONA
     * =========================================== */
    case 'delete':
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
     * DEFAULT
     * =========================================== */
    default:
        j_err("Acción no soportada. Usa: list | create | update | delete", 404);
}
?>
