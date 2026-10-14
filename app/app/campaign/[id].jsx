// A giving campaign, in the app: cover, how much is raised, milestones reached, the story, updates from the
// church, the givers (the most recent first; anonymous gifts show as "Anonymous") and words of encouragement. "Donate" opens a
// short sheet: an amount and your mobile money number, then a prompt on your phone to approve with your PIN.
import { useState } from 'react';
import { Alert, ScrollView, Share, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useLocalSearchParams } from 'expo-router';
import AppText from '../../components/AppText';
import Button from '../../components/Button';
import ChurchAvatar from '../../components/ChurchAvatar';
import CoverHero from '../../components/CoverHero';
import DonateSheet from '../../components/DonateSheet';
import Pill from '../../components/Pill';
import { HeaderAction } from '../../components/ScreenHeader';
import StateView from '../../components/StateView';
import StickyBar from '../../components/StickyBar';
import TextField from '../../components/TextField';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import api, { errorMessage } from '../../lib/api';
import { formatMoney, timeAgo } from '../../lib/format';
import { useFetch } from '../../lib/useFetch';

export default function CampaignScreen() {
  const { id } = useLocalSearchParams();
  const { data, loading, error, retry, reload } = useFetch(`/campaigns/${id}`);
  const c = data?.data;
  const [comment, setComment] = useState('');
  const [sending, setSending] = useState(false);
  const [donating, setDonating] = useState(false);
  const pct = c ? Math.max(0, Math.min(100, Math.round(c.percent))) : 0;

  async function send() {
    if (!comment.trim()) return;
    setSending(true);
    try {
      await api.post(`/campaigns/${id}/comments`, { body: comment.trim() });
      setComment('');
      await reload();
    } catch (e) {
      Alert.alert('Could not send', errorMessage(e));
    } finally {
      setSending(false);
    }
  }

  return (
    <View style={styles.screen}>
      <StateView loading={loading} error={error} onRetry={retry}>
        {c ? (
          <>
            <ScrollView contentContainerStyle={{ paddingBottom: 32 }} showsVerticalScrollIndicator={false} keyboardShouldPersistTaps="handled">
              <CoverHero uri={c.cover_url} height={280} right={<HeaderAction onDark icon="share-social-outline" label="Share" onPress={() => Share.share({ message: `${c.title} — ${formatMoney(c.raised, c.currency)} raised of ${formatMoney(c.goal, c.currency)}` }).catch(() => {})} />}>
                <View style={{ flexDirection: 'row', gap: 6 }}>
                  <Pill label={c.type || 'Campaign'} icon="flag-outline" tone="dark" />
                  {c.goal_reached ? <Pill label="Goal reached 🎉" tone="dark" /> : null}
                </View>
                <AppText style={styles.heroTitle}>{c.title}</AppText>
                <View style={styles.host}>
                  <ChurchAvatar church={c.church} size={26} />
                  <AppText style={styles.hostName}>{c.church?.name}</AppText>
                </View>
              </CoverHero>

              <View style={styles.body}>
                <View style={styles.progressCard}>
                  <View style={styles.amountRow}>
                    <AppText style={styles.raised}>{formatMoney(c.raised, c.currency)}</AppText>
                    <AppText style={styles.pct}>{pct}%</AppText>
                  </View>
                  <AppText variant="caption">raised of {formatMoney(c.goal, c.currency)} goal</AppText>
                  <View style={styles.track}>
                    <View style={[styles.fill, { width: `${Math.max(pct, 2)}%` }]} />
                  </View>
                  <View style={styles.statRow}>
                    <View style={styles.stat}>
                      <AppText style={styles.statValue}>{c.givers}</AppText>
                      <AppText variant="caption">{c.givers === 1 ? 'giver' : 'givers'}</AppText>
                    </View>
                    <View style={styles.stat}>
                      <AppText style={styles.statValue}>{c.days_left != null ? c.days_left : '—'}</AppText>
                      <AppText variant="caption">days left</AppText>
                    </View>
                    <View style={styles.stat}>
                      <AppText style={styles.statValue}>{formatMoney(Math.max(0, c.goal - c.raised), c.currency).replace(`${c.currency} `, '')}</AppText>
                      <AppText variant="caption">to go</AppText>
                    </View>
                  </View>
                </View>

                {c.milestones?.length ? (
                  <View style={styles.milestones}>
                    {c.milestones.map((m) => (
                      <View key={m.percent} style={styles.milestone}>
                        <View style={[styles.mark, m.reached && styles.markOn]}>
                          <Ionicons name={m.reached ? 'checkmark' : 'ellipse-outline'} size={m.reached ? 16 : 10} color={m.reached ? COLORS.white : COLORS.gray300} />
                        </View>
                        <AppText variant="caption" style={{ color: m.reached ? COLORS.primary : COLORS.gray700, fontFamily: FONTS.semibold }}>
                          {m.percent}%
                        </AppText>
                      </View>
                    ))}
                  </View>
                ) : null}

                {c.description ? (
                  <View style={styles.section}>
                    <AppText style={styles.h2}>The story</AppText>
                    <AppText style={styles.text}>{c.description}</AppText>
                  </View>
                ) : null}

                {c.updates?.length ? (
                  <View style={styles.section}>
                    <AppText style={styles.h2}>Updates</AppText>
                    {c.updates.map((u) => (
                      <View key={u.id} style={styles.update}>
                        <AppText style={styles.updateTitle}>{u.title}</AppText>
                        {u.body ? <AppText style={styles.text}>{u.body}</AppText> : null}
                        <AppText variant="caption" style={{ marginTop: 4 }}>
                          {timeAgo(u.published_at)}
                        </AppText>
                      </View>
                    ))}
                  </View>
                ) : null}

                <View style={styles.section}>
                  <AppText style={styles.h2}>Givers</AppText>
                  {c.recent_givers?.length ? (
                    c.recent_givers.map((g, i) => (
                      <View key={i} style={styles.giver}>
                        <View style={styles.heart}>
                          <Ionicons name="heart" size={14} color={COLORS.primary} />
                        </View>
                        <View style={{ flex: 1 }}>
                          <AppText style={styles.updateTitle}>{g.name}</AppText>
                          <AppText variant="caption">{timeAgo(g.at)}</AppText>
                        </View>
                        <AppText style={styles.pct}>{formatMoney(g.amount, c.currency)}</AppText>
                      </View>
                    ))
                  ) : (
                    <AppText variant="caption">Be the first to give to this fundraiser.</AppText>
                  )}
                </View>

                <View style={styles.section}>
                  <AppText style={styles.h2}>Words of encouragement</AppText>
                  <TextField placeholder="Write an encouraging word…" value={comment} onChangeText={setComment} maxLength={1000} multiline />
                  <Button title="Post" small loading={sending} disabled={!comment.trim()} onPress={send} style={{ alignSelf: 'flex-end', marginTop: -6 }} />
                  {(c.comments ?? []).map((m) => (
                    <View key={m.id} style={styles.comment}>
                      <AppText style={styles.updateTitle}>{m.author || 'A member'}</AppText>
                      <AppText style={styles.text}>{m.body}</AppText>
                      <AppText variant="caption">{timeAgo(m.at)}</AppText>
                    </View>
                  ))}
                </View>
              </View>
            </ScrollView>
            {c.is_open ? (
              <StickyBar title={`${pct}% funded`} subtitle={`${formatMoney(c.raised, c.currency)} raised`} action="Donate" onPress={() => setDonating(true)} />
            ) : (
              <StickyBar title="This campaign has closed" subtitle="Thank you to everyone who gave" />
            )}
            <DonateSheet visible={donating} onClose={() => setDonating(false)} church={c.church} campaign={c} />
          </>
        ) : null}
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  heroTitle: { fontFamily: FONTS.bold, fontSize: 27, lineHeight: 33, color: COLORS.white, marginTop: 4 },
  host: { flexDirection: 'row', alignItems: 'center', gap: 8, marginTop: 4 },
  hostName: { fontFamily: FONTS.semibold, fontSize: 14, color: 'rgba(255,255,255,0.92)' },
  body: { padding: SPACING.md },
  progressCard: { backgroundColor: COLORS.white, borderRadius: RADIUS.xl, padding: SPACING.md, ...SHADOW },
  amountRow: { flexDirection: 'row', alignItems: 'baseline', justifyContent: 'space-between' },
  raised: { fontFamily: FONTS.bold, fontSize: 28, color: COLORS.black },
  pct: { fontFamily: FONTS.bold, fontSize: 16, color: COLORS.primary },
  track: { height: 10, borderRadius: 5, backgroundColor: COLORS.primaryMist, marginTop: 12, overflow: 'hidden' },
  fill: { height: 10, borderRadius: 5, backgroundColor: COLORS.primary },
  statRow: { flexDirection: 'row', justifyContent: 'space-between', marginTop: 16 },
  stat: { flex: 1, alignItems: 'center' },
  statValue: { fontFamily: FONTS.bold, fontSize: 17, color: COLORS.black },
  milestones: { flexDirection: 'row', justifyContent: 'space-around', marginTop: 14 },
  milestone: { alignItems: 'center', gap: 4 },
  mark: { width: 28, height: 28, borderRadius: 14, backgroundColor: COLORS.white, borderWidth: 1.5, borderColor: COLORS.gray300, alignItems: 'center', justifyContent: 'center' },
  markOn: { backgroundColor: COLORS.primary, borderColor: COLORS.primary },
  section: { marginTop: SPACING.lg },
  h2: { fontFamily: FONTS.bold, fontSize: 18, color: COLORS.black, marginBottom: 10 },
  text: { fontFamily: FONTS.regular, fontSize: 15, lineHeight: 23, color: COLORS.gray900 },
  update: { backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: 14, marginBottom: 10, gap: 4 },
  updateTitle: { fontFamily: FONTS.semibold, fontSize: 15, color: COLORS.black },
  giver: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 9, borderBottomWidth: 1, borderBottomColor: COLORS.gray100 },
  rank: { width: 26, fontFamily: FONTS.bold, fontSize: 15, color: COLORS.primary },
  heart: { width: 28, height: 28, borderRadius: 14, backgroundColor: COLORS.primaryMist, alignItems: 'center', justifyContent: 'center' },
  comment: { backgroundColor: COLORS.white, borderRadius: RADIUS.md, padding: 12, marginTop: 10, gap: 3 },
});
