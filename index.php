<?php
include("conexion.php");

// Normalizar y validar fecha recibida
$fecha_input = isset($_POST['fecha']) ? $_POST['fecha'] : date("Y-m-d");
$ts = strtotime($fecha_input);
if ($ts === false) {
    $fecha = date("Y-m-d");
} else {
    $fecha = date("Y-m-d", $ts);
}

// Helper: comprueba si una tabla existe
if (!function_exists('tableExists')) {
    function tableExists($conn, $table) {
        $table_esc = $conn->real_escape_string($table);
        $res = $conn->query("SHOW TABLES LIKE '" . $table_esc . "'");
        return ($res && $res->num_rows > 0);
    }
}

// Total estudiantes
$res1 = $conexion->query("SELECT COUNT(*) AS total FROM estudiante");
$total_estudiantes = $res1->fetch_assoc()['total'] ?? 0;

// Decidir fuente de datos: `acceso_pae` (si existe) o fallback `entrega_alimentacion_estudiantes`
if (tableExists($conexion, 'acceso_pae')) {
    $fecha_esc = $conexion->real_escape_string($fecha);

    // Comieron
    $res2 = $conexion->query("SELECT COUNT(*) AS total FROM acceso_pae WHERE FECHA_ACCESO = '" . $fecha_esc . "' AND ASISTENCIA = 'SI'");
    $total_comieron = $res2->fetch_assoc()['total'] ?? 0;

    // No comieron
    $res3 = $conexion->query("SELECT COUNT(*) AS total FROM acceso_pae WHERE FECHA_ACCESO = '" . $fecha_esc . "' AND ASISTENCIA = 'NO'");
    $total_no = $res3->fetch_assoc()['total'] ?? 0;
} else {
    // Fallback: usar `entrega_alimentacion_estudiantes` como aproximación
    $fecha_esc = $conexion->real_escape_string($fecha);
    $res2 = $conexion->query("SELECT COUNT(*) AS total FROM entrega_alimentacion_estudiantes WHERE fecha = '" . $fecha_esc . "'");
    $total_comieron = $res2->fetch_assoc()['total'] ?? 0;

    // Calcular no_comieron como diferencia (puede dar negativos si hay inconsistencia)
    $total_no = max(0, $total_estudiantes - $total_comieron);
}
?>

<!doctype html>
<html lang="es">
<head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Reporte PAE</title>
        <!-- Bootstrap CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-" crossorigin="anonymous">
        <style>
                body { background: #f4f6f9; }
                .card-custom { border-radius: 14px; box-shadow: 0 6px 18px rgba(0,0,0,0.08); }
                .numero { font-size: 2.5rem; font-weight: 700; }
                .small-muted { color: #6c757d; }
                .top-bar { padding: 18px 0; }
        </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#">PAE - Reporte</a>
        <div class="d-flex">
            <a class="btn btn-outline-secondary btn-sm me-2" href="index.html">Volver</a>
            <a class="btn btn-primary btn-sm me-2" href="list_estudiantes.php">Lista de estudiantes</a>
            <a class="btn btn-success btn-sm" href="add_estudiante.php">Registrar</a>
        </div>
    </div>
</nav>

<main class="container py-4">
    <div class="row align-items-center top-bar mb-4">
        <div class="col-md-8">
            <h1 class="h3 mb-0">📊 Reporte de Alimentación</h1>
            <div class="small-muted">Resumen por fecha y asistencia</div>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <form method="POST" class="d-inline-flex gap-2 align-items-center">
                <input class="form-control form-control-sm" type="date" name="fecha" value="<?php echo htmlspecialchars($fecha); ?>">
                <button class="btn btn-sm btn-success" type="submit">Consultar</button>
            </form>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-12 text-center">
            <a class="btn btn-primary btn-sm me-2" href="bachillerato.php">Bachillerato PAE</a>
            <a class="btn btn-primary btn-sm" href="primaria.php">Primaria PAE</a>
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
                    <div class="text-end">
                        <div class="fs-4">👥</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-md-4">
            <div class="card card-custom p-3 h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">Comieron</h6>
                        <div class="numero text-success"><?php echo htmlspecialchars($total_comieron); ?></div>
                    </div>
                    <div class="text-end">
                        <div class="fs-4">✅</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-md-4">
            <div class="card card-custom p-3 h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">No Comieron</h6>
                        <div class="numero text-danger"><?php echo htmlspecialchars($total_no); ?></div>
                    </div>
                    <div class="text-end">
                        <div class="fs-4">❌</div>
                    </div>
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

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-" crossorigin="anonymous"></script>
</body>
</html>