// Me: a profile page in the style of a wellness app, on the FaithFellows colours. A deep wine band with your photo over its edge (tap the
// pencil to change it), your name and two small badges; a reminder to finish your profile, and, first of all, to add your phone number
// (it is saved on your profile and fills in mobile money when you give); the churches you follow; "My progress" with rings for the time you
// spent in the Bible, listening and sharing this week or month; a summary of your numbers; your week day by day; your latest notes; and
// your things (notifications, messages, notes, password, sign out). Data: GET /api/v1/me and GET /api/v1/me/stats.
import { useCallback, useState } from 'react';
import { Alert, Pressable, RefreshControl, ScrollView, StyleSheet, TextInput, View, useWindowDimensions } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { LinearGradient } from 'expo-linear-gradient';
import { router, useFocusEffect } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import AppText from '../../components/AppText';
import Avatar from '../../components/Avatar';
import Button from '../../components/Button';
import ChurchAvatar from '../../components/ChurchAvatar';
import ProgressRing from '../../components/ProgressRing';
import Skeleton from '../../components/Skeleton';
import StateView from '../../components/StateView';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING, MAX_FONT_SCALE } from '../../constants/theme';
import { SOFT, SOFT_SHADOW } from '../../constants/soft';
import api, { errorMessage, fieldErrors } from '../../lib/api';
import { useAuth } from '../../lib/auth';
import { formatMoney, timeAgo } from '../../lib/format';
import { useOutbox } from '../../lib/outbox';
import { versionLabel } from '../../lib/updates';
import { useFetch } from '../../lib/useFetch';

const MENU = [
  { icon: 'notifications-outline', label: 'Notifications', path: '/notifications' },
  { icon: 'options-outline', label: 'Notification settings', path: '/settings/notifications' },
  { icon: 'color-palette-outline', label: 'Appearance', path: '/settings/appearance' },
  { icon: 'chatbubble-ellipses-outline', label: 'Messages', path: '/chats' },
  { icon: 'people-circle-outline', label: 'My connections', path: '/connections' },
  { icon: 'ticket-outline', label: 'My tickets', path: '/tickets' },
  { icon: 'create-outline', label: 'My notes', path: '/notes' },
  { icon: 'journal-outline', label: 'Reading plans', path: '/plans' },
  { icon: 'ban-outline', label: 'Blocked people', path: '/blocked' },
  { icon: 'key-outline', label: 'Change password', path: '/password' },
  { icon: 'document-text-outline', label: 'Terms of Service', path: '/legal/terms' },
  { icon: 'shield-checkmark-outline', label: 'Privacy Policy', path: '/legal/privacy' },
  { icon: 'trash-outline', label: 'Delete my account', path: '/delete-account' },
];

const RING_COLORS = { bible: COLORS.primary, listening: COLORS.success, sharing: COLORS.info };
const RING_TITLES = { bible: 'Bible', listening: 'Listening', sharing: 'Sharing' };

const minutes = (m) => (m >= 60 ? `${Math.floor(m / 60)} h ${m % 60 ? `${m % 60} min` : ''}`.trim() : `${m} min`);

function Section({ title, action, onAction, children }) {
  return (
    <View style={styles.section}>
      <View style={styles.sectionHead}>
        <AppText style={styles.sectionTitle}>{title}</AppText>
        {action ? (
          <Pressable onPress={onAction} hitSlop={8} accessibilityRole="button" style={styles.sectionAction}>
            <AppText style={styles.sectionActionText}>{action}</AppText>
            <Ionicons name="chevron-forward" size={14} color={SOFT.muted} />
          </Pressable>
        ) : null}
      </View>
      {children}
    </View>
  );
}

function Tile({ icon, color, value, label, width }) {
  return (
    <View style={[styles.tile, { width }]}>
      <View style={[styles.tileIcon, { backgroundColor: `${color}1F` }]}>
        <Ionicons name={icon} size={18} color={color} />
      </View>
      <AppText style={styles.tileValue} numberOfLines={1} adjustsFontSizeToFit>
        {value}
      </AppText>
      <AppText style={styles.tileLabel} numberOfLines={1}>
        {label}
      </AppText>
    </View>
  );
}

