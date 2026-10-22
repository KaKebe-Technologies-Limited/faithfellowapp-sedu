// An announcement from a church, read in full inside the app (urgent ones are marked in red), with 👍 ❤️ 😊 reactions under it so people can answer it with one tap.
import { Pressable, ScrollView, StyleSheet, View } from 'react-native';
import { Image } from 'expo-image';
import { router, useLocalSearchParams } from 'expo-router';
import AppText from '../../components/AppText';
import ChurchAvatar from '../../components/ChurchAvatar';
import Pill from '../../components/Pill';
import ReactionBar from '../../components/ReactionBar';
import ScreenHeader from '../../components/ScreenHeader';
import StateView from '../../components/StateView';
import { COLORS, FONTS, RADIUS, SPACING } from '../../constants/theme';
import { mediaUrl } from '../../lib/api';
import { formatDate } from '../../lib/format';
import { useFetch } from '../../lib/useFetch';

export default function AnnouncementScreen() {
  const { id } = useLocalSearchParams();
  const { data, loading, error, retry } = useFetch(`/announcements/${id}`);
  const a = data?.data;

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Announcement" />
      <StateView loading={loading} error={error} onRetry={retry}>
        {a ? (
          <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
            {a.image_url ? <Image source={{ uri: mediaUrl(a.image_url) }} style={styles.image} contentFit="cover" transition={250} /> : null}
            {a.urgent ? <Pill label="Urgent" icon="megaphone-outline" tone="urgent" /> : <Pill label="Announcement" icon="megaphone-outline" />}
            <AppText style={styles.title}>{a.title}</AppText>
            <Pressable onPress={() => a.church && router.push({ pathname: '/church/[slug]', params: { slug: a.church.slug } })} style={styles.host}>
              <ChurchAvatar church={a.church} size={30} />
              <View style={{ flex: 1 }}>
                <AppText style={styles.hostName}>{a.church?.name}</AppText>
                <AppText variant="caption">{formatDate(a.published_at, true)}</AppText>
              </View>
            </Pressable>
            <AppText style={styles.text}>{a.body}</AppText>
            <ReactionBar type="announcement" id={a.id} reactions={a.reactions} />
          </ScrollView>
        ) : null}
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  content: { padding: SPACING.md, paddingBottom: 48, gap: 12 },
  image: { width: '100%', aspectRatio: 16 / 9, borderRadius: RADIUS.xl - 4, backgroundColor: COLORS.gray100 },
  title: { fontFamily: FONTS.bold, fontSize: 24, lineHeight: 30, color: COLORS.black },
  host: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  hostName: { fontFamily: FONTS.semibold, fontSize: 15, color: COLORS.black },
  text: { fontFamily: FONTS.regular, fontSize: 16.5, lineHeight: 27, color: '#2B2B2B', marginTop: 4 },
});
