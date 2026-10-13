// Only one thing plays at a time: the gospel radio or a podcast episode. Each player registers how to stop itself here;
// when one starts it calls claimAudio(its name) and the other one stops.
const stoppers = {};

export function registerAudio(name, stop) {
  stoppers[name] = stop;
}

/** Call when `name` starts playing: every other player stops. */
export function claimAudio(name) {
  Object.entries(stoppers).forEach(([other, stop]) => {
    if (other !== name) stop();
  });
}
