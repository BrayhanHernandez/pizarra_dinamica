<?php
require_once __DIR__.'/conexion.php';
$mensaje = "";

if($_SERVER["REQUEST_METHOD"]==="POST"){
  $nombre   = trim($_POST['nombre']  ?? '');
  $correo   = trim($_POST['correo']  ?? '');
  $password = $_POST['password']     ?? '';
  $rol      = $_POST['rol']          ?? 'invitado';

  // Whitelist de roles
  $rol = in_array($rol, ['invitado','presentador'], true) ? $rol : 'invitado';

  if($nombre==='' || $correo==='' || $password===''){
    $mensaje = "Todos los campos son obligatorios.";
  } elseif(!filter_var($correo, FILTER_VALIDATE_EMAIL)){
    $mensaje = "Correo inválido.";
  } else {
    $stmt = $conn->prepare("SELECT id FROM registro WHERE correo = ? LIMIT 1");
    $stmt->bind_param("s",$correo);
    $stmt->execute();
    if($stmt->get_result()->num_rows>0){
      $mensaje = " El correo ya está registrado.";
    } else {
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $stmt2 = $conn->prepare("INSERT INTO registro (nombre, correo, password, rol) VALUES (?,?,?,?)");
      $stmt2->bind_param("ssss",$nombre,$correo,$hash,$rol);
      if($stmt2->execute()){
        header("Location: login.php"); exit();
      } else {
        $mensaje = " Error al registrar.";
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registro - Pizarra colaborativa</title>
  <!-- OJO: usa el nombre correcto de tu CSS: styles_registro.css o style_registro.css -->
  <link rel="stylesheet" href="css/styles_registro.css">
</head>
<body>
  <div class="container">
    <div class="center">
      <h1>Registro</h1>
      <p class="sub-text">Crea tu cuenta para unirte</p>

      <form action="registro.php" method="POST" autocomplete="off">
        <input
          type="text"
          name="nombre"
          placeholder="Nombre completo"
          value="<?= htmlspecialchars($_POST['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
          required>

        <input
          type="email"
          name="correo"
          placeholder="Correo institucional"
          value="<?= htmlspecialchars($_POST['correo'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
          required>

        <select name="rol" required>
          <option value="invitado"     <?= (($_POST['rol'] ?? '')==='invitado')     ? 'selected' : '' ?>>Invitado</option>
          <option value="presentador"  <?= (($_POST['rol'] ?? '')==='presentador')  ? 'selected' : '' ?>>Presentador</option>
        </select>

        <input
          type="password"
          name="password"
          placeholder="Contraseña"
          required>

        <button type="submit">Registrarse</button>
      </form>

      <?php if ($mensaje !== ""): ?>
        <p class="error"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></p>
      <?php endif; ?>

      <p class="register">¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a></p>
    </div>
  </div>
</body>
</html>
