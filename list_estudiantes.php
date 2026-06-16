<?php
/**
 * list_estudiantes.php
 * Muestra una tabla de estudiantes con su grupo y grado.
 */
require 'conexion.php';

$nivel = isset($_GET['nivel']) && in_array($_GET['nivel'], ['bachillerato', 'primaria'], true) ? $_GET['nivel'] : '';
function getLevelGradeCondition($nivel) {
    if ($nivel === 'bachillerato') {
        return "(
            LOWER(gr.nombre_grado) LIKE '%bachillerato%' OR
            LOWER(gr.nombre_grado) LIKE '%sexto%' OR
            LOWER(gr.nombre_grado) LIKE '%septimo%' OR
            LOWER(gr.nombre_grado) LIKE '%octavo%' OR
            LOWER(gr.nombre_grado) LIKE '%noveno%' OR
            LOWER(gr.nombre_grado) LIKE '%decimo%' OR
            LOWER(gr.nombre_grado) LIKE '%once%'
        )";
    }
    if ($nivel === 'primaria') {
        return "(
            LOWER(gr.nombre_grado) LIKE '%primaria%' OR
            LOWER(gr.nombre_grado) LIKE '%primero%' OR LOWER(gr.nombre_grado) LIKE '%1°%' OR LOWER(gr.nombre_grado) LIKE '%1%' OR
            LOWER(gr.nombre_grado) LIKE '%segundo%' OR LOWER(gr.nombre_grado) LIKE '%2°%' OR LOWER(gr.nombre_grado) LIKE '%2%' OR
            LOWER(gr.nombre_grado) LIKE '%tercero%' OR LOWER(gr.nombre_grado) LIKE '%3°%' OR LOWER(gr.nombre_grado) LIKE '%3%' OR
            LOWER(gr.nombre_grado) LIKE '%cuarto%' OR LOWER(gr.nombre_grado) LIKE '%4°%' OR LOWER(gr.nombre_grado) LIKE '%4%' OR
            LOWER(gr.nombre_grado) LIKE '%quinto%' OR LOWER(gr.nombre_grado) LIKE '%5°%' OR LOWER(gr.nombre_grado) LIKE '%5%'
        )";
    }
    return '';
}

function getGradeOrderSql($alias = 'gr.nombre_grado') {
    return "CASE
        WHEN LOWER($alias) LIKE '%primero%' OR LOWER($alias) LIKE '%1°%' OR LOWER($alias) LIKE '%1' THEN 1
        WHEN LOWER($alias) LIKE '%segundo%' OR LOWER($alias) LIKE '%2°%' OR LOWER($alias) LIKE '%2' THEN 2
        WHEN LOWER($alias) LIKE '%tercero%' OR LOWER($alias) LIKE '%3°%' OR LOWER($alias) LIKE '%3' THEN 3
        WHEN LOWER($alias) LIKE '%cuarto%' OR LOWER($alias) LIKE '%4°%' OR LOWER($alias) LIKE '%4' THEN 4
        WHEN LOWER($alias) LIKE '%quinto%' OR LOWER($alias) LIKE '%5°%' OR LOWER($alias) LIKE '%5' THEN 5
        WHEN LOWER($alias) LIKE '%sexto%' OR LOWER($alias) LIKE '%6°%' OR LOWER($alias) LIKE '%6' THEN 6
        WHEN LOWER($alias) LIKE '%septimo%' OR LOWER($alias) LIKE '%7°%' OR LOWER($alias) LIKE '%7' THEN 7
        WHEN LOWER($alias) LIKE '%octavo%' OR LOWER($alias) LIKE '%8°%' OR LOWER($alias) LIKE '%8' THEN 8
        WHEN LOWER($alias) LIKE '%noveno%' OR LOWER($alias) LIKE '%9°%' OR LOWER($alias) LIKE '%9' THEN 9
        WHEN LOWER($alias) LIKE '%decimo%' OR LOWER($alias) LIKE '%10°%' OR LOWER($alias) LIKE '%10' THEN 10
        WHEN LOWER($alias) LIKE '%once%' OR LOWER($alias) LIKE '%11°%' OR LOWER($alias) LIKE '%11' THEN 11
        ELSE 99
    END";
}

$level_where = $nivel ? getLevelGradeCondition($nivel) : '';

