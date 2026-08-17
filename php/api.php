<?php
// ============================================================
//  API REST simple — Factibilidad DFM
// ============================================================
session_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/campos.php';   // definición de campos y pesos

// ── helpers ─────────────────────────────────────────────────
function json_ok(mixed $data = []): never
{
    echo json_encode(['ok' => true, 'data' => $data]);
    exit;
}
function json_err(string $msg, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}
function requireLogin(): array
{
    if (empty($_SESSION['user_id']))
        json_err('No autenticado', 401);
    return $_SESSION;
}
function requireAdmin(): array
{
    $s = requireLogin();
    if ($s['rol'] !== 'admin')
        json_err('Sin permisos', 403);
    return $s;
}

// ── router ───────────────────────────────────────────────────
// ── router ───────────────────────────────────────────────────
// 1. LEER EL JSON QUE ENVÍA APP.JS
$raw_input = file_get_contents('php://input');
$json_input = json_decode($raw_input, true) ?? [];

// 2. BUSCAR LA ACCIÓN EN EL JSON (O EN GET/POST POR SI ACASO)
$action = $_GET['action'] ?? $_POST['action'] ?? $json_input['action'] ?? '';

match ($action) {
    'login' => action_login(),
    'logout' => action_logout(),
    'me' => action_me(),
    'solicitud_nueva' => action_solicitud_nueva(),
    'solicitud_guardar' => action_solicitud_guardar(),
    'solicitud_enviar' => action_solicitud_enviar(),
    'solicitud_get' => action_solicitud_get(),
    'solicitud_lista' => action_solicitud_lista(),
    'solicitud_cambiar_estado' => action_cambiar_estado(),
    'usuarios_lista' => action_usuarios_lista(),
    'usuario_crear' => action_usuario_crear(),
    'usuario_toggle' => action_usuario_toggle(),
    'campos_definicion' => action_campos_definicion(),
    default => json_err('Acción no encontrada', 404),
};

// ============================================================
//  ACCIONES
// ============================================================

function action_login(): never
{
    // 3. LEER EL CORREO Y PASSWORD DESDE EL JSON
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true) ?? [];

    $email = trim($_POST['email'] ?? $body['email'] ?? '');
    $pass = $_POST['password'] ?? $body['password'] ?? '';

    if (!$email || !$pass)
        json_err('Credenciales requeridas');

    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND activo = 1 LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($pass, $user['password_hash'])) {
        json_err('Email o contraseña incorrectos', 401);
    }

    $pdo->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?")->execute([$user['id']]);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['nombre'] = $user['nombre'];
    $_SESSION['rol'] = $user['rol'];
    $_SESSION['email'] = $user['email'];

    json_ok(['nombre' => $user['nombre'], 'rol' => $user['rol'], 'email' => $user['email']]);
}

function action_logout(): never
{
    session_destroy();
    json_ok();
}

function action_me(): never
{
    if (empty($_SESSION['user_id']))
        json_err('No autenticado', 401);
    json_ok([
        'nombre' => $_SESSION['nombre'],
        'rol' => $_SESSION['rol'],
        'email' => $_SESSION['email'],
    ]);
}

function action_solicitud_nueva(): never
{
    $sess = requireLogin();
    $pdo = getDB();

    // Generar folio único
    $folio = 'FAC-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

    $stmt = $pdo->prepare(
        "INSERT INTO solicitudes (folio, creado_por, estado) VALUES (?, ?, 'borrador')"
    );
    $stmt->execute([$folio, $sess['user_id']]);
    $id = (int) $pdo->lastInsertId();

    json_ok(['id' => $id, 'folio' => $folio]);
}