/** The first thing to finish: the phone number that is kept on the profile and used when giving. */
function PhonePrompt({ onSaved }) {
  const { setUser } = useAuth();
  const [phone, setPhone] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  async function save() {
    if (phone.replace(/\D/g, '').length < 9) {
      setError('Enter your mobile money number, for example 0772 123 456.');
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const { data } = await api.patch('/me', { phone: phone.trim() });
      setUser(data.data ?? data);
      onSaved();
    } catch (e) {
      setError(fieldErrors(e).phone || errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  return (
    <View style={styles.phoneCard}>
      <View style={styles.phoneTop}>
        <View style={styles.phoneIcon}>
          <Ionicons name="call" size={18} color={COLORS.onColor} />
        </View>
        <View style={{ flex: 1 }}>
          <AppText style={styles.phoneTitle}>Add your phone number</AppText>
          <AppText style={styles.phoneText}>It is saved on your profile and fills in mobile money for you whenever you give.</AppText>
        </View>
      </View>
      <View style={styles.phoneRow}>
        <TextInput
          maxFontSizeMultiplier={MAX_FONT_SCALE}
          value={phone}
          onChangeText={(t) => {
            setPhone(t);
            setError(null);
          }}
          keyboardType="phone-pad"
          placeholder="0772 123 456"
          placeholderTextColor={COLORS.gray500}
          autoComplete="tel"
          style={styles.phoneInput}
          onSubmitEditing={save}
          accessibilityLabel="Phone number"
        />
        <Button title="Save" small loading={busy} onPress={save} />
      </View>
      {error ? <AppText style={styles.phoneError}>{error}</AppText> : null}
    </View>
  );
}

export default function ProfileScreen() {
  const insets = useSafeAreaInsets();
  const { width: screen } = useWindowDimensions();
  const tileWidth = Math.floor((screen - SPACING.md * 2 - 20) / 3);
  const { signOut } = useAuth();
  const { pending } = useOutbox();
  const [period, setPeriod] = useState('week');
  const me = useFetch('/me');
  const stats = useFetch(`/me/stats?period=${period}`);
  const user = me.data?.data ?? me.data;
  const s = stats.data?.data;

  // Coming back to this tab (after reading or listening) shows fresh numbers.
  useFocusEffect(
    useCallback(() => {
      stats.reload();
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []),
  );

  const refresh = () => {
    me.reload();
    stats.reload();
  };

  function confirmSignOut() {
    const lose = pending === 1 ? 'it' : 'them';
    const note = pending ? `\n\nYou have ${pending} change${pending === 1 ? '' : 's'} (notes or highlights) that could not be sent yet. Signing out now will lose ${lose}.` : '';
    Alert.alert('Sign out?', `You will need your email and password to sign in again.${note}`, [
      { text: 'Cancel', style: 'cancel' },
      {
        text: 'Sign out',
        style: 'destructive',
        onPress: async () => {
          await signOut();
          router.replace('/(auth)/welcome');
        },
      },
    ]);
  }

  const role = user?.is_super_admin ? 'Super admin' : user?.staff_of?.length ? 'Church staff' : 'Member';
  const secondBadge = user?.churches?.[0]?.name ?? (s?.other?.member_since ? `Since ${new Date(s.other.member_since).toLocaleDateString(undefined, { month: 'short', year: 'numeric' })}` : null);
  const t = s?.totals;
  const other = s?.other;
  const week = s?.week ?? [];
  const best = Math.max(10, ...week.map((d) => d.read_minutes + d.listen_minutes));
  const missing = s?.profile?.missing ?? [];
  const showPhone = s?.profile?.phone_missing;
  const otherMissing = missing.filter((m) => m.key !== 'phone');

  return (
    <StateView loading={me.loading} error={me.error} onRetry={me.retry} empty={!me.loading && !me.error && !user} emptyIcon="person-outline" emptyTitle="No profile found">
      <ScrollView
        style={styles.screen}
        contentContainerStyle={{ paddingBottom: 40 }}
        showsVerticalScrollIndicator={false}
        refreshControl={<RefreshControl refreshing={me.refreshing || stats.refreshing} onRefresh={refresh} tintColor={COLORS.primary} colors={[COLORS.primary]} />}
      >
        <LinearGradient colors={SOFT.panel} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={[styles.band, { height: 150 + insets.top }]}>
          <View style={styles.moon} />
          <View style={styles.moonSmall} />
          <Pressable onPress={() => router.push('/profile/edit')} style={[styles.bandEdit, { top: insets.top + 12 }]} accessibilityRole="button" accessibilityLabel="Edit profile" hitSlop={8}>
            <Ionicons name="create-outline" size={20} color={COLORS.onColor} />
          </Pressable>
        </LinearGradient>

        <View style={styles.identity}>
          <View style={styles.avatarWrap}>
            <Avatar person={user} size={104} ring />
            <Pressable onPress={() => router.push('/profile/edit')} style={styles.avatarEdit} accessibilityRole="button" accessibilityLabel="Change your photo">
              <Ionicons name="create-outline" size={16} color={COLORS.primary} />
            </Pressable>
          </View>
          <AppText style={styles.name} numberOfLines={2}>
            {user?.name}
          </AppText>
          <View style={styles.badges}>
            <View style={styles.badgeOn}>
              <Ionicons name={role === 'Member' ? 'person' : 'shield-checkmark'} size={12} color={COLORS.onColor} />
              <AppText style={styles.badgeOnText}>{role}</AppText>
            </View>
            {secondBadge ? (
              <View style={styles.badgeSoft}>
                <AppText style={styles.badgeSoftText} numberOfLines={1}>
                  {secondBadge}
                </AppText>
              </View>
            ) : null}
          </View>
        </View>

        {/* Finish your profile: the phone first, then what else is missing. */}
        {showPhone ? (
          <View style={styles.pad}>
            <PhonePrompt
              onSaved={() => {
                me.reload();
                stats.reload();
              }}
            />
          </View>
        ) : null}
        {s && otherMissing.length ? (
          <View style={styles.pad}>
            <Pressable onPress={() => router.push('/profile/edit')} style={styles.complete} accessibilityRole="button" accessibilityLabel="Complete your profile">
              <View style={styles.completeTop}>
                <AppText style={styles.completeTitle}>Complete your profile</AppText>
                <AppText style={styles.completePct}>{s.profile.percent}%</AppText>
              </View>
              <View style={styles.track}>
                <View style={[styles.fill, { width: `${Math.max(4, s.profile.percent)}%` }]} />
              </View>
              {otherMissing.slice(0, 3).map((m) => (
                <View key={m.key} style={styles.missing}>
                  <Ionicons name="ellipse-outline" size={14} color={COLORS.primary} />
                  <AppText style={styles.missingText} numberOfLines={1}>
                    {m.label}
                  </AppText>
                </View>
              ))}
              <AppText style={styles.completeLink}>Update my profile</AppText>
            </Pressable>
          </View>
        ) : null}

        <Section title="My churches">
          {user?.churches?.length ? (
            <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.rail}>
              {user.churches.map((church) => (
                <Pressable key={church.id} onPress={() => router.push({ pathname: '/church/[slug]', params: { slug: church.slug } })} style={styles.church} accessibilityRole="button" accessibilityLabel={church.name}>
                  <ChurchAvatar church={church} size={52} />
                  <View style={{ flex: 1 }}>
                    <AppText style={styles.churchName} numberOfLines={2}>
                      {church.name}
                    </AppText>
                    <AppText variant="caption">Following</AppText>
                  </View>
                </Pressable>
              ))}
            </ScrollView>
          ) : (
            <Pressable onPress={() => router.push('/churches')} style={[styles.church, { marginHorizontal: SPACING.md }]} accessibilityRole="button">
              <View style={styles.findIcon}>
                <Ionicons name="search" size={22} color={COLORS.primary} />
              </View>
              <View style={{ flex: 1 }}>
                <AppText style={styles.churchName}>Find your church</AppText>
                <AppText variant="caption">Follow it to get its sermons, events and prayer requests.</AppText>
              </View>
            </Pressable>
          )}
        </Section>

        <Section title="My progress">
          <View style={styles.periods}>
            {[
              ['week', 'This week'],
              ['month', 'This month'],
            ].map(([key, label]) => (
              <Pressable key={key} onPress={() => setPeriod(key)} style={[styles.period, period === key && styles.periodOn]} accessibilityRole="button" accessibilityState={{ selected: period === key }}>
                <AppText style={[styles.periodText, period === key && { color: COLORS.onColor }]}>{label}</AppText>
              </Pressable>
            ))}
          </View>
          {s ? (
            <>
              <View style={styles.rings}>
                {s.rings.map((r) => (
                  <View key={r.key} style={styles.ring}>
                    <AppText style={[styles.ringTitle, { color: RING_COLORS[r.key] }]}>{RING_TITLES[r.key] ?? r.label}</AppText>
                    <ProgressRing percent={r.percent} color={RING_COLORS[r.key]} size={92} label={`${r.label}: ${r.percent} percent`} />
                    <AppText style={styles.ringCaption} numberOfLines={1}>
                      {r.unit === 'min' ? `${minutes(r.value)} of ${minutes(r.goal)}` : `${r.value} of ${r.goal} shared`}
                    </AppText>
                  </View>
                ))}
              </View>
              <View style={styles.summary}>
                <Ionicons name="sparkles" size={18} color={COLORS.primary} />
                <AppText style={styles.summaryText}>
                  {t.read_minutes + t.listen_minutes + t.shares === 0
                    ? `Nothing yet ${period === 'week' ? 'this week' : 'this month'}. Open the Bible, play the radio or share a verse, and your rings fill up.`
                    : `${period === 'week' ? 'This week' : 'This month'} you read the Bible for ${minutes(t.read_minutes)}${t.chapters ? ` (${t.chapters} ${t.chapters === 1 ? 'chapter' : 'chapters'})` : ''}, listened for ${minutes(t.listen_minutes)} and shared ${t.shares} ${t.shares === 1 ? 'verse or message' : 'verses and messages'}. Keep going!`}
                </AppText>
              </View>
            </>
          ) : (
            <View style={styles.rings}>
              {[0, 1, 2].map((i) => (
                <Skeleton key={i} width={92} height={120} radius={RADIUS.lg} />
              ))}
            </View>
          )}
        </Section>

        {s ? (
          <Section title="Your numbers" action="See all" onAction={() => router.push('/stats')}>
            <View style={styles.tiles}>
              <Tile icon="flame" color="#E8590C" value={`${s.streak.current} ${s.streak.current === 1 ? 'day' : 'days'}`} label={`Streak · best ${s.streak.best}`} width={tileWidth} />
              <Tile icon="book" color={COLORS.primary} value={String(t.chapters)} label="Chapters read" width={tileWidth} />
              <Tile icon="headset" color={COLORS.success} value={minutes(t.listen_minutes)} label="Listened" width={tileWidth} />
              <Tile icon="share-social" color={COLORS.info} value={String(t.shares)} label="Shared" width={tileWidth} />
              <Tile icon="color-wand" color="#B58A00" value={String(other.highlights)} label="Highlights" width={tileWidth} />
              <Tile icon="hand-left" color="#E65100" value={String(other.prayers)} label="Prayed for" width={tileWidth} />
            </View>
          </Section>
        ) : null}

        {s ? (
          <Section title="Your week">
            <View style={styles.bars}>
              {week.map((d) => {
                const total = d.read_minutes + d.listen_minutes;
                return (
                  <View key={d.day} style={styles.barCol}>
                    <View style={styles.barTrack}>
                      <View style={[styles.barPart, { height: `${(d.listen_minutes / best) * 100}%`, backgroundColor: COLORS.success }]} />
                      <View style={[styles.barPart, { height: `${(d.read_minutes / best) * 100}%`, backgroundColor: COLORS.primary }]} />
                    </View>
                    <AppText style={styles.barLabel}>{d.label}</AppText>
                    <AppText style={styles.barValue}>{total ? `${total}m` : '·'}</AppText>
                  </View>
                );
              })}
            </View>
            <View style={styles.legend}>
              <View style={styles.legendItem}>
                <View style={[styles.dot, { backgroundColor: COLORS.primary }]} />
                <AppText variant="caption">Bible</AppText>
              </View>
              <View style={styles.legendItem}>
                <View style={[styles.dot, { backgroundColor: COLORS.success }]} />
                <AppText variant="caption">Listening</AppText>
              </View>
            </View>
          </Section>
        ) : null}

        {s ? (
          <Section title="Notes" action={other.notes ? 'Show all' : undefined} onAction={() => router.push('/notes')}>
            {s.notes.length ? (
              <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.rail}>
                {s.notes.map((n) => (
                  <Pressable key={n.id} onPress={() => router.push('/notes')} style={[styles.note, { backgroundColor: n.color_hex }]} accessibilityRole="button" accessibilityLabel={`Note: ${n.title}`}>
                    <AppText style={styles.noteDate}>{timeAgo(n.updated_at)}</AppText>
                    <AppText style={styles.noteTitle} numberOfLines={1}>
                      {n.title}
                    </AppText>
                    <AppText style={styles.noteBody} numberOfLines={3}>
                      {n.body || n.reference}
                    </AppText>
                  </Pressable>
                ))}
              </ScrollView>
            ) : (
              <Pressable onPress={() => router.push('/(tabs)/bible')} style={[styles.church, { marginHorizontal: SPACING.md }]} accessibilityRole="button">
                <View style={styles.findIcon}>
                  <Ionicons name="create-outline" size={22} color={COLORS.primary} />
                </View>
                <View style={{ flex: 1 }}>
                  <AppText style={styles.churchName}>Write your first note</AppText>
                  <AppText variant="caption">Open a Bible chapter and tap Notes to keep what God says to you.</AppText>
                </View>
              </Pressable>
            )}
          </Section>
        ) : null}

        {user?.is_super_admin ? (
          <View style={styles.pad}>
            <Pressable onPress={() => router.push('/platform')} style={styles.admin} accessibilityRole="button" accessibilityLabel="Platform admin">
              <View style={styles.adminIcon}>
                <Ionicons name="planet" size={22} color={COLORS.onColor} />
              </View>
              <View style={{ flex: 1 }}>
                <AppText style={styles.adminTitle}>Platform admin</AppText>
                <AppText variant="caption">Church applications, people, reports, podcasts and broadcasts</AppText>
              </View>
              <Ionicons name="chevron-forward" size={18} color={COLORS.primary} />
            </Pressable>
          </View>
        ) : null}

        {user?.staff_of?.length || user?.is_super_admin ? (
          <View style={styles.pad}>
            <Pressable onPress={() => router.push('/admin')} style={styles.admin} accessibilityRole="button" accessibilityLabel="Manage your church">
              <View style={styles.adminIcon}>
                <Ionicons name="shield-checkmark" size={22} color={COLORS.onColor} />
              </View>
              <View style={{ flex: 1 }}>
                <AppText style={styles.adminTitle}>{user.is_super_admin ? 'Manage any church' : `Manage ${user.staff_of.length === 1 ? user.staff_of[0].name : 'your churches'}`}</AppText>
                <AppText variant="caption">{user.is_super_admin ? 'Open a church to update its announcements, events, prayer and more' : 'Announcements, events, prayer, quizzes, live and more'}</AppText>
              </View>
              <Ionicons name="chevron-forward" size={18} color={COLORS.primary} />
            </Pressable>
          </View>
        ) : null}

        <Section title="My things">
          <View style={[styles.menu, styles.pad2]}>
            {MENU.map((item, i) => (
              <Pressable key={item.path} onPress={() => router.push(item.path)} style={[styles.menuRow, i > 0 && styles.menuBorder]} accessibilityRole="button">
                <View style={styles.menuIcon}>
                  <Ionicons name={item.icon} size={18} color={COLORS.primary} />
                </View>
                <AppText style={{ flex: 1, fontFamily: FONTS.medium, color: COLORS.black }}>{item.label}</AppText>
                <Ionicons name="chevron-forward" size={16} color={COLORS.gray500} />
              </Pressable>
            ))}
          </View>
        </Section>

        <View style={styles.pad}>
          <Button title="Sign out" onPress={confirmSignOut} />
          <AppText variant="caption" style={styles.version}>
            FaithFellows {versionLabel()}
          </AppText>
        </View>
      </ScrollView>
    </StateView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: SOFT.surface },
  pad: { paddingHorizontal: SPACING.md, marginTop: SPACING.md },
  pad2: { marginHorizontal: SPACING.md },
  band: { overflow: 'hidden' },
  moon: { position: 'absolute', right: -40, top: -50, width: 190, height: 190, borderRadius: 95, backgroundColor: 'rgba(255,255,255,0.09)' },
  moonSmall: { position: 'absolute', left: -30, bottom: -50, width: 120, height: 120, borderRadius: 60, backgroundColor: 'rgba(255,255,255,0.06)' },
  bandEdit: { position: 'absolute', right: SPACING.md, width: 38, height: 38, borderRadius: 19, backgroundColor: 'rgba(255,255,255,0.16)', alignItems: 'center', justifyContent: 'center' },
  identity: { alignItems: 'center', marginTop: -52, paddingHorizontal: SPACING.md },
  avatarWrap: { width: 104, height: 104 },
  avatarEdit: { position: 'absolute', right: -2, bottom: 2, width: 34, height: 34, borderRadius: 17, backgroundColor: COLORS.white, alignItems: 'center', justifyContent: 'center', ...SHADOW },
  name: { fontFamily: FONTS.display, fontSize: 24, color: COLORS.black, marginTop: 10, textAlign: 'center' },
  badges: { flexDirection: 'row', gap: 8, marginTop: 8, alignItems: 'center', maxWidth: '100%' },
  badgeOn: { flexDirection: 'row', alignItems: 'center', gap: 5, backgroundColor: COLORS.primary, borderRadius: RADIUS.md, paddingHorizontal: 12, paddingVertical: 6 },
  badgeOnText: { fontFamily: FONTS.semibold, fontSize: 13, color: COLORS.onColor },
  badgeSoft: { backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.md, paddingHorizontal: 12, paddingVertical: 6, flexShrink: 1 },
  badgeSoftText: { fontFamily: FONTS.semibold, fontSize: 13, color: COLORS.primaryInk },

  phoneCard: { backgroundColor: COLORS.white, borderRadius: 24, padding: 14, borderWidth: 1.5, borderColor: COLORS.primaryPale, ...SOFT_SHADOW },
  phoneTop: { flexDirection: 'row', gap: 12, alignItems: 'flex-start' },
  phoneIcon: { width: 38, height: 38, borderRadius: 13, backgroundColor: COLORS.primary, alignItems: 'center', justifyContent: 'center' },
  phoneTitle: { fontFamily: FONTS.bold, fontSize: 15.5, color: COLORS.black },
  phoneText: { fontFamily: FONTS.regular, fontSize: 12.5, lineHeight: 18, color: COLORS.gray700, marginTop: 2 },
  phoneRow: { flexDirection: 'row', gap: 10, alignItems: 'center', marginTop: 12 },
  phoneInput: { flex: 1, minHeight: 46, borderWidth: 1.5, borderColor: COLORS.gray300, borderRadius: RADIUS.lg, paddingHorizontal: 14, fontFamily: FONTS.semibold, fontSize: 16, color: COLORS.black },
  phoneError: { fontFamily: FONTS.medium, fontSize: 12.5, color: COLORS.danger, marginTop: 8 },

  complete: { backgroundColor: COLORS.white, borderRadius: 24, padding: 16, gap: 8, ...SOFT_SHADOW },
  completeTop: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'baseline' },
  completeTitle: { fontFamily: FONTS.bold, fontSize: 16, color: COLORS.black },
  completePct: { fontFamily: FONTS.bold, fontSize: 16, color: COLORS.primary },
  track: { height: 8, borderRadius: 4, backgroundColor: COLORS.primaryMist, overflow: 'hidden' },
  fill: { height: 8, borderRadius: 4, backgroundColor: COLORS.primary },
  missing: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  missingText: { flex: 1, fontFamily: FONTS.medium, fontSize: 13.5, color: COLORS.gray900 },
  completeLink: { fontFamily: FONTS.bold, fontSize: 13.5, color: COLORS.primary, marginTop: 2 },

  section: { marginTop: SPACING.lg },
  sectionHead: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: SPACING.md, marginBottom: 10 },
  sectionTitle: { fontFamily: FONTS.bold, fontSize: 18, color: SOFT.ink },
  sectionAction: { flexDirection: 'row', alignItems: 'center', gap: 2 },
  sectionActionText: { fontFamily: FONTS.regular, fontSize: 13, color: SOFT.muted },
  rail: { paddingHorizontal: SPACING.md, gap: 12, paddingBottom: 6 },

  church: { flexDirection: 'row', alignItems: 'center', gap: 12, minWidth: 230, backgroundColor: COLORS.white, borderRadius: 20, padding: 12, ...SOFT_SHADOW },
  churchName: { fontFamily: FONTS.bold, fontSize: 14.5, lineHeight: 19, color: COLORS.black },
  findIcon: { width: 52, height: 52, borderRadius: 18, backgroundColor: COLORS.primaryMist, alignItems: 'center', justifyContent: 'center' },

  periods: { flexDirection: 'row', gap: 8, paddingHorizontal: SPACING.md, marginBottom: 14 },
  period: { paddingHorizontal: 14, paddingVertical: 7, borderRadius: RADIUS.full, backgroundColor: 'rgba(188,38,73,0.09)' },
  periodOn: { backgroundColor: COLORS.primary },
  periodText: { fontFamily: FONTS.semibold, fontSize: 13, color: COLORS.primary },
  rings: { flexDirection: 'row', justifyContent: 'space-around', paddingHorizontal: SPACING.sm },
  ring: { alignItems: 'center', gap: 8, flex: 1 },
  ringTitle: { fontFamily: FONTS.bold, fontSize: 14 },
  ringCaption: { fontFamily: FONTS.regular, fontSize: 11.5, color: SOFT.muted },
  summary: { flexDirection: 'row', gap: 10, alignItems: 'flex-start', backgroundColor: COLORS.primaryMist, borderRadius: 18, padding: 14, marginHorizontal: SPACING.md, marginTop: 16 },
  summaryText: { flex: 1, fontFamily: FONTS.medium, fontSize: 13.5, lineHeight: 20, color: COLORS.primaryInk },

  tiles: { flexDirection: 'row', flexWrap: 'wrap', gap: 10, paddingHorizontal: SPACING.md },
  tile: { backgroundColor: COLORS.white, borderRadius: 20, padding: 12, gap: 4, ...SOFT_SHADOW },
  tileIcon: { width: 32, height: 32, borderRadius: 11, alignItems: 'center', justifyContent: 'center', marginBottom: 2 },
  tileValue: { fontFamily: FONTS.bold, fontSize: 17, color: COLORS.black },
  tileLabel: { fontFamily: FONTS.regular, fontSize: 11.5, color: SOFT.muted },

  bars: { flexDirection: 'row', justifyContent: 'space-between', marginHorizontal: SPACING.md, backgroundColor: COLORS.white, borderRadius: 20, paddingHorizontal: 14, paddingTop: 16, paddingBottom: 10, ...SOFT_SHADOW },
  barCol: { alignItems: 'center', gap: 4, flex: 1 },
  barTrack: { width: 20, height: 90, borderRadius: 10, backgroundColor: COLORS.gray100, justifyContent: 'flex-end', overflow: 'hidden' },
  barPart: { width: '100%' },
  barLabel: { fontFamily: FONTS.semibold, fontSize: 11.5, color: COLORS.gray900 },
  barValue: { fontFamily: FONTS.regular, fontSize: 10.5, color: SOFT.muted },
  legend: { flexDirection: 'row', gap: 16, justifyContent: 'center', marginTop: 10 },
  legendItem: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  dot: { width: 9, height: 9, borderRadius: 5 },

  note: { width: 170, borderRadius: 18, padding: 12, gap: 4, ...SOFT_SHADOW },
  noteDate: { fontFamily: FONTS.regular, fontSize: 11, color: SOFT.muted },
  noteTitle: { fontFamily: FONTS.bold, fontSize: 14.5, color: COLORS.black },
  noteBody: { fontFamily: FONTS.regular, fontSize: 12.5, lineHeight: 17, color: COLORS.gray900 },

  admin: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.lg + 2, padding: 14 },
  adminIcon: { width: 44, height: 44, borderRadius: 15, backgroundColor: COLORS.primary, alignItems: 'center', justifyContent: 'center' },
  adminTitle: { fontFamily: FONTS.bold, fontSize: 15.5, color: COLORS.primaryInk },

  menu: { backgroundColor: COLORS.white, borderRadius: 24, paddingHorizontal: 14, paddingVertical: 4, ...SOFT_SHADOW },
  menuRow: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 12 },
  menuBorder: { borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: 'rgba(120,80,100,0.18)' },
  menuIcon: { width: 34, height: 34, borderRadius: 11, backgroundColor: COLORS.primaryMist, alignItems: 'center', justifyContent: 'center' },
  version: { textAlign: 'center', marginTop: SPACING.md },
});
