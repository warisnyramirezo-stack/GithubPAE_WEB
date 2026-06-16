<?php
/**
 * add_estudiante.php
 * Formulario para agregar estudiantes a la tabla `estudiante`.
 * Usa prepared statements y carga los `grupo` existentes para selección.
 */
require_once 'conexion.php';

$errors = [];
$success = false;

$nivel = isset($_REQUEST['nivel']) && in_array($_REQUEST['nivel'], ['bachillerato', 'primaria'], true) ? $_REQUEST['nivel'] : '';
$editId = isset($_REQUEST['id']) && intval($_REQUEST['id']) > 0 ? intval($_REQUEST['id']) : 0;

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
            LOWER(gr.nombre_grado) LIKE '%primero%' OR LOWER(gr.nombre_grado) LIKE '%1%' OR LOWER(gr.nombre_grado) LIKE '%1°%' OR
            LOWER(gr.nombre_grado) LIKE '%segundo%' OR LOWER(gr.nombre_grado) LIKE '%2%' OR LOWER(gr.nombre_grado) LIKE '%2°%' OR
            LOWER(gr.nombre_grado) LIKE '%tercero%' OR LOWER(gr.nombre_grado) LIKE '%3%' OR LOWER(gr.nombre_grado) LIKE '%3°%' OR
            LOWER(gr.nombre_grado) LIKE '%cuarto%' OR LOWER(gr.nombre_grado) LIKE '%4%' OR LOWER(gr.nombre_grado) LIKE '%4°%' OR
            LOWER(gr.nombre_grado) LIKE '%quinto%' OR LOWER(gr.nombre_grado) LIKE '%5%' OR LOWER(gr.nombre_grado) LIKE '%5°%'
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

// Cargar datos de edición y nivel antes de listar grupos
$grupos = [];
$editData = null;
if ($editId > 0) {
    try {
        $stmt = $conexion->prepare(
            'SELECT e.id_grupo, e.nombre, e.documento, e.email, e.estado, gr.nombre_grado
             FROM estudiante e
             LEFT JOIN grupo g ON e.id_grupo = g.id_grupo
             LEFT JOIN grado gr ON g.id_grado = gr.id_grado
             WHERE e.id_estudiante = ?'
        );
        $stmt->bind_param('i', $editId);
        $stmt->execute();
        $editData = $stmt->get_result()->fetch_assoc();
        if ($editData) {
            if (!$nivel && !empty($editData['nombre_grado'])) {
                $grade = strtolower($editData['nombre_grado']);
                if (preg_match('/bachillerato|sexto|septimo|octavo|noveno|decimo|once/', $grade)) {
                    $nivel = 'bachillerato';
                } elseif (preg_match('/primaria|primero|1°|1|segundo|2°|2|tercero|3°|3|cuarto|4°|4|quinto|5°|5/', $grade)) {
                    $nivel = 'primaria';
                }
            }
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $_POST['nombre'] = $editData['nombre'];
                $_POST['documento'] = $editData['documento'];
                $_POST['email'] = $editData['email'];
                $_POST['id_grupo'] = $editData['id_grupo'];
                $_POST['estado'] = $editData['estado'];
            }
        } else {
            $editId = 0;
        }
    } catch (Throwable $e) {
        // ignorar, usar modo creación si no se encuentra
    }
}

