// An update from a church with all its photos: swipe through the pictures, read the caption. Opened from the home
// feed and from a notification. Data: GET /api/v1/posts/{id}.
import { useState } from 'react';
import { Pressable, ScrollView, StyleSheet, View, useWindowDimensions } from 'react-native';
import { Image } from 'expo-image';
import { router, useLocalSearchParams } from 'expo-router';
import AppText from '../../components/AppText';
import ChurchAvatar from '../../components/ChurchAvatar';
import ScreenHeader from '../../components/ScreenHeader';
import StateView from '../../components/StateView';
import { COLORS, FONTS, RADIUS, SPACING } from '../../constants/theme';
import { mediaUrl } from '../../lib/api';
import { timeAgo } from '../../lib/format';
import { useFetch } from '../../lib/useFetch';

export default function PostScreen() {
  const { id } = useLocalSearchParams();
  const { data, loading, error, retry } = useFetch(`/posts/${id}`);
  const { width } = useWindowDimensions();
  const [page, setPage] = useState(0);
  const p = data?.data;
  const size = Math.min(width, 560) - SPACING.md * 2;

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Update" subtitle={p?.church?.name} />
      <StateView loading={loading} error={error} onRetry={retry}>
        {p ? (
          <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
            <Pressable onPress={() => router.push({ pathname: '/church/[slug]', params: { slug: p.church.slug } })} style={styles.host} accessibilityRole="button">
              <ChurchAvatar church={p.church} size={38} />
              <View style={{ flex: 1 }}>
                <AppText style={styles.hostName}>{p.church.name}</AppText>
                <AppText variant="caption">{timeAgo(p.published_at)}</AppText>
              </View>
            </Pressable>

            {p.images.length ? (
              <View>
                <ScrollView
                  horizontal
                  pagingEnabled
                  showsHorizontalScrollIndicator={false}
                  style={{ width: size, borderRadius: RADIUS.xl - 4 }}
                  onScroll={(e) => setPage(Math.round(e.nativeEvent.contentOffset.x / size))}
                  scrollEventThrottle={64}
                >
                  {p.images.map((uri, i) => (
                    <Image key={uri + i} source={{ uri: mediaUrl(uri) }} style={{ width: size, height: size * 0.82, backgroundColor: COLORS.gray100 }} contentFit="cover" transition={200} accessibilityLabel={`Photo ${i + 1} of ${p.images.length}`} />
                  ))}
                </ScrollView>
                {p.images.length > 1 ? (
                  <View style={styles.dots}>
                    {p.images.map((_, i) => (
                      <View key={i} style={[styles.dot, i === page && styles.dotOn]} />
                    ))}
                  </View>
                ) : null}
              </View>
            ) : null}

            {p.body ? <AppText style={styles.text}>{p.body}</AppText> : null}
          </ScrollView>
        ) : null}
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  content: { padding: SPACING.md, paddingBottom: 48, gap: 14 },
  host: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  hostName: { fontFamily: FONTS.semibold, fontSize: 15.5, color: COLORS.black },
  dots: { flexDirection: 'row', justifyContent: 'center', gap: 6, marginTop: 10 },
  dot: { width: 7, height: 7, borderRadius: 4, backgroundColor: COLORS.gray300 },
  dotOn: { backgroundColor: COLORS.primary, width: 18 },
  text: { fontFamily: FONTS.regular, fontSize: 16.5, lineHeight: 25, color: COLORS.black },
});