// Cargar lista de grados para filtro
$grados = [];
try {
  $sqlGrados = "SELECT id_grado, nombre_grado FROM grado gr";
  if ($nivel) {
    $sqlGrados .= " WHERE " . getLevelGradeCondition($nivel);
  }
  $sqlGrados .= " ORDER BY " . getGradeOrderSql('gr.nombre_grado');
  $rg = $conexion->query($sqlGrados);
  while ($g = $rg->fetch_assoc()) $grados[] = $g;
} catch (Throwable $e) {
  // ignorar si no existe la tabla grado
}

// Filtro por grado (GET)
$filter_grado = isset($_GET['grado']) ? intval($_GET['grado']) : 0;

// Manejar eliminación (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
  $id = intval($_POST['id']);
  if ($id > 0) {
    try {
      $stmt = $conexion->prepare('DELETE FROM estudiante WHERE id_estudiante = ?');
      $stmt->bind_param('i', $id);
      $stmt->execute();
    } catch (Throwable $e) {
      // no hacemos nada especial en caso de error de borrado
    }
  }
  $redirect = 'list_estudiantes.php' . ($nivel ? '?nivel=' . urlencode($nivel) : '');
  header('Location: ' . $redirect);
  exit;
}

$rows = [];
try {
  if ($filter_grado > 0) {
    $sql = "SELECT e.id_estudiante, e.nombre, e.documento, e.email, e.estado, g.nombre_grupo, gr.nombre_grado
      FROM estudiante e
      LEFT JOIN grupo g ON e.id_grupo = g.id_grupo
      LEFT JOIN grado gr ON g.id_grado = gr.id_grado
      WHERE gr.id_grado = " . $conexion->real_escape_string($filter_grado);
    if ($level_where) {
        $sql .= " AND " . $level_where;
    }
    $sql .= " ORDER BY " . getGradeOrderSql('gr.nombre_grado') . ", g.nombre_grupo, e.nombre";
  } elseif ($level_where) {
    $sql = "SELECT e.id_estudiante, e.nombre, e.documento, e.email, e.estado, g.nombre_grupo, gr.nombre_grado
      FROM estudiante e
      LEFT JOIN grupo g ON e.id_grupo = g.id_grupo
      LEFT JOIN grado gr ON g.id_grado = gr.id_grado
      WHERE " . $level_where . "
      ORDER BY " . getGradeOrderSql('gr.nombre_grado') . ", g.nombre_grupo, e.nombre";
  } else {
    $sql = "SELECT e.id_estudiante, e.nombre, e.documento, e.email, e.estado, g.nombre_grupo, gr.nombre_grado
      FROM estudiante e
      LEFT JOIN grupo g ON e.id_grupo = g.id_grupo
      LEFT JOIN grado gr ON g.id_grado = gr.id_grado
      ORDER BY " . getGradeOrderSql('gr.nombre_grado') . ", g.nombre_grupo, e.nombre";
  }
  $res = $conexion->query($sql);
  while ($r = $res->fetch_assoc()) $rows[] = $r;
} catch (Throwable $e) {
  // ignorar, $rows quedará vacío
}

