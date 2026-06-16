<?php
/**
 * reclamos_semana.php
 * Reporte de "reclamos" definidos como ASISTENCIA='NO' en la tabla `acceso_pae`.
 * Permite seleccionar rango de fechas y filtrar por grado.
 */
require 'conexion.php';

// Cargar grados para filtro
$grados = [];
try {
    $rg = $conexion->query("SELECT id_grado, nombre_grado FROM grado ORDER BY nombre_grado");
    while ($g = $rg->fetch_assoc()) $grados[] = $g;
} catch (Throwable $e) {
    // tabla grado puede no existir
}

// Fecha por defecto: inicio de semana (lunes) hasta hoy
$today = date('Y-m-d');
$monday = date('Y-m-d', strtotime('monday this week'));
if (date('N') == 1) { $monday = date('Y-m-d'); } // hoy es lunes

$from = $_GET['from'] ?? $monday;
$to   = $_GET['to']   ?? $today;
$filter_grado = isset($_GET['grado']) ? intval($_GET['grado']) : 0;

$rows = [];
$total_reclamos = 0;

$display_sql = '';

// Comprobar que la tabla acceso_pae existe
$tableExists = false;
try {
    $res = $conexion->query("SHOW TABLES LIKE 'acceso_pae'");
    $tableExists = ($res && $res->num_rows > 0);
} catch (Throwable $e) {
}

if ($tableExists) {
    try {
        // Construir consulta con parámetros
        $params = [];
        $sql = "SELECT a.id_estudiante, e.nombre, COUNT(*) AS veces
                FROM acceso_pae a
                LEFT JOIN estudiante e ON a.id_estudiante = e.id_estudiante
                LEFT JOIN grupo g ON e.id_grupo = g.id_grupo
                LEFT JOIN grado gr ON g.id_grado = gr.id_grado
                WHERE a.ASISTENCIA = 'NO' AND a.FECHA_ACCESO BETWEEN ? AND ?";

        $params[] = $from;
        $params[] = $to;

        if ($filter_grado > 0) {
            $sql .= " AND gr.id_grado = ?";
            $params[] = $filter_grado;
        }

        $sql .= " GROUP BY a.id_estudiante ORDER BY veces DESC";

        // Preparar versión para mostrar (reemplaza ? por valores escapados)
        $display_sql = $sql;
        foreach ($params as $p) {
          $escaped = $conexion->real_escape_string($p);
          $display_sql = preg_replace('/\?/', "'" . $escaped . "'", $display_sql, 1);
        }

        $stmt = $conexion->prepare($sql);
        // bind parameters dinámicamente
        if (count($params) === 2) {
          $stmt->bind_param('ss', $params[0], $params[1]);
        } elseif (count($params) === 3) {
          $stmt->bind_param('ssi', $params[0], $params[1], $params[2]);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
            $total_reclamos += (int)$r['veces'];
        }
    } catch (Throwable $e) {
        // Silenciar errores, $rows quedará vacío
    }
}

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reclamos semanales</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Reclamos (ASISTENCIA = 'NO')</h3>
    <div>
      <a class="btn btn-outline-secondary btn-sm" href="index.php">Volver</a>
    </div>
  </div>

  <?php if (!$tableExists): ?>
    <div class="alert alert-warning">La tabla <code>acceso_pae</code> no existe. Usa <code>create_acceso_pae.php</code> o importa el dump.</div>
  <?php endif; ?>

  <form method="GET" class="row g-2 mb-3">
    <div class="col-auto">
      <label class="form-label">Desde</label>
      <input type="date" name="from" class="form-control form-control-sm" value="<?php echo htmlspecialchars($from); ?>">
    </div>
    <div class="col-auto">
      <label class="form-label">Hasta</label>
      <input type="date" name="to" class="form-control form-control-sm" value="<?php echo htmlspecialchars($to); ?>">
    </div>
    <div class="col-auto">
      <label class="form-label">Filtrar por grado</label>
      <select name="grado" class="form-select form-select-sm">
        <option value="0">Todos los grados</option>
        <?php foreach ($grados as $gr): ?>
          <option value="<?php echo (int)$gr['id_grado']; ?>" <?php if ($filter_grado == $gr['id_grado']) echo 'selected'; ?>><?php echo htmlspecialchars($gr['nombre_grado']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto align-self-end">
      <button class="btn btn-sm btn-primary" type="submit">Aplicar</button>
      <a class="btn btn-sm btn-outline-secondary" href="reclamos_semana.php">Limpiar</a>
    </div>
  </form>

  <div class="card p-3 mb-3">
    <div class="d-flex justify-content-between">
      <div>Total reclamos en rango: <strong><?php echo $total_reclamos; ?></strong></div>
      <div>Rango: <strong><?php echo htmlspecialchars($from); ?></strong> — <strong><?php echo htmlspecialchars($to); ?></strong></div>
    </div>
  </div>

  <div class="mb-3">
    <button class="btn btn-sm btn-outline-info" type="button" data-bs-toggle="collapse" data-bs-target="#sqlBox" aria-expanded="false" aria-controls="sqlBox">Ver consulta</button>
    <div class="collapse mt-2" id="sqlBox">
      <div class="card card-body">
        <pre style="white-space:pre-wrap;word-break:break-word;"><?php echo htmlspecialchars($display_sql); ?></pre>
      </div>
    </div>
  </div>

  <div class="card p-2">
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>Estudiante</th>
            <th>Veces (<?php echo htmlspecialchars($from); ?> → <?php echo htmlspecialchars($to); ?>)</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="2" class="text-center small-muted">No se encontraron reclamos en el rango seleccionado.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?php echo htmlspecialchars($r['nombre'] ?? ('ID ' . (int)$r['id_estudiante'])); ?></td>
              <td><?php echo (int)$r['veces']; ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
