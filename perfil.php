<?php
session_start();
require_once __DIR__ . '/conexion.php';

if (!isset($_SESSION['usuario'])) {
  header("Location: login.php");
  exit();
}

$correoSesion = $_SESSION['usuario'];
$nickSesion   = $_SESSION['nick'] ?? '';

$ok = '';
$err = '';

// Cargar datos del usuario en tabla 'registro'
$stmt = $conn->prepare("SELECT id_registro, nombre, correo, password, rol, created_at FROM registro WHERE correo = ? LIMIT 1");
$stmt->bind_param("s", $correoSesion);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();

if (!$user) {
  $err = "No se encontró tu registro. Consulta al administrador.";
} else {
  // Procesar formularios
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_basic') {
      $nuevoNombre = trim($_POST['nombre'] ?? '');
      $nuevoNick   = trim($_POST['nick'] ?? '');

      if ($nuevoNombre === '') {
        $err = "El nombre no puede ir vacío.";
      } else {
        $upd = $conn->prepare("UPDATE registro SET nombre = ? WHERE id = ?");
        $upd->bind_param("si", $nuevoNombre, $user['id']);
        if ($upd->execute()) {
          $ok = "Datos actualizados correctamente.";
          $user['nombre'] = $nuevoNombre;
          $_SESSION['nick'] = $nuevoNick; // lo usamos en room.php como apodo
        } else {
          $err = "No fue posible actualizar tus datos.";
        }
        $upd->close();
      }
    }

    if ($action === 'change_pass') {
      $actual = $_POST['pass_actual'] ?? '';
      $nueva  = $_POST['pass_nueva'] ?? '';
      $conf   = $_POST['pass_conf'] ?? '';

      if ($actual === '' || $nueva === '' || $conf === '') {
        $err = "Completa todos los campos de contraseña.";
      } elseif (!password_verify($actual, $user['password'])) {
        $err = "La contraseña actual no es correcta.";
      } elseif (strlen($nueva) < 8) {
        $err = "La nueva contraseña debe tener al menos 8 caracteres.";
      } elseif ($nueva !== $conf) {
        $err = "La confirmación no coincide.";
      } else {
        $hash = password_hash($nueva, PASSWORD_DEFAULT);
        $updP = $conn->prepare("UPDATE registro SET password = ? WHERE id = ?");
        $updP->bind_param("si", $hash, $user['id']);
        if ($updP->execute()) {
          $ok = "Contraseña actualizada.";
        } else {
          $err = "No fue posible actualizar la contraseña.";
        }
        $updP->close();
      }
    }
  }
}

// Historial de uso desde 'usuarios'
$logs = [];
$logStmt = $conn->prepare("SELECT nombre, rol, correo, fecha_uso FROM usuarios WHERE correo = ? ORDER BY fecha_uso DESC LIMIT 10");
$logStmt->bind_param("s", $correoSesion);
$logStmt->execute();
$logsRes = $logStmt->get_result();
while ($r = $logsRes->fetch_assoc()) $logs[] = $r;
$logStmt->close();

