// The FaithFellows placeholder for a picture that is missing (a church banner, a group cover, a card without a photo): the white logo mark in the
// middle on a soft glow, and the mark big and faint in the background. It fills its parent and sits on the brand gradient the parent already
// draws (pass `gradient` when there is none), so a banner or a card without a photo still looks like part of the app and not like an empty box.
import { useState } from 'react';
import { Image, StyleSheet, View } from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { COLORS } from '../constants/theme';

const MARK = require('../assets/logo-mark.png');

export default function BrandPlaceholder({ gradient = false }) {
  const [box, setBox] = useState({ w: 0, h: 0 });
  const small = Math.min(box.w, box.h);
  const size = Math.round(small * 0.42);
  const faint = Math.round(Math.max(box.w, box.h) * 1.05);

  return (
    <View pointerEvents="none" style={StyleSheet.absoluteFill} onLayout={(e) => setBox({ w: e.nativeEvent.layout.width, h: e.nativeEvent.layout.height })}>
      {gradient ? <LinearGradient colors={[COLORS.primaryDark, COLORS.primary]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={StyleSheet.absoluteFill} /> : null}
      {box.w > 0 ? (
        <>
          {small >= 110 ? (
            <View style={[StyleSheet.absoluteFill, styles.clip]}>
              <Image source={MARK} resizeMode="contain" style={{ position: 'absolute', width: faint, height: faint, right: -faint * 0.28, top: -faint * 0.12, tintColor: '#fff', opacity: 0.08 }} />
            </View>
          ) : null}
          <View style={styles.center}>
            <View style={{ position: 'absolute', width: size * 1.7, height: size * 1.7, borderRadius: size, backgroundColor: 'rgba(255,255,255,0.1)' }} />
            <Image source={MARK} resizeMode="contain" style={{ width: size, height: size, tintColor: '#fff', opacity: 0.96 }} />
          </View>
        </>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  clip: { overflow: 'hidden' },
  center: { ...StyleSheet.absoluteFillObject, alignItems: 'center', justifyContent: 'center' },
});
