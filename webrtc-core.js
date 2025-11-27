// js/webrtc-core.js  (ESM)
const WEBRTC = {
  cfg: null,
  ws: null,
  state: {
    localStream: null,
    screenStream: null,
    peers: new Map(), // peerId -> { pc, stream }
  },
  on: {
    ws: (st)=>{}, joined: ()=>{},
    remoteStream: (peerId, stream)=>{},
    peerLeft: (peerId)=>{},
    custom: (msg)=>{}, // <- NUEVO: chat, hand, etc.
  }
};
window.WEBRTC = WEBRTC;

function log(...a){ console.log('[WEBRTC]', ...a); }

WEBRTC.connect = async function(){
  WEBRTC.cfg = window.WEBRTC_CONFIG;
  const { ROOM_ID, WS_URL, ICE_SERVERS } = WEBRTC.cfg;

  // get local A/V
  try {
    WEBRTC.state.localStream = await navigator.mediaDevices.getUserMedia({audio:true,video:true});
  } catch(e){ console.warn('getUserMedia', e); WEBRTC.state.localStream = new MediaStream(); }

  // WS
  const ws = new WebSocket(WS_URL);
  WEBRTC.ws = ws;

  ws.onopen = () => {
    WEBRTC.on.ws('Conectado');
    ws.send(JSON.stringify({ type:'join', roomId: ROOM_ID }));
  };

  ws.onmessage = async ev => {
    const msg = JSON.parse(ev.data);

    if (msg.type === 'peers') {
      for (const peerId of msg.peers || []) {
        await callPeer(peerId);
      }
      WEBRTC.on.joined();
      return;
    }

    if (msg.type === 'peer-joined') {
      await callPeer(msg.peerId);
      return;
    }

    if (msg.type === 'offer') {
      const pc = newPeerConnection(msg.from);
      WEBRTC.state.peers.set(msg.from, { pc, stream:new MediaStream() });
      await pc.setRemoteDescription(msg.sdp);
      const ans = await pc.createAnswer();
      await pc.setLocalDescription(ans);
      ws.send(JSON.stringify({ type:'answer', to:msg.from, sdp:pc.localDescription }));
      return;
    }

    if (msg.type === 'answer') {
      const info = WEBRTC.state.peers.get(msg.from);
      await info?.pc?.setRemoteDescription(msg.sdp);
      return;
    }

    if (msg.type === 'ice') {
      const info = WEBRTC.state.peers.get(msg.from);
      info && msg.candidate && info.pc.addIceCandidate(msg.candidate);
      return;
    }

    if (msg.type === 'peer-left') {
      const info = WEBRTC.state.peers.get(msg.peerId);
      if (info) { try{info.pc.close();}catch{} WEBRTC.state.peers.delete(msg.peerId); }
      WEBRTC.on.peerLeft(msg.peerId);
      return;
    }

    // <- Mensajes personalizados (chat, hand, etc.)
    WEBRTC.on.custom(msg);
  };

  ws.onclose = ()=> WEBRTC.on.ws('Desconectado');

  // helpers
  function newPeerConnection(peerId){
    const pc = new RTCPeerConnection({ iceServers: WEBRTC.cfg.ICE_SERVERS || ICE_SERVERS || [] });
    WEBRTC.state.localStream.getTracks().forEach(t => pc.addTrack(t, WEBRTC.state.localStream));

    pc.onicecandidate = ({candidate}) => { if (candidate) ws.send(JSON.stringify({ type:'ice', to:peerId, candidate })); };
    pc.ontrack = e => {
      let info = WEBRTC.state.peers.get(peerId);
      if (!info) { info = { pc, stream:new MediaStream() }; WEBRTC.state.peers.set(peerId, info); }
      e.streams[0].getTracks().forEach(t => info.stream.addTrack(t));
      WEBRTC.on.remoteStream(peerId, info.stream);
    };
    return pc;
  }

  async function callPeer(peerId){
    const pc = newPeerConnection(peerId);
    WEBRTC.state.peers.set(peerId, { pc, stream:new MediaStream() });
    const off = await pc.createOffer();
    await pc.setLocalDescription(off);
    ws.send(JSON.stringify({ type:'offer', to:peerId, sdp:pc.localDescription }));
  }
};

