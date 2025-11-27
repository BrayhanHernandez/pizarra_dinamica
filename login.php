<?php
session_start();
require_once __DIR__.'/conexion.php';

$mensaje = "";
if($_SERVER["REQUEST_METHOD"]==="POST"){
  $correo = $_POST['correo'] ?? '';
  $password = $_POST['password'] ?? '';

  $stmt = $conn->prepare("SELECT id_registro, nombre, correo, password, rol FROM registro WHERE correo = ? LIMIT 1");
  $stmt->bind_param("s",$correo); $stmt->execute();
  $res = $stmt->get_result();
  if($user = $res->fetch_assoc()){
    if(password_verify($password, $user['password'])){
      $_SESSION['usuario'] = $user['correo'];

      // log de uso en 'usuarios'
      $stmt2 = $conn->prepare("INSERT INTO usuarios (nombre, rol, correo, fecha_uso) VALUES (?,?,?, NOW())");
      $stmt2->bind_param("sss", $user['nombre'], $user['rol'], $user['correo']);
      $stmt2->execute();

      header("Location: Home.php"); exit();
    } else { $mensaje = " Contraseña incorrecta"; }
  } else { $mensaje = " El usuario no existe"; }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pizarra colaborativa - Login</title>
    <link rel="stylesheet" href="css/styles_login.css">
</head>
<body>
    <div class="container">
        <div class="login-box">
            <div class="left">
                <img src="img/imagen1.jpg" alt="Clases online">
            </div>
            
            <div class="right">
                <h1>Pizarra colaborativa</h1>
                <p class="sub-text">Donde puedes explicar y mostrar sin interrupciones</p>
                
                <form action="login.php" method="POST">
                    <input type="email" name="correo" placeholder="Ejemplo: alumno@buap.mx" required>
                    <input type="password" name="password" placeholder="Contraseña" required>
                    <button type="submit">Iniciar sesión</button>
                </form>

                <?php if ($mensaje != ""): ?>
                    <p class="error"><?= $mensaje ?></p>
                <?php endif; ?>

                <p class="register">Nuevo usuario? <a href="registro.php">REGÍSTRATE</a></p>
            </div>
        </div>
    </div>
</body>
</html>




