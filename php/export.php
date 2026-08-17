<?php
date_default_timezone_set('America/Mexico_City');
// ============================================================
//  EXPORTAR PDF — Factibilidad DFM (Ultra-Optimizado Max 2 Hojas)
//  Genera un documento HTML optimizado para impresión/PDF
//  Uso: php/export_pdf.php?id=42  (sesión activa requerida)
// ============================================================
session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/campos.php';

// ── Auth ─────────────────────────────────────────────────────
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit('No autenticado');
}

// ── Parámetros ────────────────────────────────────────────────
$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    exit('ID requerido');
}

// ── Cargar datos ──────────────────────────────────────────────
$pdo = getDB();

$stmt = $pdo->prepare(
    "SELECT s.*, u.nombre AS creado_por_nombre
     FROM solicitudes s
     JOIN usuarios u ON s.creado_por = u.id
     WHERE s.id = ? LIMIT 1"
);
$stmt->execute([$id]);
$sol = $stmt->fetch();

if (!$sol) {
    http_response_code(404);
    exit('Solicitud no encontrada');
}

// Control de acceso: solo el creador o admin pueden exportar
if ($_SESSION['rol'] !== 'admin' && $sol['creado_por'] != $_SESSION['user_id']) {
    http_response_code(403);
    exit('Sin permisos');
}

// Cargar campos EAV
$stmt2 = $pdo->prepare("SELECT campo_clave, valor FROM solicitud_campos WHERE solicitud_id = ?");
$stmt2->execute([$id]);
$campos_eav = [];
foreach ($stmt2->fetchAll() as $row) {
    $campos_eav[$row['campo_clave']] = $row['valor'];
}

// Cargar historial (LIMITADO A LOS ÚLTIMOS 5 PARA AHORRAR ESPACIO EN PDF)
$stmt3 = $pdo->prepare(
    "SELECT h.*, u.nombre AS usuario_nombre
     FROM solicitud_historial h
     JOIN usuarios u ON h.usuario_id = u.id
     WHERE h.solicitud_id = ?
     ORDER BY h.fecha DESC
     LIMIT 5"
);
$stmt3->execute([$id]);
$historial = $stmt3->fetchAll();

