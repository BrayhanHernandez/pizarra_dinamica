<?php
session_start();

/* ==== Conexión a BD ==== */
$host = "localhost";
$user = "root";
$password = "";
$dbname = "pizarra";

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

/* ==== Procesar POST ==== */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Sanitización básica
    $nombre   = trim($_POST['nombre']  ?? '');
    $correo   = trim($_POST['correo']  ?? '');
    $pass     = trim($_POST['password'] ?? '');
    $rol      = trim($_POST['rol'] ?? 'invitado');

    // Whitelist de rol
    $rol = in_array($rol, ['invitado','presentador'], true) ? $rol : 'invitado';

    $errores = [];

    // Validaciones
    if ($nombre === '' || $correo === '' || $pass === '') {
        $errores[] = "Todos los campos son obligatorios.";
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "El formato del correo electrónico no es válido.";
    }

    if (strlen($pass) < 6) {
        $errores[] = "La contraseña debe tener al menos 6 caracteres.";
    }

    // Si todo ok, revisar duplicado e insertar
    if (empty($errores)) {

        // ¿Ese correo ya existe en 'registro'?
        $stmt = $conn->prepare("SELECT id FROM registro WHERE correo = ? LIMIT 1");
        if (!$stmt) {
            $errores[] = "Error interno (prep dup): " . $conn->error;
        } else {
            $stmt->bind_param("s", $correo);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $errores[] = "El correo ya está registrado.";
            }
            $stmt->close();
        }

        // Insertar si no hubo error por duplicado
        if (empty($errores)) {
            $hash = password_hash($pass, PASSWORD_DEFAULT);

            $stmt2 = $conn->prepare("INSERT INTO registro (nombre, correo, password, rol) VALUES (?,?,?,?)");
            if (!$stmt2) {
                $errores[] = "Error interno (prep ins): " . $conn->error;
            } else {
                $stmt2->bind_param("ssss", $nombre, $correo, $hash, $rol);
                if ($stmt2->execute()) {
                    // Éxito → redirigir a login
                    header("Location: login.php");
                    exit();
                } else {
                    $errores[] = "Error al registrar: " . $stmt2->error;
                }
                $stmt2->close();
            }
        }
    }

    // Si hubo errores, mostrarlos
    if (!empty($errores)) {
        echo "<h1>Error en el Registro</h1>";
        echo "<ul>";
        foreach ($errores as $e) {
            echo "<li>" . htmlspecialchars($e, ENT_QUOTES, 'UTF-8') . "</li>";
        }
        echo "</ul>";
        echo "<a href='registro.php'>Volver al formulario</a>";
    }

} else {
    // Si llega por GET, lo mandamos al formulario
    header("Location: registro.php");
    exit();
}