try {
    $sql = "SELECT g.id_grupo, g.nombre_grupo, gr.nombre_grado FROM grupo g JOIN grado gr ON g.id_grado = gr.id_grado";
    if ($nivel) {
        $sql .= " WHERE " . getLevelGradeCondition($nivel);
    }
    $sql .= " ORDER BY " . getGradeOrderSql('gr.nombre_grado') . ", g.nombre_grupo";
    $res = $conexion->query($sql);
    while ($r = $res->fetch_assoc()) {
        $grupos[] = $r;
    }
} catch (Throwable $e) {
    // Si no existen tablas relacionadas, dejar grupos vacío
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $editId = isset($_POST['id_estudiante']) ? intval($_POST['id_estudiante']) : $editId;
    $nombre = trim($_POST['nombre'] ?? '');
    $documento = trim($_POST['documento'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $id_grupo = intval($_POST['id_grupo'] ?? 0);
    $estado = ($_POST['estado'] ?? 'activo');

    // Normalizar estado permitido
    $estado = ($estado === 'suplente') ? 'suplente' : 'activo';

    if ($nombre === '') $errors[] = 'El nombre es obligatorio.';
    if ($documento === '') $errors[] = 'El documento es obligatorio.';
    if ($id_grupo <= 0) $errors[] = 'Selecciona un grupo válido.';

    // Validación básica del email (debe existir y tener formato)
    if ($email === '') {
        $errors[] = 'El email es obligatorio.';
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'El email no tiene un formato válido.';
    }

    if ($nivel && $id_grupo > 0) {
        try {
            $check = $conexion->query("SELECT g.id_grupo FROM grupo g JOIN grado gr ON g.id_grado = gr.id_grado WHERE g.id_grupo = " . intval($id_grupo) . " AND " . getLevelGradeCondition($nivel));
            if (!$check || $check->num_rows === 0) {
                $errors[] = 'El grupo seleccionado no corresponde a ' . ($nivel === 'bachillerato' ? 'Bachillerato' : 'Primaria') . '.';
            }
        } catch (Throwable $e) {
            // ignorar, la validación se hace en el formulario cuando sea posible
        }
    }

    if (empty($errors)) {
        try {
            if ($editId > 0) {
                $check = $conexion->prepare('SELECT COUNT(*) AS c FROM estudiante WHERE (documento = ? OR email = ?) AND id_estudiante <> ?');
                $check->bind_param('ssi', $documento, $email, $editId);
                $check->execute();
                $exists = $check->get_result()->fetch_assoc()['c'] ?? 0;
                if ($exists > 0) {
                    $errors[] = 'Documento o email ya existe en la base de datos.';
                } else {
                    $stmt = $conexion->prepare('UPDATE estudiante SET id_grupo = ?, nombre = ?, documento = ?, email = ?, estado = ? WHERE id_estudiante = ?');
                    $stmt->bind_param('issssi', $id_grupo, $nombre, $documento, $email, $estado, $editId);
                    $stmt->execute();
                    $success = true;
                }
            } else {
                $stmt = $conexion->prepare("INSERT INTO estudiante (id_grupo, nombre, documento, email, estado) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param('issss', $id_grupo, $nombre, $documento, $email, $estado);
                $stmt->execute();
                $success = true;
            }
        } catch (mysqli_sql_exception $e) {
            // Manejar errores comunes (duplicados)
            if ($e->getCode() === 1062) {
                $errors[] = 'Documento o email ya existe en la base de datos.';
            } else {
                $errors[] = 'Error al guardar: ' . $e->getMessage();
            }
        }
    }
}

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($nivel ? 'Agregar Estudiante - ' . ucfirst($nivel) : 'Agregar Estudiante'); ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3><?php echo htmlspecialchars($nivel ? 'Agregar Estudiante - ' . ucfirst($nivel) : 'Agregar Estudiante'); ?></h3>
    <div>
      <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars($nivel ? 'list_estudiantes.php?nivel=' . urlencode($nivel) : 'index.php'); ?>">Volver</a>
    </div>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success">Estudiante agregado correctamente.</div>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <ul class="mb-0">
      <?php foreach ($errors as $err) echo '<li>' . htmlspecialchars($err) . '</li>'; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="card p-3">
    <form method="POST">
      <div class="mb-3">
        <label class="form-label">Nombre</label>
        <input class="form-control" name="nombre" required value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>">
      </div>

      <div class="mb-3">
        <label class="form-label">Documento</label>
        <input class="form-control" name="documento" required value="<?php echo htmlspecialchars($_POST['documento'] ?? ''); ?>">
      </div>

      <div class="mb-3">
        <label class="form-label">Email</label>
        <input class="form-control" type="email" name="email" id="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
      </div>

      <?php if ($nivel): ?>
        <input type="hidden" name="nivel" value="<?php echo htmlspecialchars($nivel); ?>">
      <?php endif; ?>
      <div class="mb-3">
        <label class="form-label">Grupo</label>
        <select class="form-select" name="id_grupo" required>
          <option value="0">-- Selecciona un grupo --</option>
          <?php foreach ($grupos as $g): ?>
            <option value="<?php echo (int)$g['id_grupo']; ?>" <?php if (isset($_POST['id_grupo']) && intval($_POST['id_grupo']) === intval($g['id_grupo'])) echo 'selected'; ?>>
              <?php echo htmlspecialchars($g['nombre_grado'] . ' / ' . $g['nombre_grupo']); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if (empty($grupos)): ?>
          <div class="form-text">No se encontraron grupos. Crea `grado` y `grupo` o importa el dump.</div>
        <?php endif; ?>
      </div>

      <div class="mb-3">
        <label class="form-label">Estado</label>
        <select class="form-select" name="estado" required>
          <option value="activo" <?php if (isset($_POST['estado']) && $_POST['estado'] === 'activo') echo 'selected'; ?>>Activo</option>
          <option value="suplente" <?php if (isset($_POST['estado']) && $_POST['estado'] === 'suplente') echo 'selected'; ?>>Suplente</option>
        </select>
      </div>

          <div class="d-flex gap-2">
        <button class="btn btn-primary" type="submit"><?php echo $editId > 0 ? 'Guardar cambios' : 'Agregar'; ?></button>
        <?php if ($editId > 0): ?>
            <input type="hidden" name="id_estudiante" value="<?php echo $editId; ?>">
        <?php endif; ?>
        <a class="btn btn-secondary" href="<?php echo htmlspecialchars($nivel ? 'list_estudiantes.php?nivel=' . urlencode($nivel) : 'index.php'); ?>">Cancelar</a>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- No client-side autocompletion for domain; accept any valid email -->
</body>
</html>
