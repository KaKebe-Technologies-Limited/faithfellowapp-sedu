// The small web page the app shows a YouTube video in. YouTube refuses a player that cannot say which site is showing it ("Video player configuration
// error", error 153), so the page carries the site's address as its base (set by the caller) and puts the player in an iframe. The player's own
// messages are passed on to the app: "ready" when it has loaded, "error:<code>" when it cannot play the video (embedding switched off, video removed).
//
// `clean` is the look for a live service: no control bar (so no YouTube logo while it plays), no keyboard, no annotations, and the player cannot be paused
// by a touch. A small sound button of ours sits in the corner instead. YouTube's own logo and title still appear when the player is paused or ends,
// which is YouTube's part of the player and is left as it is.
export const EMBED_ORIGIN = 'https://faithfellows.com';

export function youtubePage(url, { clean = false, origin = EMBED_ORIGIN } = {}) {
  const params = ['enablejsapi=1', 'playsinline=1', 'rel=0', `origin=${encodeURIComponent(origin)}`];
  if (clean) params.push('controls=0', 'disablekb=1', 'fs=0', 'iv_load_policy=3', 'autoplay=1');
  const src = `${url}${url.includes('?') ? '&' : '?'}${params.join('&')}`.replace(/&/g, '&amp;');

  return `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<style>
html,body{margin:0;height:100%;background:#000;overflow:hidden}
iframe{position:absolute;top:0;left:0;width:100%;height:100%;border:0;${clean ? 'pointer-events:none;' : ''}}
#sound{position:absolute;top:10px;right:10px;width:40px;height:40px;border-radius:20px;border:0;background:rgba(0,0,0,0.5);color:#fff;font-size:18px;line-height:40px;padding:0;display:${clean ? 'block' : 'none'}}
</style></head><body>
<iframe id="p" src="${src}" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
<button id="sound" type="button" aria-label="Sound">&#128266;</button>
<script>
(function () {
  var frame = document.getElementById('p');
  var sound = document.getElementById('sound');
  var muted = false;
  var tell = function (text) { if (window.ReactNativeWebView) window.ReactNativeWebView.postMessage(text); };
  var command = function (func) { try { frame.contentWindow.postMessage(JSON.stringify({ event: 'command', func: func, args: [] }), '*'); } catch (e) {} };
  frame.addEventListener('load', function () {
    try { frame.contentWindow.postMessage(JSON.stringify({ event: 'listening', id: 1, channel: 'widget' }), '*'); } catch (e) {}
  });
  sound.addEventListener('click', function () {
    muted = !muted;
    command(muted ? 'mute' : 'unMute');
    sound.innerHTML = muted ? '&#128263;' : '&#128266;';
  });
  window.addEventListener('message', function (e) {
    try {
      var d = typeof e.data === 'string' ? JSON.parse(e.data) : e.data;
      if (d && d.event === 'onError') tell('error:' + d.info);
      else if (d && (d.event === 'onReady' || d.event === 'initialDelivery' || d.event === 'infoDelivery' || d.event === 'onStateChange')) tell('ready');
    } catch (err) {}
  });
})();
</script></body></html>`;
}
