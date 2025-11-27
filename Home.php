<?php
session_start();

if (empty($_SESSION['usuario'])) {
  header("Location: login.php?next=Home.php");
  exit();
}

/* ===== Datos para el avatar con iniciales ===== */
$__displayName = $_SESSION['nombre'] ?? $_SESSION['usuario'] ?? 'Usuario';

function __initials($s){
  $s = trim($s);
  // Si es correo, usa la parte antes de @
  if (strpos($s, '@') !== false) {
    $s = substr($s, 0, strpos($s, '@'));
  }
  // Divide por espacios/puntos/guiones
  $parts = preg_split('/[\s._-]+/u', $s, -1, PREG_SPLIT_NO_EMPTY);
  $ini = '';
  foreach ($parts as $p) {
    $ini .= mb_strtoupper(mb_substr($p, 0, 1, 'UTF-8'), 'UTF-8');
    if (mb_strlen($ini, 'UTF-8') >= 2) break; // máximo 2 letras
  }
  return $ini ?: 'U';
}

$__initials = __initials($__displayName);
?>


<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Menú principal</title>
  <link rel="stylesheet" href="css/styles_home.css">
</head>
<body>
  <header class="topbar">
    <div class="topbar__left">Menú principal</div>
    <nav class="topbar__right">
  <a class="btn btn--dark" href="perfil.php">Mi perfil</a>
  <a class="btn btn--dark" href="login.php" onclick="alert('Sesión cerrada')">Cerrar sesión</a>

  <!-- Avatar con iniciales -->
  <div class="avatar small" title="<?php echo htmlspecialchars($__displayName); ?>">
    <span class="avatar__text"><?php echo htmlspecialchars($__initials); ?></span>
  </div>
</nav>
  </header>

  <main class="container home">
    <section class="panel panel--start">
      <div class="panel__text">
        <h2>¿QUIERES INICIAR UNA<br/><span>REUNIÓN?</span></h2>
        <button class="cta" id="btnCrear"><span class="icon">✪</span> Crear junta</button>
      </div>
      <div class="panel__media">
        <img src="img/imagen2.jpg" alt="Personas en videollamada">
      </div>
    </section>

    <section class="panel panel--join">
      <div class="panel__media">
        <img src="img/imagen3.jpg" alt="Tablet con anotaciones" class="rounded">
      </div>
      <div class="panel__text">
        <h2>¿QUIERES UNIRTE A UNA<br/><span>REUNIÓN?</span></h2>
        <label class="field">
          <span class="field__label">Introduce código de reunión</span>
          <input id="codigo" class="input" type="text" placeholder="ATP-456" maxlength="10">
        </label>
        <button class="cta cta--ghost" id="btnEntrar"><span class="icon">✪</span> Entrar a reunión</button>
        <p class="helper" id="joinMsg"></p>
      </div>
    </section>
  </main>

  <footer class="footer"></footer>

  <script>
  document.addEventListener('DOMContentLoaded', () => {
    /* === Crear sala === */
    const btnCrear = document.getElementById('btnCrear');
    btnCrear?.addEventListener('click', async () => {
      try {
        const res = await fetch('crear_sala.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ nombre: 'Reunión rápida' })
        });

        if (!res.ok) {
          const txt = await res.text();
          alert(`Fallo crear sala (HTTP ${res.status}).\n\n${txt}`);
          return;
        }

        const data = await res.json();
        if (data.ok) {
          window.location.href = 'room.php?code=' + encodeURIComponent(data.code);
        } else {
          alert('No se pudo crear la sala: ' + (data.msg || 'Error'));
        }
      } catch (e) {
        console.error(e);
        alert('Error de red al crear la sala.');
      }
    });

    /* === Unirse por código (robusto a respuestas no-JSON) === */
    const $cod = document.getElementById('codigo');
    const $btn = document.getElementById('btnEntrar');
    const $msg = document.getElementById('joinMsg');

    function normCode(s){
      return (s || '').trim().toUpperCase().replace(/\s+/g,'').slice(0, 10);
    }

    $btn?.addEventListener('click', async () => {
      if ($msg) $msg.textContent = '';
      const code = normCode($cod?.value);

      if (!code) {
        if ($msg) $msg.textContent = 'Escribe un código de reunión.';
        return;
      }

      try {
        const res  = await fetch('verificar_sala.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ code })
        });

        const text = await res.text();
        let data = null;
        try { data = JSON.parse(text); } catch {
          console.warn('Respuesta no-JSON de verificar_sala.php:\n', text);
          if ($msg) $msg.textContent = `Respuesta inválida del servidor (HTTP ${res.status}).`;
          return;
        }

        if (!res.ok || !data.ok) {
          if ($msg) {
            if (res.status === 404)      $msg.textContent = 'No existe una reunión con ese código.';
            else if (res.status === 410) $msg.textContent = 'La reunión está cerrada/inactiva.';
            else                         $msg.textContent = data.msg || `Error (HTTP ${res.status}).`;
          }
          return;
        }

        window.location.href = 'room.php?code=' + encodeURIComponent(code);
      } catch (e) {
        console.error(e);
        if ($msg) $msg.textContent = 'Error de red al validar la reunión.';
      }
    });

    $cod?.addEventListener('keydown', e => {
      if (e.key === 'Enter') { e.preventDefault(); $btn?.click(); }
    });
  });
  </script>
</body>
</html>