// Cerrar stmt principal
$stmt->close();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Mi perfil</title>
  <style>
    :root{
      --green:#2ecc71; --ink:#111; --muted:#6b7280; --bg:#f6f7f9; --card:#fff; --radius:16px;
      --danger:#cc2e3b; --ok:#16a34a;
    }
    *{box-sizing:border-box}
    html,body{margin:0;padding:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial;color:var(--ink);background:var(--bg)}
    .topbar{display:flex;justify-content:space-between;align-items:center;padding:10px 16px;background:var(--green);color:#fff;position:sticky;top:0}
    .btn{border:0;padding:10px 14px;border-radius:12px;background:#111;color:#fff;text-decoration:none;display:inline-block}
    .wrap{max-width:920px;margin:20px auto;padding:0 16px;display:grid;gap:16px}
    .card{background:var(--card);border-radius:var(--radius);padding:16px;border:1px solid #ececf1}
    .row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
    @media (max-width:820px){ .row{grid-template-columns:1fr} }
    h2{margin:0 0 8px}
    .muted{color:var(--muted);font-size:14px}
    .avatar{width:86px;height:86px;border-radius:50%;overflow:hidden;background:#eee;border:2px solid #e5e7eb}
    .avatar img{width:100%;height:100%;object-fit:cover}
    .grid-2{display:grid;grid-template-columns:108px 1fr;gap:16px;align-items:center}
    label{font-size:14px;color:#374151;margin:8px 0 6px;display:block}
    input{width:100%;padding:10px 12px;border-radius:12px;border:1px solid #e5e7eb;background:#fafafa}
    .actions{display:flex;gap:10px;margin-top:12px}
    .btn-ghost{background:#f3f4f6;color:#111}
    .alert{padding:10px 12px;border-radius:10px;font-size:14px}
    .alert.ok{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
    .alert.err{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
    table{width:100%;border-collapse:collapse;font-size:14px}
    th,td{padding:10px;border-bottom:1px solid #eee;text-align:left}
    th{color:#374151}
    .pill{display:inline-block;padding:4px 8px;border-radius:999px;background:#eef2ff}
  </style>
</head>
<body>
  <header class="topbar">
    <div><strong>Mi perfil</strong></div>
    <nav style="display:flex;gap:10px;align-items:center">
      <a class="btn" href="Home.php">Volver al menú</a>
      <a class="btn" href="login.php" onclick="alert('Sesión cerrada')">Cerrar sesión</a>
    </nav>
  </header>

  <main class="wrap">
    <?php if ($ok): ?><div class="alert ok"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <section class="card">
      <h2>Información de la cuenta</h2>
      <p class="muted">Consulta y actualiza tus datos básicos.</p>
      <?php if ($user): ?>
      <div class="grid-2">
        <div class="avatar">
          <img src="img/avatar.jpg" alt="Avatar">
        </div>
        <div>
          <form method="post">
            <input type="hidden" name="action" value="update_basic">
            <label>Nombre</label>
            <input type="text" name="nombre" value="<?= htmlspecialchars($user['nombre']) ?>" required>
            <label>Correo</label>
            <input type="email" value="<?= htmlspecialchars($user['correo']) ?>" disabled>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div>
                <label>Rol</label>
                <input type="text" value="<?= htmlspecialchars($user['rol']) ?>" disabled>
              </div>
              <div>
                <label>Fecha de registro</label>
                <input type="text" value="<?= htmlspecialchars($user['fecha_registro'] ?? '') ?>" disabled>
              </div>
            </div>
            <label>Apodo (para usar en la sala)</label>
            <input type="text" name="nick" value="<?= htmlspecialchars($nickSesion) ?>" placeholder="Ej. Profe Elena">
            <div class="actions">
              <button class="btn" type="submit">Guardar cambios</button>
              <a class="btn btn-ghost" href="perfil.php">Cancelar</a>
            </div>
          </form>
        </div>
      </div>
      <?php endif; ?>
    </section>

    <section class="card">
      <h2>Cambiar contraseña</h2>
      <p class="muted">Por seguridad, la nueva contraseña debe tener al menos 8 caracteres.</p>
      <form method="post" class="row">
        <input type="hidden" name="action" value="change_pass">
        <div>
          <label>Contraseña actual</label>
          <input type="password" name="pass_actual" required>
        </div>
        <div></div>
        <div>
          <label>Nueva contraseña</label>
          <input type="password" name="pass_nueva" required>
        </div>
        <div>
          <label>Confirmar nueva contraseña</label>
          <input type="password" name="pass_conf" required>
        </div>
        <div></div>
        <div class="actions">
          <button class="btn" type="submit">Actualizar contraseña</button>
        </div>
      </form>
    </section>

    <section class="card">
      <h2>Historial de uso (últimos 10)</h2>
      <table>
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Nombre</th>
            <th>Rol</th>
            <th>Correo</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($logs) === 0): ?>
            <tr><td colspan="4" class="muted">Sin registros.</td></tr>
          <?php else: ?>
            <?php foreach ($logs as $l): ?>
              <tr>
                <td><?= htmlspecialchars($l['fecha_uso']) ?></td>
                <td><?= htmlspecialchars($l['nombre']) ?></td>
                <td><span class="pill"><?= htmlspecialchars($l['rol']) ?></span></td>
                <td><?= htmlspecialchars($l['correo']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </section>
  </main>

  <script>
    // Exponer el nick en window para que room.php pueda reutilizarlo si se requiere
    window.__PROFILE__ = {
      nick: <?= json_encode($nickSesion) ?>
    };
  </script>
</body>
</html>
