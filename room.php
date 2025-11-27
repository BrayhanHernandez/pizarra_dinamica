<?php
session_start();
if (!isset($_SESSION['usuario'])) { header("Location: login.php"); exit(); }

$code = isset($_GET['code']) ? htmlspecialchars($_GET['code']) : 'N/A';
$user = $_SESSION['usuario'];
$nick = isset($_GET['nick']) ? htmlspecialchars($_GET['nick']) : '';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Sala de reunión: <?php echo $code; ?></title>
  <link rel="stylesheet" href="css/styles_room.css"/>
</head>
<body>
  <header class="topbar">
    <div class="left">Sala de reunión: <strong><?php echo $code; ?></strong></div>
    <div class="right">
      <button id="btnChatToggle" class="chip" title="Chat">💬</button>
      <button id="btnLeaveTop" class="btn btn--dark" title="Cerrar sesión">Cerrar sesión</button>
      <div class="avatar small" title="<?php echo htmlspecialchars($user); ?>"></div>
    </div>
  </header>

  <main class="shell">
    <!-- Izquierda: carril de participantes + chat -->
    <aside class="rail">
      <div class="rail-head">
        <div class="ttl">Participantes</div>
      </div>

      <div id="railTiles" class="thumbs">
        <!-- Local -->
        <article class="thumb" data-peer="local">
          <div class="video-wrap">
            <video id="vLocal" autoplay playsinline muted></video>
            <div class="badges">
              <span class="badge badge--mic" hidden>🔇</span>
              <span class="badge badge--hand" hidden>✋</span>
            </div>
          </div>
          <div class="meta">
            <div class="name"><?php echo htmlspecialchars($user); ?></div>
            <div class="nick">APODO: <?php echo ($nick ?: '—'); ?></div>
          </div>
        </article>
        <!-- Remotos se agregan aquí -->
      </div>

      <div class="chat">
        <div class="chat__title">CHAT</div>
        <div id="chatStream" class="chat__stream"></div>
        <form id="chatForm" class="chat__form" autocomplete="off">
          <button type="button" class="chat__menu">≡</button>
          <input id="chatInput" class="chat__input" placeholder="Escribe un mensaje…"/>
          <button class="chat__send" title="Enviar">➤</button>
        </form>
      </div>
    </aside>

    <!-- Centro: pizarra + dock -->
    <section class="stage">
      <div class="board" id="board">
        <canvas id="boardCanvas"></canvas>
        <div class="board__placeholder">Pizarra colaborativa</div>
      </div>

      <div class="dock">
        <button class="tool" id="tSelect" title="Seleccionar">🖱️</button>
        <button class="tool" id="tPen" title="Lápiz">✏️</button>
        <button class="tool" id="tRect" title="Rectángulo">▭</button>
        <button class="tool" id="tText" title="Texto">Tt</button>
        <button class="tool" id="tClear" title="Limpiar pizarra">🗑️</button>
        <div class="sp"></div>
        <button class="round" id="btnMic" data-on="1" title="Micrófono">🎤</button>
        <button class="round" id="btnCam" data-on="1" title="Cámara">📷</button>
        <button class="round" id="btnHand" data-on="0" title="Levantar mano">✋</button>
        <button class="round" id="btnShare" title="Compartir pantalla">🖥️</button>
        <button class="round round--record" id="btnRecord" data-state="off" title="Grabar">⏺</button>
        <button class="round round--danger" id="btnLeave" title="Salir">⛔</button>
        <select id="selMic" class="sel" title="Micrófono"></select>
        <select id="selCam" class="sel" title="Cámara"></select>
        <span id="webrtcStatus" class="status">Conectando…</span>
      </div>
    </section>
  </main>

  <!-- Config del core -->
  <script>
    window.WEBRTC_CONFIG = {
      ROOM_ID: "<?php echo $code; ?>",
      USER_ID: "<?php echo htmlspecialchars($user); ?>",
      WS_URL: "ws://localhost:8081",
      ICE_SERVERS: [{ urls: 'stun:stun.l.google.com:19302' }]
    };
  </script>

  <script type="module" src="js/webrtc-core.js"></script>
  <script defer src="js/room.js"></script>
</body>
</html>
