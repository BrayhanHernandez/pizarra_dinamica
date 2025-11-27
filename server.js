// server.js — señalización mínima (Node 18+)
const { WebSocketServer } = require('ws');

const wss = new WebSocketServer({ port: 8081 });
const rooms = new Map(); // roomId -> Set<WebSocket>

function send(ws, obj) {
  try { ws.send(JSON.stringify(obj)); } catch {}
}

function broadcast(roomId, obj, except) {
  const set = rooms.get(roomId);
  if (!set) return;
  for (const cli of set) {
    if (cli !== except && cli.readyState === 1) send(cli, obj);
  }
}

wss.on('listening', () => {
  console.log('WS signaling on ws://localhost:8081');
});

wss.on('connection', (ws) => {
  console.log('Cliente conectado');
  ws.roomId = null;
  ws.id = null;

  ws.on('message', (raw) => {
    let msg; try { msg = JSON.parse(raw); } catch { return; }
    const { type } = msg;

    // --- join a la sala ---
    if (type === 'join') {
      ws.roomId = msg.roomId;
      if (!rooms.has(ws.roomId)) rooms.set(ws.roomId, new Set());
      const set = rooms.get(ws.roomId);

      // asignar ID del peer ANTES de anunciarlo
      ws.id = Math.random().toString(36).slice(2, 8);

      // enviar lista de peers existentes al que entra
      send(ws, { type: 'peers', peers: [...set].map(x => x.id).filter(Boolean) });

      // añadirlo y anunciar a los demás
      set.add(ws);
      broadcast(ws.roomId, { type: 'peer-joined', peerId: ws.id }, ws);
      return;
    }

    // --- WebRTC signaling ---
    if (type === 'offer' || type === 'answer' || type === 'ice') {
      const set = rooms.get(msg.roomId || ws.roomId);
      if (!set) return;
      const to = [...set].find(x => x.id && x.id === msg.to);
      if (to) send(to, { type, from: ws.id, sdp: msg.sdp, candidate: msg.candidate });
      return;
    }

    // --- Mensajería "custom" (chat, mano, mute, pizarra, etc.) ---
    // Nuestros clientes usan WEBRTC.sendCustom('algo', payload)
    // que envía { type:'custom', data:{ type:'algo', ... } }
    if (type === 'custom') {
      const data = msg.data || msg.payload || {}; // tolerante
      // Reenviamos conservando la forma esperada por el cliente:
      // { type:'custom', from, data:{ type:'chat' | 'hand' | 'mute-ind' | 'board:*', ... } }
      broadcast(msg.roomId || ws.roomId, { type: 'custom', from: ws.id, data }, ws);
      return;
    }

    // (Opcional) compatibilidad con mensajes directos no "custom"
    if (type === 'chat' || type === 'hand' || type === 'mute-ind') {
      broadcast(msg.roomId || ws.roomId, { type, from: ws.id, ...msg }, ws);
      return;
    }
  });

  ws.on('close', () => {
    console.log('Cliente salió');
    const set = rooms.get(ws.roomId);
    if (set) {
      set.delete(ws);
      broadcast(ws.roomId, { type: 'peer-left', peerId: ws.id }, ws);
      if (set.size === 0) rooms.delete(ws.roomId);
    }
  });
});

wss.on('error', (err) => console.error('WS error:', err));

