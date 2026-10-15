// A round progress meter: a grey track, a coloured arc that fills clockwise from the top, and the percent in the middle. Used for the
// Bible, listening and sharing rings on the profile.
import { StyleSheet, View } from 'react-native';
import Svg, { Circle } from 'react-native-svg';
import { COLORS, FONTS } from '../constants/theme';
import AppText from './AppText';

export default function ProgressRing({ percent = 0, color = COLORS.primary, size = 88, stroke = 10, label }) {
  const value = Math.max(0, Math.min(100, percent));
  const radius = (size - stroke) / 2;
  const circumference = 2 * Math.PI * radius;

  return (
    <View style={{ width: size, height: size }} accessibilityRole="progressbar" accessibilityLabel={label} accessibilityValue={{ min: 0, max: 100, now: Math.round(value) }}>
      <Svg width={size} height={size} style={{ transform: [{ rotate: '-90deg' }] }}>
        <Circle cx={size / 2} cy={size / 2} r={radius} stroke="#ECE4E8" strokeWidth={stroke} fill="none" />
        {value > 0 ? <Circle cx={size / 2} cy={size / 2} r={radius} stroke={color} strokeWidth={stroke} fill="none" strokeLinecap="round" strokeDasharray={`${(circumference * value) / 100} ${circumference}`} /> : null}
      </Svg>
      <View style={[StyleSheet.absoluteFill, styles.center]}>
        <AppText allowFontScaling={false} style={[styles.value, { fontSize: size * 0.22 }]}>
          {Math.round(value)}%
        </AppText>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  center: { alignItems: 'center', justifyContent: 'center' },
  value: { fontFamily: FONTS.bold, color: COLORS.black },
});