function action_solicitud_guardar(): never
{
    $sess = requireLogin();
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!$body)
        json_err('Cuerpo inválido');

    $id = (int) ($body['id'] ?? 0);
    if (!$id)
        json_err('ID requerido');

    $pdo = getDB();

    // Verificar ownership (admin puede todo)
    $stmt = $pdo->prepare("SELECT * FROM solicitudes WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $sol = $stmt->fetch();
    if (!$sol)
        json_err('Solicitud no encontrada', 404);
    if ($sess['rol'] !== 'admin' && $sol['creado_por'] != $sess['user_id']) {
        json_err('Sin permisos', 403);
    }
    if ($sol['estado'] === 'enviado')
        json_err('La solicitud ya fue enviada');

    // Actualizar campos de cabecera
    $campos_cabecera = [
        'cliente',
        'lider_proyecto',
        'fecha_entrada',
        'fecha_entrega_equipo',
        'fecha_estimada_cierre',
        'fecha_entrega_lider',
        'fecha_cierre'
    ];
    $sets = [];
    $vals = [];
    foreach ($campos_cabecera as $c) {
        if (array_key_exists($c, $body)) {
            $sets[] = "$c = ?";
            $vals[] = $body[$c] ?: null;
        }
    }

    // Calcular porcentaje
    $campos_vals = $body['campos'] ?? [];
    $pct = calcularPorcentaje($campos_vals);
    $sets[] = "porcentaje_completado = ?";
    $vals[] = $pct;
    $vals[] = $id;

    if ($sets) {
        $pdo->prepare("UPDATE solicitudes SET " . implode(',', $sets) . " WHERE id = ?")->execute($vals);
    }

    // Guardar campos EAV
    foreach ($campos_vals as $clave => $valor) {
        $pdo->prepare(
            "INSERT INTO solicitud_campos (solicitud_id, campo_clave, valor)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor), actualizado_en = NOW()"
        )->execute([$id, $clave, is_array($valor) ? implode(',', $valor) : $valor]);
    }

    json_ok(['porcentaje' => $pct]);
}

function action_solicitud_enviar(): never
{
    $sess = requireLogin();
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true) ?? [];
    $id = (int) ($body['id'] ?? $_POST['id'] ?? 0);
    if (!$id)
        json_err('ID requerido');

    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM solicitudes WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $sol = $stmt->fetch();
    if (!$sol)
        json_err('Solicitud no encontrada', 404);
    if ($sess['rol'] !== 'admin' && $sol['creado_por'] != $sess['user_id'])
        json_err('Sin permisos', 403);
    if ($sol['estado'] !== 'borrador')
        json_err('Solo se pueden enviar borradores');
    if ((float) $sol['porcentaje_completado'] < 75) {
        json_err('El porcentaje de completado debe ser al menos 75% para enviar. Actual: ' . $sol['porcentaje_completado'] . '%');
    }

    $pdo->prepare(
        "UPDATE solicitudes SET estado = 'enviado', enviado_en = NOW() WHERE id = ?"
    )->execute([$id]);

    $pdo->prepare(
        "INSERT INTO solicitud_historial (solicitud_id, estado_desde, estado_hasta, usuario_id, comentario)
         VALUES (?, 'borrador', 'enviado', ?, 'Solicitud enviada por el usuario')"
    )->execute([$id, $sess['user_id']]);

    json_ok(['mensaje' => 'Solicitud enviada exitosamente']);
}

function action_solicitud_get(): never
{
    requireLogin();
    $id = (int) ($_GET['id'] ?? 0);
    if (!$id)
        json_err('ID requerido');

    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT s.*, u.nombre as creado_por_nombre FROM solicitudes s JOIN usuarios u ON s.creado_por = u.id WHERE s.id = ? LIMIT 1");
    $stmt->execute([$id]);
    $sol = $stmt->fetch();
    if (!$sol)
        json_err('No encontrada', 404);

    // Cargar campos EAV
    $stmt2 = $pdo->prepare("SELECT campo_clave, valor FROM solicitud_campos WHERE solicitud_id = ?");
    $stmt2->execute([$id]);
    $campos = [];
    foreach ($stmt2->fetchAll() as $row) {
        $campos[$row['campo_clave']] = $row['valor'];
    }
    $sol['campos'] = $campos;

    // Historial
    $stmt3 = $pdo->prepare(
        "SELECT h.*, u.nombre as usuario_nombre FROM solicitud_historial h
         JOIN usuarios u ON h.usuario_id = u.id
         WHERE h.solicitud_id = ? ORDER BY h.fecha DESC"
    );
    $stmt3->execute([$id]);
    $sol['historial'] = $stmt3->fetchAll();

    json_ok($sol);
}

