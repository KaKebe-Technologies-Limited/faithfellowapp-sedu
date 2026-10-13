// The progress bar of the podcast player: shows how far into the episode you are; touch or drag it to jump.
// Screen readers can step it forward and back 15 seconds.
import { useState } from 'react';
import { StyleSheet, View } from 'react-native';

export default function Scrubber({ position, duration, onSeek, onStep }) {
  const [width, setWidth] = useState(1);
  const [drag, setDrag] = useState(null); // 0..1 while a finger is on the bar
  const ratio = drag ?? (duration > 0 ? Math.min(1, position / duration) : 0);
  const pick = (e) => Math.max(0, Math.min(1, e.nativeEvent.locationX / width));

  return (
    <View
      style={styles.hit}
      onLayout={(e) => setWidth(Math.max(1, e.nativeEvent.layout.width))}
      onStartShouldSetResponder={() => duration > 0}
      onMoveShouldSetResponder={() => duration > 0}
      onResponderGrant={(e) => setDrag(pick(e))}
      onResponderMove={(e) => setDrag(pick(e))}
      onResponderRelease={(e) => {
        const to = pick(e) * duration;
        setDrag(null);
        onSeek(to);
      }}
      onResponderTerminate={() => setDrag(null)}
      accessible
      accessibilityRole="adjustable"
      accessibilityLabel="Episode progress"
      accessibilityActions={[{ name: 'increment' }, { name: 'decrement' }]}
      onAccessibilityAction={(e) => onStep?.(e.nativeEvent.actionName === 'increment' ? 15 : -15)}
    >
      <View style={[styles.rail, styles.none]}>
        <View style={[styles.fill, { width: `${ratio * 100}%` }]} />
      </View>
      <View style={[styles.thumb, styles.none, { left: ratio * width - 8 }]} />
    </View>
  );
}

const styles = StyleSheet.create({
  hit: { height: 34, justifyContent: 'center' },
  rail: { height: 5, borderRadius: 3, backgroundColor: 'rgba(255,255,255,0.18)', overflow: 'hidden' },
  fill: { height: 5, backgroundColor: '#FF7A9A' },
  thumb: { position: 'absolute', top: 9, width: 16, height: 16, borderRadius: 8, backgroundColor: '#fff' },
  none: { pointerEvents: 'none' },
});
