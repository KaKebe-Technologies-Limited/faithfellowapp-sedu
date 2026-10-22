// A small "Comments" button with how many there are. Tap it and the comments slide up from the bottom (to read, to write one, to report); close it and the
// page is as it was. `base` is the address the comments belong to (/podcast-episodes/12, /radio/stations/podcast). `dark` is for the dark player screens; `center` centres it.
import { useEffect, useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { FONTS, RADIUS } from '../../constants/theme';
import { PURPLE } from '../../constants/podcastTheme';
import api from '../../lib/api';
import AppText from '../AppText';
import Sheet from '../Sheet';
import Comments from './Comments';

export default function CommentsButton({ base, title = 'Comments', dark = false, center = false, style }) {
  const [open, setOpen] = useState(false);
  const [total, setTotal] = useState(null);

  // How many comments there are: one small request, and again after the sheet is closed (the member may have written one).
  useEffect(() => {
    if (open) return undefined;
    let alive = true;
    api
      .get(`${base}/comments`, { params: { per_page: 1 } })
      .then(({ data }) => alive && setTotal(data.meta?.total ?? 0))
      .catch(() => {});

    return () => {
      alive = false;
    };
  }, [base, open]);

  return (
    <View style={style}>
      <Pressable
        onPress={() => setOpen(true)}
        accessibilityRole="button"
        accessibilityLabel={total ? `${title}, ${total}. Open` : `${title}. Open`}
        style={({ pressed }) => [styles.button, dark ? styles.dark : styles.light, center && { alignSelf: 'center' }, pressed && { opacity: 0.85 }]}
      >
        <Ionicons name="chatbubble-ellipses" size={17} color={dark ? PURPLE.pale : PURPLE.main} />
        <AppText style={[styles.label, { color: dark ? '#fff' : PURPLE.main }]}>{total ? `${title} · ${total}` : title}</AppText>
      </Pressable>

      <Sheet visible={open} onClose={() => setOpen(false)} title={title} tall>
        {open ? <Comments base={base} hideTitle /> : null}
      </Sheet>
    </View>
  );
}

const styles = StyleSheet.create({
  button: { alignSelf: 'flex-start', flexDirection: 'row', alignItems: 'center', gap: 8, borderRadius: RADIUS.full, paddingHorizontal: 16, paddingVertical: 9 },
  light: { backgroundColor: PURPLE.mist },
  dark: { backgroundColor: 'rgba(255,255,255,0.12)' },
  label: { fontFamily: FONTS.semibold, fontSize: 14 },
});
