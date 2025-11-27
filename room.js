(() => {
  const $ = s => document.querySelector(s);

  // DOM
  const rail = $('#railTiles');
  const vLocal = $('#vLocal');
  const selMic = $('#selMic');
  const selCam = $('#selCam');
  const statusEl = $('#webrtcStatus');
  const btnMic = $('#btnMic');
  const btnCam = $('#btnCam');
  const btnHand = $('#btnHand');
  const btnShare = $('#btnShare');
  const btnRecord = $('#btnRecord');
  const btnLeave = $('#btnLeave');
  const btnLeaveTop = $('#btnLeaveTop');
  const btnChatToggle = $('#btnChatToggle');
  const chatStream = $('#chatStream');
  const chatForm = $('#chatForm');
  const chatInput = $('#chatInput');

  // ===== Mapa nombre-visible por userId (para chat/cintas) =====
  const displayNameOf = (userId) => {
    if (!userId) return 'usuario';
    if (userId === WEBRTC?.cfg?.USER_ID) {
      // nombre que se ve en tu propia miniatura
      const n = rail.querySelector('.thumb[data-peer="local"] .name')?.textContent?.trim();
      return n || userId;
    }
    const card = rail.querySelector(`.thumb[data-peer="${userId}"]`);
    const n = card?.querySelector('.name')?.textContent?.trim();
    return n || userId;
  };

  // ====== PIZARRA (colaborativa) ======
  const canvas = $('#boardCanvas');
  const ctx = canvas.getContext('2d');
  let tool = 'pen';                // pen | rect | text | select
  let drawing = false, startX=0, startY=0, lastX=0, lastY=0;
  let sendTicker = 0;              // throttling de draw

  function resizeCanvas(){
    const r = canvas.parentElement.getBoundingClientRect();
    // evitar re-acumular escalado
    const ratio = window.devicePixelRatio || 1;
    canvas.width = Math.floor(r.width * ratio);
    canvas.height = Math.floor(r.height * ratio);
    ctx.setTransform(1,0,0,1,0,0);
    ctx.scale(ratio, ratio);
  }
  window.addEventListener('resize', resizeCanvas);
  resizeCanvas();

  const BOARD_STYLE = {
    stroke: '#1f2937',
    rect:   '#334155',
    width:  2,
    font:   '16px system-ui',
    text:   '#111'
  };

  function beginStroke(x,y){
    ctx.strokeStyle = BOARD_STYLE.stroke;
    ctx.lineWidth = BOARD_STYLE.width;
    ctx.lineCap = 'round';
    ctx.beginPath();
    ctx.moveTo(x,y);
  }
  function drawStroke(x,y){
    ctx.lineTo(x,y);
    ctx.stroke();
  }
  function drawRect(ax,ay,bx,by){
    ctx.strokeStyle = BOARD_STYLE.rect;
    ctx.lineWidth = BOARD_STYLE.width;
    const x = Math.min(ax,bx), y = Math.min(ay,by);
    const w = Math.abs(bx-ax), h = Math.abs(by-ay);
    ctx.strokeRect(x,y,w,h);
  }
  function drawText(x,y,t){
    ctx.fillStyle = BOARD_STYLE.text;
    ctx.font = BOARD_STYLE.font;
    ctx.fillText(t,x,y);
  }

  // coords relativas al canvas
  const toCanvas = (e)=>{
    const b = canvas.getBoundingClientRect();
    return { x: e.clientX - b.left, y: e.clientY - b.top };
  };

  // ==== eventos locales + broadcast ====
  $('#tPen')?.addEventListener('click', ()=> tool='pen');
  $('#tRect')?.addEventListener('click', ()=> tool='rect');
  $('#tText')?.addEventListener('click', ()=> tool='text');
  $('#tSelect')?.addEventListener('click', ()=> tool='select');
  $('#tClear')?.addEventListener('click', ()=>{
    ctx.clearRect(0,0,canvas.width,canvas.height);
    WEBRTC.sendCustom('board:clear', {});
  });

  canvas.addEventListener('mousedown', e=>{
    const {x,y} = toCanvas(e);
    startX = lastX = x; startY = lastY = y; drawing = true;

    if (tool==='pen'){
      beginStroke(x,y);
      WEBRTC.sendCustom('board:begin', { tool, x, y });
    } else if (tool==='text'){
      const t = prompt('Texto:');
      if (t && t.trim()){
        drawText(x,y,t.trim());
        WEBRTC.sendCustom('board:text', { x, y, t: t.trim() });
      }
      drawing = false;
    } else if (tool==='rect'){
      // marcador de inicio (para clientes que quieran previsualizar)
      WEBRTC.sendCustom('board:begin', { tool, x, y });
    }
  });

  canvas.addEventListener('mousemove', e=>{
    if (!drawing) return;
    const {x,y} = toCanvas(e);

    if (tool==='pen'){
      drawStroke(x,y);
      lastX=x; lastY=y;

      // throttle ~30 Hz
      const now = performance.now();
      if (now - sendTicker > 33){
        sendTicker = now;
        WEBRTC.sendCustom('board:draw', { tool, x, y });
      }
    }
  });

  canvas.addEventListener('mouseup', e=>{
    if (!drawing) return; drawing=false;
    const {x,y} = toCanvas(e);

    if (tool==='rect'){
      // limpiar el rectángulo “preview”: redibujar sería más complejo.
      // Para simpleza dibujamos definitivo:
      drawRect(startX,startY,x,y);
      WEBRTC.sendCustom('board:rect', { ax:startX, ay:startY, bx:x, by:y });
    } else if (tool==='pen'){
      WEBRTC.sendCustom('board:end', { });
    }
  });

  // ====== MINIATURAS ======
  function ensureThumb(peerId, label){
    let card = rail.querySelector(`.thumb[data-peer="${peerId}"]`);
    if (!card){
      card = document.createElement('article');
      card.className = 'thumb'; card.dataset.peer = peerId;
      card.innerHTML = `
        <div class="video-wrap">
          <video id="v-${peerId}" autoplay playsinline></video>
          <div class="badges">
            <span class="badge badge--mic" hidden>🔇</span>
            <span class="badge badge--hand" hidden>✋</span>
          </div>
        </div>
        <div class="meta">
          <div class="name">${label||('Invitado '+peerId)}</div>
          <div class="nick">APODO: —</div>
        </div>`;
      rail.appendChild(card);
    }
    return card;
  }
  function setMicBadge(peerId, muted){
    const card = rail.querySelector(`.thumb[data-peer="${peerId}"]`);
    card?.querySelector('.badge--mic')?.toggleAttribute('hidden', !muted);
  }
  function setHandBadge(peerId, raised){
    const card = rail.querySelector(`.thumb[data-peer="${peerId}"]`);
    card?.querySelector('.badge--hand')?.toggleAttribute('hidden', !raised);
  }

  // ====== CORE ======
  WEBRTC.on.ws = (state)=> { statusEl && (statusEl.textContent = state); };

  WEBRTC.on.joined = async () => {
    if (WEBRTC.state.localStream && vLocal) vLocal.srcObject = WEBRTC.state.localStream;
    const { mics, cams } = await WEBRTC.listDevices();
    selMic.innerHTML = mics.map(d=>`<option value="${d.deviceId}">${d.label||'Mic'}</option>`).join('');
    selCam.innerHTML = cams.map(d=>`<option value="${d.deviceId}">${d.label||'Cam'}</option>`).join('');
  };

  WEBRTC.on.remoteStream = (peerId, stream) => {
    const card = ensureThumb(peerId);
    const v = card.querySelector('video');
    if (v.srcObject !== stream) v.srcObject = stream;
  };

  WEBRTC.on.peerLeft = (peerId) => {
    rail.querySelector(`.thumb[data-peer="${peerId}"]`)?.remove();
  };

  // Mensajes custom: chat, mano, mute-ind y pizarra
  WEBRTC.on.custom = (msg) => {
    // CHAT
    if (msg.type === 'chat'){
      pushChat(displayNameOf(msg.from), msg.text||'', msg.ts);
    }

    // Estado UI
    if (msg.type === 'hand'){ setHandBadge(msg.from, !!msg.raised); }
    if (msg.type === 'mute-ind'){ setMicBadge(msg.from, !!msg.muted); }

    // PIZARRA remota
    if (msg.type === 'board:clear'){ ctx.clearRect(0,0,canvas.width,canvas.height); }
    if (msg.type === 'board:begin'){
      if (msg.tool==='pen'){ beginStroke(msg.x, msg.y); }
      // en rect usaremos el evento final board:rect
    }
    if (msg.type === 'board:draw'){
      if (msg.tool==='pen'){ drawStroke(msg.x, msg.y); }
    }
    if (msg.type === 'board:end'){
      // nada especial en esta versión
    }
    if (msg.type === 'board:rect'){
      drawRect(msg.ax,msg.ay,msg.bx,msg.by);
    }
    if (msg.type === 'board:text'){
      drawText(msg.x,msg.y,msg.t||'');
    }
  };

  WEBRTC.connect();

  // ====== CONTROLES ======
  selMic?.addEventListener('change', e => WEBRTC.setMic(e.target.value));
  selCam?.addEventListener('change', e => WEBRTC.setCam(e.target.value));

  btnMic?.addEventListener('click', () => {
    const at = WEBRTC.state.localStream?.getAudioTracks?.()[0];
    if (!at) return;
    at.enabled = !at.enabled;
    btnMic.dataset.on = at.enabled ? '1' : '0';
    setMicBadge('local', !at.enabled);
    WEBRTC.sendCustom('mute-ind', { muted: !at.enabled });
  });

  btnCam?.addEventListener('click', () => {
    const vt = WEBRTC.state.localStream?.getVideoTracks?.()[0];
    if (!vt) return;
    vt.enabled = !vt.enabled;
    btnCam.dataset.on = vt.enabled ? '1' : '0';
  });

  let handRaised = false;
  btnHand?.addEventListener('click', () => {
    handRaised = !handRaised;
    setHandBadge('local', handRaised);
    WEBRTC.sendCustom('hand', { raised: handRaised });
  });

  btnShare?.addEventListener('click', async () => {
    if (!WEBRTC.state.screenStream) await WEBRTC.startScreenShare();
    else await WEBRTC.stopScreenShare();
  });

  function leaveAll(){
    try{ WEBRTC.leave(); }catch{}
    window.location.href = 'Home.php';
  }
  btnLeave?.addEventListener('click', leaveAll);
  btnLeaveTop?.addEventListener('click', (e)=>{ e.preventDefault(); leaveAll(); });

  btnChatToggle?.addEventListener('click', () => {
    chatStream?.scrollTo({top:chatStream.scrollHeight, behavior:'smooth'});
  });

  // ====== CHAT ======
  function pushChat(from, text, ts){
    if (!text) return;
    const wrap = document.createElement('div');
    const me = (from === displayNameOf(WEBRTC.cfg.USER_ID));
    wrap.className = 'msg' + (me ? ' me' : '');
    const metaTime = new Date(ts || Date.now()).toLocaleTimeString();
    wrap.innerHTML = `<div class="bubble">${escapeHtml(text)}</div><div class="meta">${from} · ${metaTime}</div>`;
    chatStream.appendChild(wrap);
    chatStream.scrollTop = chatStream.scrollHeight;
  }
  function escapeHtml(s){ return (s+'').replace(/[&<>"]/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[m])); }

  chatForm?.addEventListener('submit', e=>{
    e.preventDefault();
    const txt = (chatInput.value || '').trim();
    if (!txt) return;
    const myName = displayNameOf(WEBRTC.cfg.USER_ID);
    pushChat(myName, txt, Date.now());
    WEBRTC.sendCustom('chat', { text: txt, ts: Date.now(), userId: WEBRTC.cfg.USER_ID });
    chatInput.value = '';
  });

  // ====== Grabación A/V (mosaico + pizarra en PiP) ======
  let AVREC = { canvas:null, ctx:null, raf:0, ac:null, dest:null, mr:null, chunks:[], stream:null, fps:30 };

  function drawMosaic(){
    if (!AVREC.ctx) return;
    const vids = Array.from(document.querySelectorAll('.thumb video')).filter(v=>v.readyState>=2);
    AVREC.ctx.fillStyle='#000'; AVREC.ctx.fillRect(0,0,AVREC.canvas.width,AVREC.canvas.height);

    // grid básico
    const n = Math.max(1, vids.length);
    const cols = Math.ceil(Math.sqrt(n));
    const rows = Math.ceil(n/cols);
    const cellW = AVREC.canvas.width/cols, cellH = AVREC.canvas.height/rows;

    vids.forEach((vid,i)=>{
      const r = Math.floor(i/cols), c = i%cols;
      const x = c*cellW, y = r*cellH;
      const vw = vid.videoWidth||16, vh = vid.videoHeight||9;
      const s = Math.min(cellW/vw, cellH/vh);
      const dw = vw*s, dh = vh*s;
      const dx = x+(cellW-dw)/2, dy = y+(cellH-dh)/2;
      try{ AVREC.ctx.drawImage(vid, dx, dy, dw, dh); }catch{}

      const name = vid.closest('.thumb')?.querySelector('.name')?.textContent || vid.id;
      AVREC.ctx.fillStyle='rgba(0,0,0,.55)'; AVREC.ctx.fillRect(dx, dy+dh-22, Math.min(160,dw), 22);
      AVREC.ctx.fillStyle='#fff'; AVREC.ctx.font='12px system-ui'; AVREC.ctx.fillText(name, dx+6, dy+dh-8);
    });

    // Pizarra PiP
    const board = document.getElementById('boardCanvas');
    if (board && board.width && board.height){
      const w = AVREC.canvas.width, h = AVREC.canvas.height;
      const pipW = Math.min(480, w*0.35);
      const bh = board.clientHeight || 720, bw = board.clientWidth || 1280;
      const pipH = pipW * (bh/bw);
      const px = w - pipW - 20, py = h - pipH - 20;
      try{ AVREC.ctx.drawImage(board, 0,0, board.width, board.height, px, py, pipW, pipH); }catch{}
    }

    AVREC.raf = requestAnimationFrame(drawMosaic);
  }

  function startRecording(){
    AVREC.canvas = document.createElement('canvas');
    AVREC.canvas.width = 1280; AVREC.canvas.height = 720;
    AVREC.canvas.style.display='none'; document.body.appendChild(AVREC.canvas);
    AVREC.ctx = AVREC.canvas.getContext('2d');

    const vTrack = AVREC.canvas.captureStream(AVREC.fps).getVideoTracks()[0];

    AVREC.ac = new (window.AudioContext||window.webkitAudioContext)();
    AVREC.dest = AVREC.ac.createMediaStreamDestination();

    const tap = ms => { ms?.getAudioTracks?.().forEach(t=>{
      const src = AVREC.ac.createMediaStreamSource(new MediaStream([t]));
      try{ src.connect(AVREC.dest); }catch{}
    });};
    tap(WEBRTC.state.localStream);
    WEBRTC.state.peers.forEach(({stream})=> tap(stream));

    AVREC.stream = new MediaStream([vTrack, ...AVREC.dest.stream.getAudioTracks()]);
    const mime =
      MediaRecorder.isTypeSupported('video/webm;codecs=vp9,opus') ? 'video/webm;codecs=vp9,opus' :
      MediaRecorder.isTypeSupported('video/webm;codecs=vp8,opus') ? 'video/webm;codecs=vp8,opus' :
      'video/webm';

    AVREC.chunks = [];
    AVREC.mr = new MediaRecorder(AVREC.stream, { mimeType:mime, videoBitsPerSecond:4_000_000 });
    AVREC.mr.ondataavailable = e => e.data.size && AVREC.chunks.push(e.data);
    AVREC.mr.onstop = ()=>{
      const blob = new Blob(AVREC.chunks, {type:mime});
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a'); a.href=url; a.download=`grabacion_${WEBRTC.cfg.ROOM_ID}.webm`; a.click();
      URL.revokeObjectURL(url);
      try{ AVREC.ac.close(); }catch{} try{ AVREC.stream.getTracks().forEach(t=>t.stop()); }catch{}
      cancelAnimationFrame(AVREC.raf); AVREC.canvas.remove();
      AVREC = { canvas:null, ctx:null, raf:0, ac:null, dest:null, mr:null, chunks:[], stream:null, fps:30 };
    };
    AVREC.mr.start(500);
    AVREC.raf = requestAnimationFrame(drawMosaic);
  }
  function stopRecording(){ try{ AVREC.mr?.stop(); }catch{} }

  btnRecord?.addEventListener('click', ()=>{
    if (btnRecord.dataset.state === 'off'){
      startRecording();
      btnRecord.dataset.state='on'; btnRecord.style.background='#e01b24'; btnRecord.style.color='#fff';
    } else {
      stopRecording();
      btnRecord.dataset.state='off'; btnRecord.style.background='#fff'; btnRecord.style.color='#e01b24';
    }
  });
})();
