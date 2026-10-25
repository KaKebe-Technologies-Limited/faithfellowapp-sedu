// The little flowers of the Rose look, drawn as pictures (no photos): one bloom, a loose bunch of them to sit behind a heading, and "the garden", a row of
// seven blooms for the last seven days of reading (a bloom opens on each day you read and stays a bud on the others).
import { StyleSheet, View } from 'react-native';
import Svg, { Circle, Ellipse, G } from 'react-native-svg';
import AppText from '../AppText';
import { COLORS, FONTS } from '../../constants/theme';

/** One flower: `petals` round petals around a warm centre. */
export function Bloom({ size = 40, color = '#F59CC0', center = '#FFD36B', petals = 6, opacity = 1 }) {
  const r = size / 2;
  const rx = r * 0.27;
  const ry = r * 0.44;

  return (
    <Svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} opacity={opacity}>
      {Array.from({ length: petals }, (_, i) => (
        <G key={i} transform={`rotate(${(360 / petals) * i} ${r} ${r})`}>
          <Ellipse cx={r} cy={r - ry * 0.78} rx={rx} ry={ry} fill={color} />
        </G>
      ))}
      <Circle cx={r} cy={r} r={r * 0.2} fill={center} />
    </Svg>
  );
}

/** A bud: a day that has not bloomed (yet). */
function Bud({ size }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24">
      <Circle cx="12" cy="12" r="5" fill="none" stroke={COLORS.primaryPale} strokeWidth="2" strokeDasharray="2.5 3" />
    </Svg>
  );
}

/** A loose bunch of blooms in a corner, to sit behind a heading or on a panel. `tone` is 'pink' (on a pale page) or 'light' (on a dark panel). */
export function BloomCluster({ tone = 'pink', style }) {
  const main = tone === 'light' ? 'rgba(255,255,255,0.20)' : COLORS.primaryPale;
  const soft = tone === 'light' ? 'rgba(255,255,255,0.12)' : COLORS.primaryMist;
  const heart = tone === 'light' ? 'rgba(255,255,255,0.35)' : '#FFD9A8';

  return (
    <View pointerEvents="none" style={[styles.cluster, style]}>
      <View style={{ position: 'absolute', right: -26, top: -22 }}>
        <Bloom size={118} color={main} center={heart} petals={7} opacity={tone === 'light' ? 1 : 0.9} />
      </View>
      <View style={{ position: 'absolute', right: 70, top: 44 }}>
        <Bloom size={50} color={soft} center={heart} petals={6} />
      </View>
      <View style={{ position: 'absolute', right: 18, top: 92 }}>
        <Bloom size={34} color={main} center={heart} petals={5} opacity={0.8} />
      </View>
    </View>
  );
}

/** The week as a row of seven blooms. `days` is the `week` of GET /me/stats ({ label, read_minutes, listen_minutes, shares }). */
export function Garden({ days = [] }) {
  return (
    <View style={styles.garden} accessibilityRole="image" accessibilityLabel={`${days.filter((d) => d.read_minutes > 0).length} of the last 7 days read`}>
      {days.map((d, i) => {
        const read = d.read_minutes > 0;
        const other = !read && (d.listen_minutes > 0 || d.shares > 0);

        return (
          <View key={d.day ?? i} style={styles.day}>
            {read ? <Bloom size={34} color={COLORS.primarySoft} center="#FFD36B" petals={6} /> : other ? <Bloom size={30} color={COLORS.primaryPale} center="#FFE9B8" petals={5} /> : <Bud size={34} />}
            <AppText style={[styles.dayLabel, read && { color: COLORS.primary }]}>{String(d.label ?? '').slice(0, 1)}</AppText>
          </View>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  cluster: { position: 'absolute', right: 0, top: 0, width: 220, height: 180 },
  garden: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-end' },
  day: { alignItems: 'center', gap: 2, width: 40 },
  dayLabel: { fontFamily: FONTS.bold, fontSize: 11.5, color: COLORS.gray500 },
});
