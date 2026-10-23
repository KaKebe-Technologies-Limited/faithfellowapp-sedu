// The small web page the app shows a YouTube video in. YouTube refuses a player that cannot say which site is showing it ("Video player configuration
// error", error 153), so the page carries the site's address as its base (set by the caller) and puts the player in an iframe. The player's own
// messages are passed on to the app: "ready" when it has loaded, "error:<code>" when it cannot play the video (embedding switched off, video removed).
//
// `clean` is the look for a live service: no control bar (so no YouTube logo while it plays), no keyboard, no annotations, and the player cannot be paused
// by a touch. A small sound button of ours sits in the corner instead. YouTube's own logo and title still appear when the player is paused or ends,
// which is YouTube's part of the player and is left as it is.
//
// `bar` (a church's name) is the look for a sermon video: YouTube's control bar is switched off with the player's own setting and a bar of ours sits BELOW the
// picture instead, never over it (play / pause, a seek line, the time, the sound and the church's name). Nothing of YouTube's is covered or blocked: its title
// and logo still show when the video is paused or has ended, and a touch on the picture reaches the player.
export const EMBED_ORIGIN = 'https://faithfellows.com';

/** The height of the bar under a sermon video, in pixels. */
export const BAR_HEIGHT = 60;

const escapeHtml = (text) => String(text).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