WEBRTC.listDevices = async function(){
  const all = await navigator.mediaDevices.enumerateDevices();
  return {
    mics:  all.filter(d => d.kind === 'audioinput'),
    cams:  all.filter(d => d.kind === 'videoinput'),
    outs:  all.filter(d => d.kind === 'audiooutput'),
  };
};

WEBRTC.setMic = async function(deviceId){
  const v = WEBRTC.state.localStream.getVideoTracks()[0];
  WEBRTC.state.localStream.getTracks().forEach(t => t.stop());
  WEBRTC.state.localStream = await navigator.mediaDevices.getUserMedia({
    audio: deviceId ? {deviceId:{exact:deviceId}} : true,
    video: v ? true : false
  });
  renegotiateAll();
};
WEBRTC.setCam = async function(deviceId){
  const a = WEBRTC.state.localStream.getAudioTracks()[0];
  WEBRTC.state.localStream.getTracks().forEach(t => t.stop());
  WEBRTC.state.localStream = await navigator.mediaDevices.getUserMedia({
    audio: a ? true : false,
    video: deviceId ? {deviceId:{exact:deviceId}} : true
  });
  renegotiateAll();
};
async function renegotiateAll(){
  WEBRTC.state.peers.forEach(async ({pc})=>{
    pc.getSenders().forEach(s => { if (s.track) pc.removeTrack(s); });
    WEBRTC.state.localStream.getTracks().forEach(t => pc.addTrack(t, WEBRTC.state.localStream));
    const off = await pc.createOffer(); await pc.setLocalDescription(off);
    WEBRTC.ws?.send(JSON.stringify({ type:'offer', to:[...WEBRTC.state.peers.keys()][0], sdp:pc.localDescription }));
  });
}

WEBRTC.startScreenShare = async function(){
  const disp = await navigator.mediaDevices.getDisplayMedia({ video:true, audio:false });
  const track = disp.getVideoTracks()[0];
  WEBRTC.state.screenStream = disp;

  WEBRTC.state.peers.forEach(({pc})=>{
    const sender = pc.getSenders().find(s => s.track && s.track.kind === 'video');
    if (sender) sender.replaceTrack(track);
  });
  track.onended = async ()=>{
    const cam = await navigator.mediaDevices.getUserMedia({video:true,audio:false});
    const camTrack = cam.getVideoTracks()[0];
    WEBRTC.state.peers.forEach(({pc})=>{
      const sender = pc.getSenders().find(s => s.track && s.track.kind === 'video');
      if (sender) sender.replaceTrack(camTrack);
    });
  };
};
WEBRTC.stopScreenShare = async function(){
  try { WEBRTC.state.screenStream?.getTracks().forEach(t=>t.stop()); } catch {}
};

// NUEVO: enviar mensajes custom (chat/hand)
WEBRTC.sendCustom = function(type, payload){
  WEBRTC.ws?.send(JSON.stringify({
    type, roomId: WEBRTC.cfg.ROOM_ID, userId: WEBRTC.cfg.USER_ID, ...payload
  }));
};

WEBRTC.leave = function(){
  try { WEBRTC.ws?.close(); } catch {}
  try { WEBRTC.state.localStream?.getTracks().forEach(t=>t.stop()); } catch {}
  WEBRTC.state.peers.forEach(({pc})=>{ try{pc.close();}catch{} });
  WEBRTC.state.peers.clear();
};

log('core listo. Sala:', window.WEBRTC_CONFIG?.ROOM_ID, 'ws:', window.WEBRTC_CONFIG?.WS_URL);
