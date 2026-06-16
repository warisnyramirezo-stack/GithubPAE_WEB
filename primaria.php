<?php
/**
 * primaria.php - Página independiente para Primaria PAE
 */
require 'conexion.php';

// Fecha (permitir override por POST)
$fecha_input = isset($_POST['fecha']) ? $_POST['fecha'] : date("Y-m-d");
$ts = strtotime($fecha_input);
if ($ts === false) { $fecha = date("Y-m-d"); } else { $fecha = date("Y-m-d", $ts); }

if (!function_exists('tableExists')) {
    function tableExists($conn, $table) {
        $table_esc = $conn->real_escape_string($table);
        $res = $conn->query("SHOW TABLES LIKE '" . $table_esc . "'");
        return ($res && $res->num_rows > 0);
    }
}

$grade_patterns = [
    "%primaria%",
    "%primero%", "%1%", "%1°%",
    "%segundo%", "%2%", "%2°%",
    "%tercero%", "%3%", "%3°%",
    "%cuarto%", "%4%", "%4°%",
    "%quinto%", "%5%", "%5°%",
];

// Construir condición SQL segura que cubra primero->quinto
$conds = [];
foreach ($grade_patterns as $p) {
    $conds[] = "LOWER(gr.nombre_grado) LIKE '" . $conexion->real_escape_string($p) . "'";
}
$grade_where = implode(' OR ', $conds);

// Totales: contar solo estudiantes que pertenecen a grados de Primaria (1º-5º)
$res1 = $conexion->query("SELECT COUNT(*) AS total FROM estudiante e
    LEFT JOIN grupo g ON e.id_grupo = g.id_grupo
    LEFT JOIN grado gr ON g.id_grado = gr.id_grado
    WHERE (" . $grade_where . ")");
$total_estudiantes = $res1->fetch_assoc()['total'] ?? 0;

if (tableExists($conexion, 'acceso_pae')) {
    $fecha_esc = $conexion->real_escape_string($fecha);
    // Contar accesos solo de estudiantes de primaria
    $res2 = $conexion->query("SELECT COUNT(*) AS total FROM acceso_pae a
        JOIN estudiante e ON a.id_estudiante = e.id_estudiante
        JOIN grupo g ON e.id_grupo = g.id_grupo
        JOIN grado gr ON g.id_grado = gr.id_grado
        WHERE a.FECHA_ACCESO = '" . $fecha_esc . "' AND a.ASISTENCIA = 'SI' AND (" . $grade_where . ")");
    $total_comieron = $res2->fetch_assoc()['total'] ?? 0;

    $res3 = $conexion->query("SELECT COUNT(*) AS total FROM acceso_pae a
        JOIN estudiante e ON a.id_estudiante = e.id_estudiante
        JOIN grupo g ON e.id_grupo = g.id_grupo
        JOIN grado gr ON g.id_grado = gr.id_grado
        WHERE a.FECHA_ACCESO = '" . $fecha_esc . "' AND a.ASISTENCIA = 'NO' AND (" . $grade_where . ")");
    $total_no = $res3->fetch_assoc()['total'] ?? 0;
} else {
    $fecha_esc = $conexion->real_escape_string($fecha);
    // Fallback: contar entregas asociadas a estudiantes de primaria (si la tabla entrega tiene id_estudiante)
    $res2 = $conexion->query("SELECT COUNT(*) AS total FROM entrega_alimentacion_estudiantes ea
        JOIN estudiante e ON ea.id_estudiante = e.id_estudiante
        JOIN grupo g ON e.id_grupo = g.id_grupo
        JOIN grado gr ON g.id_grado = gr.id_grado
        WHERE ea.fecha = '" . $fecha_esc . "' AND (" . $grade_where . ")");
    $total_comieron = $res2->fetch_assoc()['total'] ?? 0;
    $total_no = max(0, $total_estudiantes - $total_comieron);
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Primaria PAE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; }
        .card-custom { border-radius: 14px; box-shadow: 0 6px 18px rgba(0,0,0,0.08); }
        .numero { font-size: 2.5rem; font-weight: 700; }
        .small-muted { color: #6c757d; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#">PAE - Primaria</a>
        <div class="d-flex">
            <a class="btn btn-outline-secondary btn-sm me-2" href="index.html">Volver</a>
            <a class="btn btn-primary btn-sm me-2" href="list_estudiantes.php?nivel=primaria">Lista de estudiantes</a>
            <a class="btn btn-success btn-sm" href="add_estudiante.php?nivel=primaria">Registrar</a>
        </div>
    </div>
</nav>

<main class="container py-4">
    <div class="row align-items-center top-bar mb-4">
        <div class="col-md-8">
            <h1 class="h3 mb-0">Primaria PAE</h1>
            <div class="small-muted">Reporte - Vaso de leche (Primaria)</div>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <form method="POST" class="d-inline-flex gap-2 align-items-center">
                <input class="form-control form-control-sm" type="date" name="fecha" value="<?php echo htmlspecialchars($fecha); ?>">
                <button class="btn btn-sm btn-success" type="submit">Consultar</button>
            </form>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-sm-6 col-md-4">
            <div class="card card-custom p-3 h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">Total Estudiantes</h6>
                        <div class="numero text-primary"><?php echo htmlspecialchars($total_estudiantes); ?></div>
                    </div>
                    <div class="text-end"><div class="fs-4">👥</div></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-4">
            <div class="card card-custom p-3 h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">Recibieron vaso de leche</h6>
                        <div class="numero text-success"><?php echo htmlspecialchars($total_comieron); ?></div>
                    </div>
                    <div class="text-end"><div class="fs-4">✅</div></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-4">
            <div class="card card-custom p-3 h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">No recibieron vaso de leche</h6>
                        <div class="numero text-danger"><?php echo htmlspecialchars($total_no); ?></div>
                    </div>
                    <div class="text-end"><div class="fs-4">❌</div></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card p-3">
                <div class="small-muted">Fecha seleccionada: <strong><?php echo htmlspecialchars($fecha); ?></strong></div>
            </div>
        </div>
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