function action_solicitud_lista(): never
{
    $sess = requireLogin();
    $pdo = getDB();
 
    $where = $sess['rol'] === 'admin' ? '' : 'WHERE s.creado_por = ' . (int) $sess['user_id'];
    $stmt = $pdo->query(
        "SELECT s.id, s.folio, s.cliente, s.lider_proyecto, s.estado,
                s.porcentaje_completado, s.creado_en, s.enviado_en,
                s.fecha_entrada, s.fecha_entrega_equipo, s.fecha_estimada_cierre,
                s.fecha_entrega_lider, s.fecha_cierre,
                u.nombre as creado_por_nombre
         FROM solicitudes s JOIN usuarios u ON s.creado_por = u.id
         $where ORDER BY s.creado_en DESC LIMIT 200"
    );
    json_ok($stmt->fetchAll());
}

function action_cambiar_estado(): never
{
    requireAdmin();
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true) ?? [];
    $id = (int) ($body['id'] ?? 0);
    $nuevo = $body['estado'] ?? '';
    $nota = $body['comentario'] ?? '';
    $estados_validos = ['en_revision', 'aprobado', 'rechazado'];
    if (!$id || !in_array($nuevo, $estados_validos))
        json_err('Parámetros inválidos');

    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT estado FROM solicitudes WHERE id = ?");
    $stmt->execute([$id]);
    $sol = $stmt->fetch();
    if (!$sol)
        json_err('No encontrada', 404);

    $pdo->prepare("UPDATE solicitudes SET estado = ? WHERE id = ?")->execute([$nuevo, $id]);
    $pdo->prepare(
        "INSERT INTO solicitud_historial (solicitud_id, estado_desde, estado_hasta, usuario_id, comentario)
         VALUES (?, ?, ?, ?, ?)"
    )->execute([$id, $sol['estado'], $nuevo, $_SESSION['user_id'], $nota]);

    json_ok();
}

function action_usuarios_lista(): never
{
    requireAdmin();
    $pdo = getDB();
    $stmt = $pdo->query("SELECT id, nombre, email, rol, departamento, activo, creado_en, ultimo_login FROM usuarios ORDER BY nombre");
    json_ok($stmt->fetchAll());
}

function action_usuario_crear(): never
{
    requireAdmin();
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true) ?? [];
    $nombre = trim($body['nombre'] ?? '');
    $email = trim($body['email'] ?? '');
    $pass = $body['password'] ?? '';
    $rol = $body['rol'] ?? 'ingenieria';
    $depto = trim($body['departamento'] ?? '');
    $roles_validos = ['admin', 'lider', 'ingenieria', 'ventas'];

    if (!$nombre || !$email || !$pass)
        json_err('Nombre, email y contraseña son requeridos');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        json_err('Email inválido');
    if (!in_array($rol, $roles_validos))
        json_err('Rol inválido');
    if (strlen($pass) < 6)
        json_err('La contraseña debe tener al menos 6 caracteres');

    $pdo = getDB();
    try {
        $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare(
            "INSERT INTO usuarios (nombre, email, password_hash, rol, departamento) VALUES (?,?,?,?,?)"
        )->execute([$nombre, $email, $hash, $rol, $depto ?: null]);
        json_ok(['id' => (int) $pdo->lastInsertId()]);
    } catch (\PDOException $e) {
        if (str_contains($e->getMessage(), 'Duplicate'))
            json_err('El email ya está registrado');
        json_err('Error al crear usuario');
    }
}

function action_usuario_toggle(): never
{
    requireAdmin();
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true) ?? [];
    $id = (int) ($body['id'] ?? 0);
    if (!$id)
        json_err('ID requerido');
    if ($id == $_SESSION['user_id'])
        json_err('No puedes desactivarte a ti mismo');

    $pdo = getDB();
    $pdo->prepare("UPDATE usuarios SET activo = NOT activo WHERE id = ?")->execute([$id]);
    json_ok();
}

function action_campos_definicion(): never
{
    requireLogin();
    json_ok(getCamposDefinicion());
}

// ── lógica de cálculo ────────────────────────────────────────
function calcularPorcentaje(array $valores): float
{
    $campos = getCamposDefinicion();
    $total = 0.0;
    foreach ($campos as $seccion) {
        foreach ($seccion['campos'] as $campo) {
            $clave = $campo['clave'];
            $peso = (float) ($campo['peso'] ?? 0);
            if ($peso <= 0)
                continue;
            $val = $valores[$clave] ?? '';
            if ($val !== '' && $val !== null && $val !== false) {
                $total += $peso;
            }
        }
    }
    return round(min($total * 100, 100), 2);
}