// Conteo de comidas en la semana actual por estudiante (ASISTENCIA = 'SI')
$ate_counts = [];
try {
    $q = "SELECT id_estudiante, COUNT(*) AS veces FROM acceso_pae WHERE ASISTENCIA = 'SI' AND YEARWEEK(FECHA_ACCESO,1) = YEARWEEK(CURDATE(),1) GROUP BY id_estudiante";
    $res2 = $conexion->query($q);
    while ($rr = $res2->fetch_assoc()) {
        $ate_counts[(int)$rr['id_estudiante']] = (int)$rr['veces'];
    }
} catch (Throwable $e) {
    // si la tabla no existe o hay error, dejar vacío
}

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Estudiantes</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Estudiantes<?php echo $nivel ? ' - ' . ucfirst($nivel) : ''; ?></h3>
        <div>
          <a class="btn btn-primary btn-sm" href="add_estudiante.php<?php echo $nivel ? '?nivel=' . urlencode($nivel) : ''; ?>">Agregar Estudiante</a>
          <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars($nivel === 'bachillerato' ? 'bachillerato.php' : ($nivel === 'primaria' ? 'primaria.php' : 'index.php')); ?>">Volver</a>
        </div>
  </div>

  <form method="GET" class="row g-2 mb-3 align-items-center">
    <?php if ($nivel): ?>
      <input type="hidden" name="nivel" value="<?php echo htmlspecialchars($nivel); ?>">
    <?php endif; ?>
    <div class="col-auto">
      <label class="form-label mb-0">Filtrar por grado</label>
    </div>
    <div class="col-auto">
      <select name="grado" class="form-select form-select-sm">
        <option value="0">Todos los grados</option>
        <?php foreach ($grados as $gr): ?>
          <option value="<?php echo (int)$gr['id_grado']; ?>" <?php if ($filter_grado == $gr['id_grado']) echo 'selected'; ?>><?php echo htmlspecialchars($gr['nombre_grado']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <button class="btn btn-sm btn-secondary" type="submit">Aplicar</button>
      <a class="btn btn-sm btn-outline-secondary" href="list_estudiantes.php<?php echo $nivel ? '?nivel=' . urlencode($nivel) : ''; ?>">Limpiar</a>
    </div>
  </form>

  <div class="card p-2">
    <div class="table-responsive">
      <?php if ($filter_grado > 0): ?>
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Documento</th>
            <th>Email</th>
            <th>Grado / Grupo</th>
            <th>Esta semana</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="8" class="text-center small-muted">No hay estudiantes registrados.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?php echo (int)$r['id_estudiante']; ?></td>
              <td><?php echo htmlspecialchars($r['nombre']); ?></td>
              <td><?php echo htmlspecialchars($r['documento']); ?></td>
              <td><?php echo htmlspecialchars($r['email']); ?></td>
              <td><?php echo htmlspecialchars(($r['nombre_grado'] ?? '-') . ' / ' . ($r['nombre_grupo'] ?? '-')); ?></td>
              <td><?php echo isset($ate_counts[(int)$r['id_estudiante']]) ? (int)$ate_counts[(int)$r['id_estudiante']] : 0; ?></td>
              <td><?php echo htmlspecialchars($r['estado']); ?></td>
              <td>
                <div class="d-flex gap-2">
                  <a class="btn btn-sm btn-outline-primary" href="add_estudiante.php?id=<?php echo (int)$r['id_estudiante']; ?><?php echo $nivel ? '&nivel=' . urlencode($nivel) : ''; ?>">Editar</a>
                  <form method="POST" onsubmit="return confirm('¿Eliminar este estudiante? Esta acción es irreversible.');" style="display:inline-block;margin:0;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo (int)$r['id_estudiante']; ?>">
                    <?php if ($nivel): ?>
                      <input type="hidden" name="nivel" value="<?php echo htmlspecialchars($nivel); ?>">
                    <?php endif; ?>
                    <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
      <?php else: ?>
        <?php
          // Agrupar por grado
          $grouped = [];
          foreach ($rows as $r) {
              $gname = $r['nombre_grado'] ?? 'Sin grado';
              if (!isset($grouped[$gname])) $grouped[$gname] = [];
              $grouped[$gname][] = $r;
          }
        ?>
        <?php if (empty($grouped)): ?>
          <div class="text-center small-muted p-3">No hay estudiantes registrados.</div>
        <?php else: ?>
          <?php foreach ($grouped as $gname => $students): ?>
            <h5 class="mt-3 mb-2"><?php echo htmlspecialchars($gname); ?></h5>
            <table class="table table-sm table-hover mb-3">
              <thead class="table-light">
                <tr>
                  <th>ID</th>
                  <th>Nombre</th>
                  <th>Documento</th>
                  <th>Email</th>
                  <th>Grado / Grupo</th>
                  <th>Esta semana</th>
                  <th>Estado</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($students as $r): ?>
                  <tr>
                    <td><?php echo (int)$r['id_estudiante']; ?></td>
                    <td><?php echo htmlspecialchars($r['nombre']); ?></td>
                    <td><?php echo htmlspecialchars($r['documento']); ?></td>
                    <td><?php echo htmlspecialchars($r['email']); ?></td>
                    <td><?php echo htmlspecialchars(($r['nombre_grado'] ?? '-') . ' / ' . ($r['nombre_grupo'] ?? '-')); ?></td>
                    <td><?php echo isset($ate_counts[(int)$r['id_estudiante']]) ? (int)$ate_counts[(int)$r['id_estudiante']] : 0; ?></td>
                    <td><?php echo htmlspecialchars($r['estado']); ?></td>
                    <td>
                      <div class="d-flex gap-2">
                        <a class="btn btn-sm btn-outline-primary" href="add_estudiante.php?id=<?php echo (int)$r['id_estudiante']; ?>">Editar</a>
                        <form method="POST" onsubmit="return confirm('¿Eliminar este estudiante? Esta acción es irreversible.');" style="display:inline-block;margin:0;">
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="id" value="<?php echo (int)$r['id_estudiante']; ?>">
                          <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endforeach; ?>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