// ── Helpers ───────────────────────────────────────────────────
function esc(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function fmtDate(?string $d): string
{
    if (!$d)
        return '—';
    $ts = strtotime($d);
    return $ts ? date('d/m/Y', $ts) : '—';
}

function getVal(array $sol, array $eav, string $clave): string
{
    $v = $sol[$clave] ?? $eav[$clave] ?? '';
    return (string) ($v ?? '');
}

function estadoLabel(string $e): string
{
    $m = [
        'borrador' => 'Borrador',
        'enviado' => 'Enviado',
        'en_revision' => 'En Revisión',
        'aprobado' => 'Aprobado',
        'rechazado' => 'Rechazado',
    ];
    return $m[$e] ?? $e;
}

function estadoColor(string $e): string
{
    $m = [
        'borrador' => '#6b7280',
        'enviado' => '#1a56db',
        'en_revision' => '#d97706',
        'aprobado' => '#0e9f6e',
        'rechazado' => '#e02424',
    ];
    return $m[$e] ?? '#6b7280';
}

function estadoBg(string $e): string
{
    $m = [
        'borrador' => '#f3f4f6',
        'enviado' => '#dbeafe',
        'en_revision' => '#fef3c7',
        'aprobado' => '#d1fae5',
        'rechazado' => '#fee2e2',
    ];
    return $m[$e] ?? '#f3f4f6';
}

function pctColor(float $p): string
{
    if ($p >= 75)
        return '#0e9f6e';
    if ($p >= 40)
        return '#d97706';
    return '#e02424';
}

// ── Calcular campos faltantes ─────────────────────────────────
$campos_def = getCamposDefinicion();
$campos_faltantes = [];
$pct = (float) ($sol['porcentaje_completado'] ?? 0);

foreach ($campos_def as $seccion) {
    foreach ($seccion['campos'] as $campo) {
        $peso = (float) ($campo['peso'] ?? 0);
        if ($peso <= 0)
            continue;
        $val = getVal($sol, $campos_eav, $campo['clave']);
        if ($val === '' || $val === null) {
            $campos_faltantes[] = [
                'seccion' => $seccion['titulo'],
                'label' => $campo['label'],
                'peso' => $peso,
            ];
        }
    }
}

$total_perdido = array_sum(array_column($campos_faltantes, 'peso')) * 100;

// ── Renderizar HTML ───────────────────────────────────────────
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Solicitud <?= esc($sol['folio']) ?> — Factibilidad DFM</title>
    <style>
        /* ── Variables ── */
        :root {
            --primary: #1a56db;
            --success: #0e9f6e;
            --warning: #d97706;
            --danger: #e02424;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-400: #9ca3af;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-900: #111827;
        }

        /* ── Reset / base ── */
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 12px;
            color: var(--gray-900);
            background: #fff;
            padding: 0;
            overflow-wrap: break-word;
        }

        /* ── Pantalla ── */
        @media screen {
            body {
                background: #e5e7eb;
            }

            .page {
                background: #fff;
                max-width: 900px;
                margin: 32px auto;
                padding: 32px;
                border-radius: 10px;
                box-shadow: 0 4px 24px rgba(0, 0, 0, .15);
            }

            .print-actions {
                max-width: 900px;
                margin: 20px auto 0;
                display: flex;
                gap: 10px;
                justify-content: flex-end;
            }

            .btn-print {
                background: var(--primary);
                color: #fff;
                border: none;
                padding: 10px 24px;
                border-radius: 6px;
                font-size: 14px;
                cursor: pointer;
            }

            .btn-back {
                background: #fff;
                color: var(--gray-700);
                border: 1.5px solid var(--gray-200);
                padding: 10px 24px;
                border-radius: 6px;
                font-size: 14px;
                text-decoration: none;
            }

            .btn-print:hover {
                background: #1245b0;
            }

            .btn-back:hover {
                background: var(--gray-100);
            }
        }

        /* ── OPTIMIZACIÓN EXTREMA PARA IMPRESIÓN (MAX 2 HOJAS) ── */
        @media print {

            /* Definición del margen físico más amplio */
            @page {
                size: letter;
                margin: 2cm;
                /* 2 centímetros de margen físico por default */
            }

            body {
                font-size: 9px;
                background: #fff;
                margin: 0;
                padding: 0;
            }

            .print-actions {
                display: none;
            }

            /* DOBLE SEGURO: Agregamos padding interno arriba y a los lados por si el navegador ignora el @page */
            .page {
                padding: 25px 35px !important;
                /* Despegado de arriba y de los costados */
                margin: 0;
                box-shadow: none;
                width: 100%;
                max-width: 100%;
                box-sizing: border-box;
            }

            /* Encabezado mínimo */
            .doc-header {
                padding: 8px 12px !important;
                margin-bottom: 6px !important;
            }

            .doc-header .title {
                font-size: 12px !important;
            }

            .doc-header .subtitle {
                font-size: 8px !important;
                margin-top: 0 !important;
            }

            .doc-header .folio {
                font-size: 14px !important;
            }

            /* Grid a 6 columnas para compactar altura */
            .meta-grid {
                grid-template-columns: repeat(6, 1fr) !important;
                margin-bottom: 6px !important;
            }

            .meta-cell {
                padding: 4px 6px !important;
            }

            .meta-cell .ml {
                font-size: 7px !important;
                margin-bottom: 1px !important;
            }

            .meta-cell .mv {
                font-size: 9px !important;
            }

            /* Barra de progreso aplanada en línea */
            .progress-box {
                padding: 4px 8px !important;
                margin-bottom: 6px !important;
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .progress-header {
                margin-bottom: 0 !important;
                flex-shrink: 0;
                width: auto !important;
            }

            .progress-pct {
                font-size: 12px !important;
                margin-left: 6px;
                display: inline-block;
            }

            .progress-label {
                display: inline-block;
                font-size: 9px !important;
            }

            .progress-track {
                flex-grow: 1;
                height: 6px !important;
            }

            .progress-tip {
                margin-top: 0 !important;
                font-size: 8px !important;
                flex-shrink: 0;
            }

            /* Secciones a 3 columnas */
            .section {
                margin-bottom: 6px !important;
                break-inside: avoid;
                border-radius: 4px !important;
            }

            .section-header {
                padding: 4px 8px !important;
                font-size: 10px !important;
                border-bottom: 1px solid var(--gray-200) !important;
            }

            .fields-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            }

            .field-cell {
                padding: 4px 6px !important;
                border-bottom: 1px solid var(--gray-100) !important;
                border-right: 1px solid var(--gray-100) !important;
                overflow: hidden;
            }

            .field-cell:nth-child(3n) {
                border-right: none !important;
            }

            .field-label {
                font-size: 7px !important;
                margin-bottom: 2px !important;
            }

            .field-value,
            .field-empty {
                font-size: 9px !important;
                word-break: break-word;
            }

            /* Tablas */
            .historial-table th,
            .historial-table td {
                padding: 3px 5px !important;
                font-size: 8px !important;
            }

            .missing-header {
                padding: 5px 8px !important;
            }

            .missing-header h2 {
                font-size: 11px !important;
            }

            .missing-table th,
            .missing-table td {
                padding: 3px 5px !important;
                font-size: 8px !important;
            }

            .all-ok {
                padding: 6px !important;
                font-size: 10px !important;
            }

            .doc-footer {
                margin-top: 8px !important;
                padding-top: 6px !important;
                font-size: 7px !important;
            }

            .avoid-break {
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }

        /* ── Estilos Generales ── */
        .doc-header {
            background: var(--primary);
            color: #fff;
            border-radius: 8px;
            padding: 20px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .doc-header .title {
            font-size: 18px;
            font-weight: 700;
        }

        .doc-header .subtitle {
            font-size: 11px;
            opacity: .8;
            margin-top: 2px;
        }

        .doc-header .folio {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: 1px;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1px;
            background: var(--gray-200);
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 16px;
        }

        .meta-cell {
            background: var(--gray-50);
            padding: 10px 14px;
        }

        .meta-cell .ml {
            font-size: 10px;
            color: var(--gray-400);
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-bottom: 3px;
        }

        .meta-cell .mv {
            font-size: 13px;
            font-weight: 600;
            color: var(--gray-900);
        }

        .progress-box {
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            padding: 14px 20px;
            margin-bottom: 16px;
            background: var(--gray-50);
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .progress-label {
            font-size: 11px;
            color: var(--gray-600);
        }

        .progress-pct {
            font-size: 20px;
            font-weight: 800;
        }

        .progress-track {
            height: 10px;
            background: var(--gray-200);
            border-radius: 99px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 99px;
        }

        .progress-tip {
            font-size: 10px;
            color: var(--gray-600);
            margin-top: 6px;
        }

        .estado-badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }

        .section {
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            margin-bottom: 12px;
            overflow: hidden;
        }

        .section-header {
            background: #eff6ff;
            padding: 10px 16px;
            font-weight: 700;
            font-size: 12px;
            color: var(--primary);
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-pct {
            font-size: 10px;
            background: #dbeafe;
            color: var(--primary);
            padding: 2px 8px;
            border-radius: 20px;
        }

        .fields-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
        }

        .field-cell {
            padding: 8px 14px;
            border-bottom: 1px solid var(--gray-100);
            border-right: 1px solid var(--gray-100);
        }

        .field-cell:nth-child(even) {
            border-right: none;
        }

        .field-label {
            font-size: 9px;
            color: var(--gray-400);
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: 2px;
        }

        .field-value {
            font-size: 12px;
            font-weight: 600;
            color: var(--gray-900);
        }

        .field-empty {
            font-size: 12px;
            color: var(--gray-400);
            font-style: italic;
        }

        .field-cell.has-peso .field-label::after {
            content: ' ●';
            color: var(--primary);
            font-size: 8px;
        }

        .historial-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        .historial-table th {
            background: var(--gray-700);
            color: #fff;
            padding: 7px 10px;
            text-align: left;
            font-size: 10px;
        }

        .historial-table td {
            padding: 7px 10px;
            border-bottom: 1px solid var(--gray-100);
        }

        .historial-table tr:nth-child(even) td {
            background: var(--gray-50);
        }

        .missing-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            background: #fee2e2;
            border: 1px solid #fecaca;
            border-radius: 8px 8px 0 0;
            margin-top: 0;
        }

        .missing-header h2 {
            font-size: 15px;
            color: var(--danger);
        }

        .missing-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        .missing-table th {
            background: var(--gray-700);
            color: #fff;
            padding: 8px 12px;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
        }

        .missing-table td {
            padding: 8px 12px;
            border-bottom: 1px solid var(--gray-100);
        }

        .missing-table tr:nth-child(even) td {
            background: #fef2f2;
        }

        .missing-table .total-row td {
            background: var(--danger);
            color: #fff;
            font-weight: 700;
            font-size: 12px;
        }

        .missing-table .pct-col {
            text-align: center;
            color: var(--danger);
            font-weight: 700;
        }

        .missing-table .total-row .pct-col {
            color: #fff;
        }

        .all-ok {
            background: #d1fae5;
            border: 1px solid #a7f3d0;
            border-radius: 8px;
            padding: 16px 20px;
            text-align: center;
            color: var(--success);
            font-weight: 700;
            font-size: 14px;
        }

        .doc-footer {
            text-align: center;
            font-size: 9px;
            color: var(--gray-400);
            padding-top: 12px;
            border-top: 1px solid var(--gray-200);
            margin-top: 16px;
        }
    </style>
</head>

<body>

    <!-- Acciones de pantalla -->
    <div class="print-actions">
        <a href="../" class="btn-back">← Volver</a>
        <button class="btn-print" onclick="window.print()">🖨️ Imprimir / Guardar PDF</button>
    </div>

    <div class="page">

        <!-- ── ENCABEZADO ── -->
        <div class="doc-header">
            <div>
                <div class="title">Solicitud de Factibilidad DFM</div>
                <div class="subtitle">Sistema de Gestión de Requisitos</div>
            </div>
            <div class="folio"><?= esc($sol['folio']) ?></div>
        </div>

        <!-- ── META INFO ── -->
        <div class="meta-grid">
            <div class="meta-cell">
                <div class="ml">Creado por</div>
                <div class="mv"><?= esc($sol['creado_por_nombre']) ?></div>
            </div>
            <div class="meta-cell">
                <div class="ml">Fecha de Creación</div>
                <div class="mv"><?= fmtDate($sol['creado_en']) ?></div>
            </div>
            <div class="meta-cell">
                <div class="ml">Estado</div>
                <div class="mv">
                    <span class="estado-badge"
                        style="background:<?= estadoBg($sol['estado']) ?>;color:<?= estadoColor($sol['estado']) ?>">
                        <?= estadoLabel($sol['estado']) ?>
                    </span>
                </div>
            </div>
            <div class="meta-cell">
                <div class="ml">Cliente</div>
                <div class="mv"><?= esc($sol['cliente'] ?: '—') ?></div>
            </div>
            <div class="meta-cell">
                <div class="ml">Líder de Proyecto</div>
                <div class="mv"><?= esc($sol['lider_proyecto'] ?: '—') ?></div>
            </div>
            <div class="meta-cell">
                <div class="ml">Enviado en</div>
                <div class="mv"><?= fmtDate($sol['enviado_en']) ?></div>
            </div>
        </div>

        <!-- ── BARRA DE PROGRESO ── -->
        <?php $bar_color = pctColor($pct); ?>
        <div class="progress-box avoid-break">
            <div class="progress-header">
                <div class="progress-label">Porcentaje de Completado</div>
                <div class="progress-pct" style="color:<?= $bar_color ?>"><?= number_format($pct, 1) ?>%</div>
            </div>
            <div class="progress-track">
                <div class="progress-fill" style="width:<?= min($pct, 100) ?>%;background:<?= $bar_color ?>"></div>
            </div>
            <div class="progress-tip">
                <?php if ($pct >= 75): ?>
                    ✅ Listo
                <?php else: ?>
                    ⚠️ Falta <?= number_format(75 - $pct, 1) ?>%
                <?php endif; ?>
            </div>
        </div>

        <!-- ── SECCIONES DE CAMPOS ── -->
        <?php foreach ($campos_def as $seccion): ?>
            <?php
            $total_peso_sec = array_sum(array_column($seccion['campos'], 'peso'));
            ?>
            <div class="section avoid-break">
                <div class="section-header">
                    <span><?= esc($seccion['icono']) ?>     <?= esc($seccion['titulo']) ?></span>
                    <?php if ($total_peso_sec > 0): ?>
                        <span class="section-pct"><?= round($total_peso_sec * 100) ?>% del total</span>
                    <?php endif; ?>
                </div>
                <div class="fields-grid">
                    <?php foreach ($seccion['campos'] as $campo): ?>
                        <?php
                        $val = getVal($sol, $campos_eav, $campo['clave']);
                        $peso = (float) ($campo['peso'] ?? 0);
                        $hasPeso = $peso > 0;
                        ?>
                        <div class="field-cell <?= $hasPeso ? 'has-peso' : '' ?>">
                            <div class="field-label">
                                <?= esc($campo['label']) ?>
                                <?php if ($hasPeso): ?><span style="color:#1a56db;font-size:9px">
                                        (<?= round($peso * 100) ?>%)</span><?php endif; ?>
                            </div>
                            <?php if ($val !== ''): ?>
                                <div class="field-value"><?= esc($val) ?></div>
                            <?php else: ?>
                                <div class="field-empty">Sin completar</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- ── HISTORIAL ── -->
        <?php if (!empty($historial)): ?>
            <div class="section avoid-break" style="margin-top:10px">
                <div class="section-header">📜 Últimos Cambios (Historial)</div>
                <table class="historial-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Estado Anterior</th>
                            <th>Nuevo Estado</th>
                            <th>Usuario</th>
                            <th>Comentario</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historial as $h): ?>
                            <tr>
                                <td><?= fmtDate($h['fecha']) ?></td>
                                <td>
                                    <?php if ($h['estado_desde']): ?>
                                        <span class="estado-badge"
                                            style="background:<?= estadoBg($h['estado_desde']) ?>;color:<?= estadoColor($h['estado_desde']) ?>">
                                            <?= estadoLabel($h['estado_desde']) ?>
                                        </span>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td>
                                    <span class="estado-badge"
                                        style="background:<?= estadoBg($h['estado_hasta']) ?>;color:<?= estadoColor($h['estado_hasta']) ?>">
                                        <?= estadoLabel($h['estado_hasta']) ?>
                                    </span>
                                </td>
                                <td><?= esc($h['usuario_nombre']) ?></td>
                                <td><?= esc($h['comentario'] ?: '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- ══════════════════════════════════════════════════════════ -->
        <!--  RESUMEN DE INFORMACIÓN FALTANTE                          -->
        <!-- ══════════════════════════════════════════════════════════ -->
        <div class="avoid-break" style="margin-top:10px;">
            <div class="missing-header">
                <span style="font-size:14px">⚠️</span>
                <h2>Resumen de Información Faltante</h2>
            </div>

            <?php if (empty($campos_faltantes)): ?>
                <div class="all-ok" style="border-radius:0 0 8px 8px">
                    ✅ Todos los campos con peso de evaluación están completos.
                </div>
            <?php else: ?>
                <table class="missing-table" style="border-radius:0 0 8px 8px;overflow:hidden">
                    <thead>
                        <tr>
                            <th style="width:35%">Sección</th>
                            <th style="width:50%">Campo Faltante</th>
                            <th style="width:15%;text-align:center">Impacto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $secciones_faltantes = [];
                        foreach ($campos_faltantes as $item) {
                            $secciones_faltantes[$item['seccion']][] = $item;
                        }
                        foreach ($secciones_faltantes as $sec_name => $items):
                            foreach ($items as $idx => $item):
                                ?>
                                <tr>
                                    <td><?= $idx === 0 ? esc($sec_name) : '' ?></td>
                                    <td><strong><?= esc($item['label']) ?></strong></td>
                                    <td class="pct-col"><?= round($item['peso'] * 100) ?>%</td>
                                </tr>
                                <?php
                            endforeach;
                        endforeach;
                        ?>
                        <tr class="total-row">
                            <td colspan="2">TOTAL DE PUNTUACIÓN NO COMPLETADA</td>
                            <td class="pct-col"><?= round($total_perdido) ?>%</td>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- ── FOOTER ── -->
        <div class="doc-footer avoid-break">
            Documento generado el <?= date('d/m/Y H:i') ?> por <?= esc($_SESSION['nombre']) ?>
            &nbsp;•&nbsp; Solicitud <?= esc($sol['folio']) ?>
            &nbsp;•&nbsp; Sistema Factibilidad DFM &nbsp;•&nbsp; Confidencial
        </div>

    </div><!-- /page -->

    <script>
        if (new URLSearchParams(window.location.search).get('print') === '1') {
            window.addEventListener('load', () => setTimeout(() => window.print(), 400));
        }
    </script>
</body>

</html>