export function youtubePage(url, { clean = false, origin = EMBED_ORIGIN, bar, autoPlay } = {}) {
  const hasBar = typeof bar === 'string';
  const chrome = clean || hasBar; // no YouTube control bar
  const autoplay = autoPlay ?? (clean && !hasBar);
  const params = ['enablejsapi=1', 'playsinline=1', 'rel=0', `origin=${encodeURIComponent(origin)}`];
  if (chrome) params.push('controls=0', 'disablekb=1', 'fs=0', 'iv_load_policy=3');
  if (autoplay) params.push('autoplay=1');
  const src = `${url}${url.includes('?') ? '&' : '?'}${params.join('&')}`.replace(/&/g, '&amp;');

  return `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<style>
html,body{margin:0;height:100%;background:#000;overflow:hidden;font-family:-apple-system,Roboto,'Segoe UI',Helvetica,Arial,sans-serif;-webkit-tap-highlight-color:transparent}
#stage{position:absolute;left:0;right:0;top:0;bottom:${hasBar ? BAR_HEIGHT : 0}px}
iframe{position:absolute;top:0;left:0;width:100%;height:100%;border:0;${clean && !hasBar ? 'pointer-events:none;' : ''}}
#sound{position:absolute;top:10px;right:10px;width:40px;height:40px;border-radius:20px;border:0;background:rgba(0,0,0,0.5);color:#fff;font-size:18px;line-height:40px;padding:0;display:${clean && !hasBar ? 'block' : 'none'}}
#bar{position:absolute;left:0;right:0;bottom:0;height:${BAR_HEIGHT}px;background:linear-gradient(#ffffff,#f4ecf0);color:#2b1b22;display:${hasBar ? 'block' : 'none'};user-select:none;-webkit-user-select:none}
#seek{height:20px;padding:0 14px;touch-action:none;display:flex;align-items:center}
#track{position:relative;flex:1;height:4px;border-radius:2px;background:#e3d6dc}
#fill{position:absolute;left:0;top:0;bottom:0;width:0;border-radius:2px;background:#bc2649}
#knob{position:absolute;top:50%;left:0;width:14px;height:14px;margin:-7px 0 0 -7px;border-radius:7px;background:#bc2649;box-shadow:0 1px 4px rgba(0,0,0,0.3)}
#row{height:${BAR_HEIGHT - 20}px;display:flex;align-items:center;gap:8px;padding:0 8px 0 10px}
#bar button{width:38px;height:38px;border:0;border-radius:19px;padding:0;display:flex;align-items:center;justify-content:center;flex:none;background:transparent;color:#bc2649}
#bar button#toggle{background:#bc2649;color:#fff}
#bar svg{width:20px;height:20px;fill:currentColor}
#time{font-size:12.5px;font-weight:600;color:#6b5a62;white-space:nowrap;flex:none}
#name{flex:1;min-width:0;text-align:right;font-size:13px;font-weight:700;color:#8e1b3a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
</style></head><body>
<div id="stage"><iframe id="p" src="${src}" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>
<button id="sound" type="button" aria-label="Sound">&#128266;</button>
<div id="bar">
  <div id="seek"><div id="track"><div id="fill"></div><div id="knob"></div></div></div>
  <div id="row">
    <button id="toggle" type="button" aria-label="Play or pause"><svg viewBox="0 0 24 24" id="icon"><path d="M8 5v14l11-7z"/></svg></button>
    <span id="time">0:00 / 0:00</span>
    <span id="name">${escapeHtml(bar ?? '')}</span>
    <button id="mute" type="button" aria-label="Sound"><svg viewBox="0 0 24 24" id="speaker"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3A4.5 4.5 0 0 0 14 8v8a4.5 4.5 0 0 0 2.5-4zM14 3.2v2.1a7 7 0 0 1 0 13.4v2.1a9 9 0 0 0 0-17.6z"/></svg></button>
  </div>
</div>
<script>
(function () {
  var frame = document.getElementById('p');
  var sound = document.getElementById('sound');
  var muted = false;
  var playing = false;
  var duration = 0;
  var current = 0;
  var dragging = false;
  var PLAY = 'M8 5v14l11-7z';
  var PAUSE = 'M6 5h4v14H6zm8 0h4v14h-4z';
  var SPEAKER = 'M3 9v6h4l5 5V4L7 9H3zm13.5 3A4.5 4.5 0 0 0 14 8v8a4.5 4.5 0 0 0 2.5-4zM14 3.2v2.1a7 7 0 0 1 0 13.4v2.1a9 9 0 0 0 0-17.6z';
  var SILENT = 'M3 9v6h4l5 5V4L7 9H3zm13.6 3L19 9.6 17.6 8.2 15.2 10.6 12.8 8.2 11.4 9.6 13.8 12l-2.4 2.4 1.4 1.4 2.4-2.4 2.4 2.4 1.4-1.4z';
  var tell = function (text) { if (window.ReactNativeWebView) window.ReactNativeWebView.postMessage(text); };
  var command = function (func, args) { try { frame.contentWindow.postMessage(JSON.stringify({ event: 'command', func: func, args: args || [], id: 1, channel: 'widget' }), '*'); } catch (e) {} };
  var heard = false;
  var listen = function () { try { frame.contentWindow.postMessage(JSON.stringify({ event: 'listening', id: 1, channel: 'widget' }), '*'); } catch (e) {} };
  var $ = function (id) { return document.getElementById(id); };
  var clock = function (s) { s = Math.max(0, Math.floor(s || 0)); var h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), r = s % 60; return (h ? h + ':' + (m < 10 ? '0' : '') : '') + m + ':' + (r < 10 ? '0' : '') + r; };
  var draw = function () {
    var ratio = duration ? Math.min(1, current / duration) : 0;
    $('fill').style.width = ratio * 100 + '%';
    $('knob').style.left = ratio * 100 + '%';
    $('time').textContent = clock(current) + ' / ' + clock(duration);
    $('icon').firstChild.setAttribute('d', playing ? PAUSE : PLAY);
    $('speaker').firstChild.setAttribute('d', muted ? SILENT : SPEAKER);
  };
  // The player only answers once its own scripts have loaded, which is after the page has: ask again every half second until it speaks.
  var tries = 0;
  var asking = setInterval(function () { if (heard || ++tries > 60) clearInterval(asking); else listen(); }, 500);
  frame.addEventListener('load', listen);
  sound.addEventListener('click', function () {
    muted = !muted;
    command(muted ? 'mute' : 'unMute');
    sound.innerHTML = muted ? '&#128263;' : '&#128266;';
  });
  $('toggle').addEventListener('click', function () { command(playing ? 'pauseVideo' : 'playVideo'); playing = !playing; draw(); });
  $('mute').addEventListener('click', function () { muted = !muted; command(muted ? 'mute' : 'unMute'); draw(); });

  // The seek line: touch or drag anywhere on it; the video jumps when the finger is lifted.
  var seek = $('seek');
  var at = function (e) { var box = $('track').getBoundingClientRect(); return Math.max(0, Math.min(1, (e.clientX - box.left) / box.width)); };
  seek.addEventListener('pointerdown', function (e) { if (!duration) return; dragging = true; try { seek.setPointerCapture(e.pointerId); } catch (x) {} current = at(e) * duration; draw(); });
  seek.addEventListener('pointermove', function (e) { if (dragging) { current = at(e) * duration; draw(); } });
  var release = function (e) { if (!dragging) return; dragging = false; current = at(e) * duration; command('seekTo', [current, true]); draw(); };
  seek.addEventListener('pointerup', release);
  seek.addEventListener('pointercancel', function () { dragging = false; });

  window.addEventListener('message', function (e) {
    try {
      var d = typeof e.data === 'string' ? JSON.parse(e.data) : e.data;
      if (!d) return;
      if (d.channel === 'widget' || d.event) heard = true;
      if (d.event === 'onError') tell('error:' + d.info);
      else if (d.event === 'onReady' || d.event === 'initialDelivery' || d.event === 'infoDelivery' || d.event === 'onStateChange') tell('ready');
      if (d.event === 'onStateChange' && typeof d.info === 'number') playing = d.info === 1 || d.info === 3;
      var info = d.info && typeof d.info === 'object' ? d.info : null;
      if (info) {
        if (typeof info.duration === 'number' && info.duration > 0) duration = info.duration;
        if (typeof info.currentTime === 'number' && !dragging) current = info.currentTime;
        if (typeof info.playerState === 'number') playing = info.playerState === 1 || info.playerState === 3;
        if (typeof info.muted === 'boolean') muted = info.muted;
      }
      draw();
    } catch (err) {}
  });
  draw();
})();
</script></body></html>`;
}
