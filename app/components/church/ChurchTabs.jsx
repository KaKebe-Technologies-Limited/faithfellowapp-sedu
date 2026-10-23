// What each tab of a church's page shows. Everything is read from the one GET /churches/{slug}/page answer (the sermons tab also pages through every sermon),
// and everything plays or opens in place: a sermon or a song starts in the app's player right where you press it.
import { ActivityIndicator, Linking, Pressable, Share, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { Image } from 'expo-image';
import { router } from 'expo-router';
import { COLORS, FONTS, SPACING } from '../../constants/theme';
import { SOFT, SOFT_SHADOW } from '../../constants/soft';
import { mediaUrl } from '../../lib/api';
import { usePaged } from '../../lib/usePaged';
import AppText from '../AppText';
import Button from '../Button';
import { CampaignCard, ChannelCard, DevotionalCard, EmptyNote, EventRow, LiveCard, NoticeCard, PrayerCard, Section, SermonFeature, SermonRow, TrackRow, UpdateCard } from './Blocks';

const SOCIAL_ICONS = { facebook: 'logo-facebook', instagram: 'logo-instagram', youtube: 'logo-youtube', twitter: 'logo-twitter', x: 'logo-twitter', tiktok: 'logo-tiktok', whatsapp: 'logo-whatsapp', website: 'globe-outline' };
const LANGUAGES = { en: 'English', lg: 'Luganda', luo: 'Luo', sw: 'Swahili', ach: 'Acholi', nyn: 'Runyankore', teo: 'Ateso', rny: 'Runyoro', lgg: 'Lugbara' };
const languageName = (code) => LANGUAGES[String(code).toLowerCase()] ?? String(code).toUpperCase();
const open = (url) => url && Linking.openURL(/^https?:/i.test(url) ? url : mediaUrl(url));

export function HomeTab({ info, page, go, onFollow }) {
  const urgent = page.announcements.filter((a) => a.urgent);
  const latest = page.sermons[0];
  const nextEvent = page.events[0];
  const campaign = page.campaigns[0];
  const updates = page.updates.slice(0, 2);
  const listen = page.radio ?? page.channel; // the church's songs radio leads; its podcast channel is next
  const nothing = !latest && !nextEvent && !campaign && !updates.length && !page.devotional && !listen && !page.live && !page.next_live;

  return (
    <View>
      {page.live || page.next_live ? <Section title={page.live ? 'Live now' : 'Next live service'}><LiveCard live={page.live} next={page.next_live} /></Section> : null}
      {urgent.length ? urgent.map((n) => <NoticeCard key={n.id} notice={n} />) : null}
      {latest ? (
        <Section title="Sermons" action={page.sermons.length > 1 ? 'See all' : null} onAction={() => go('sermons')}>
          <SermonFeature sermon={latest} list={page.sermons} />
        </Section>
      ) : null}
      {listen ? (
        <Section title="Listen" action={listen.can_listen ? 'See all' : null} onAction={() => go('listen')}>
          <ChannelCard channel={listen} church={info} following={info.is_subscribed} onFollow={onFollow} />
        </Section>
      ) : null}
      {nextEvent ? (
        <Section title="Coming up" action={page.events.length > 1 ? 'See all' : null} onAction={() => go('events')}>
          {page.events.slice(0, 2).map((e) => <EventRow key={e.id} event={e} />)}
        </Section>
      ) : null}
      {page.devotional ? (
        <Section title="Today's devotional">
          <DevotionalCard devotional={page.devotional} />
        </Section>
      ) : null}
      {campaign ? (
        <Section title="Giving" action="See all" onAction={() => go('give')}>
          <CampaignCard campaign={campaign} />
        </Section>
      ) : null}
      {updates.length ? (
        <Section title="Latest news" action={page.updates.length > 2 ? 'See all' : null} onAction={() => go('updates')}>
          {updates.map((u) => <UpdateCard key={u.id} update={u} church={info} />)}
        </Section>
      ) : null}
      {info.is_subscribed ? <PrayerCard count={page.prayer.open_count} /> : null}
      {nothing ? <EmptyNote icon="business-outline" title="This church is just getting started" text={`Sermons, events, songs and news will appear here as ${info.name} shares them. Follow to hear first.`} /> : null}
    </View>
  );
}

/** Every sermon of the church, newest first, with the audio playing right here. */
export function SermonsTab({ info }) {
  const list = usePaged('/sermons', { church: info.id });

  if (list.loading) return <ActivityIndicator color={COLORS.primary} style={{ marginTop: 40 }} />;
  if (list.error && !list.items.length) return <EmptyNote icon="cloud-offline-outline" title="The sermons could not load" text={list.error} />;
  if (!list.items.length) return <EmptyNote icon="mic-outline" title="No sermons yet" text="When the church shares a sermon you can listen to it here, even with the screen locked." />;

  return (
    <View>
      <Section title="All sermons">
        {list.items.map((s) => <SermonRow key={s.id} sermon={s} list={list.items} />)}
        {list.hasMore ? <Button title={list.loadingMore ? 'Loading…' : 'Show more sermons'} variant="outline" loading={list.loadingMore} onPress={list.loadMore} /> : null}
      </Section>
    </View>
  );
}

/** The church's own radio (songs) and podcast channel (teaching), and each song or episode in them. */
export function ListenTab({ info, page, onFollow }) {
  const channels = [
    page.radio ? { channel: page.radio, title: 'Songs' } : null,
    page.channel ? { channel: page.channel, title: 'Teaching and episodes' } : null,
  ].filter(Boolean);
  if (!channels.length) return <EmptyNote icon="radio-outline" title="No audio yet" text="When the church adds songs or teaching to its channel, you can listen here." />;

  return (
    <View>
      {channels.map(({ channel, title }, i) => (
        <View key={channel.slug} style={i ? { marginTop: SPACING.xl } : null}>
          <ChannelCard channel={channel} church={info} following={info.is_subscribed} onFollow={onFollow} />
          <Section title={title} style={{ marginTop: SPACING.lg }}>
            {channel.can_listen ? (
              channel.tracks.map((t) => <TrackRow key={t.id} track={t} list={channel.tracks} church={info} />)
            ) : (
              <EmptyNote icon="lock-closed-outline" title={`Follow ${info.name} to listen`} text="The church's songs and teaching are for the people who follow it." />
            )}
          </Section>
        </View>
      ))}
    </View>
  );
}

export function EventsTab({ page }) {
  if (!page.events.length) return <EmptyNote icon="calendar-outline" title="No events coming up" text="Services, conferences and gatherings will be listed here." />;

  return <Section title="Coming up">{page.events.map((e) => <EventRow key={e.id} event={e} />)}</Section>;
}

export function UpdatesTab({ info, page }) {
  const empty = !page.updates.length && !page.announcements.length && !page.devotional;
  if (empty) return <EmptyNote icon="newspaper-outline" title="No news yet" text="Updates, notices and devotionals from the church will appear here." />;

  return (
    <View>
      {page.announcements.length ? <Section title="Notices">{page.announcements.map((n) => <NoticeCard key={n.id} notice={n} />)}</Section> : null}
      {page.devotional ? <Section title="Today's devotional"><DevotionalCard devotional={page.devotional} /></Section> : null}
      {page.updates.length ? <Section title="News and photos">{page.updates.map((u) => <UpdateCard key={u.id} update={u} church={info} />)}</Section> : null}
    </View>
  );
}

export function GiveTab({ info, page }) {
  return (
    <View>
      <Section title={`Give to ${info.name}`}>
        <View style={styles.giveCard}>
          <View style={styles.giveIcon}>
            <Ionicons name="heart" size={26} color="#fff" />
          </View>
          <AppText style={styles.giveTitle}>Tithes, offerings and gifts</AppText>
          <AppText style={styles.giveText}>Give safely with mobile money. You get a receipt, and the church sees the gift at once.</AppText>
          <Button title="Give now" onPress={() => router.push({ pathname: '/give/[slug]', params: { slug: info.slug } })} style={{ marginTop: SPACING.md }} />
        </View>
      </Section>
      {page.campaigns.length ? <Section title="Giving campaigns">{page.campaigns.map((c) => <CampaignCard key={c.id} campaign={c} />)}</Section> : null}
    </View>
  );
}

function Row({ icon, label, value, onPress }) {
  return (
    <Pressable onPress={onPress} disabled={!onPress} style={styles.infoRow} accessibilityRole={onPress ? 'link' : undefined}>
      <View style={styles.infoIcon}>
        <Ionicons name={icon} size={18} color={COLORS.primary} />
      </View>
      <View style={{ flex: 1 }}>
        <AppText style={styles.infoLabel}>{label}</AppText>
        <AppText style={styles.infoValue} numberOfLines={3}>
          {value}
        </AppText>
      </View>
      {onPress ? <Ionicons name="chevron-forward" size={16} color={COLORS.gray500} /> : null}
    </Pressable>
  );
}

export function AboutTab({ info, page }) {
  const loc = info.location ?? {};
  const maps = loc.lat && loc.lng ? `https://www.google.com/maps/search/?api=1&query=${loc.lat},${loc.lng}` : loc.address ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(loc.address)}` : null;
  const languages = (info.languages ?? []).map(languageName).join(', ');
  const where = [...new Set([loc.city, loc.district].filter(Boolean))].join(', ');

  return (
    <View>
      {info.description ? (
        <Section title={`About ${info.name}`}>
          <View style={styles.box}>
            <AppText style={styles.about}>{info.description}</AppText>
          </View>
        </Section>
      ) : null}

      <Section title="Details">
        <View style={styles.box}>
          {info.pastor ? <Row icon="person-outline" label="Pastor" value={info.pastor} /> : null}
          {info.denomination ? <Row icon="bookmark-outline" label="Denomination" value={info.denomination} /> : null}
          {languages ? <Row icon="chatbubbles-outline" label="Services in" value={languages} /> : null}
          {where ? <Row icon="location-outline" label="Where" value={where} onPress={maps ? () => open(maps) : undefined} /> : null}
        </View>
      </Section>

      {info.service_times?.length ? (
        <Section title="Service times">
          <View style={styles.box}>
            {info.service_times.map((s, i) => (
              <View key={`${s.name}-${i}`} style={[styles.timeRow, i > 0 && styles.timeBorder]}>
                <View style={{ flex: 1 }}>
                  <AppText style={styles.timeName}>{s.name}</AppText>
                  <AppText style={styles.infoLabel}>{[s.day, s.language && languageName(s.language), s.location].filter(Boolean).join(' · ')}</AppText>
                </View>
                <AppText style={styles.timeAt}>{s.time}</AppText>
              </View>
            ))}
          </View>
        </Section>
      ) : null}

      <Section title="Contact">
        <View style={styles.box}>
          {info.contact ? (
            <>
              {info.contact.phone ? <Row icon="call-outline" label="Phone" value={info.contact.phone} onPress={() => Linking.openURL(`tel:${info.contact.phone}`)} /> : null}
              {info.contact.email ? <Row icon="mail-outline" label="Email" value={info.contact.email} onPress={() => Linking.openURL(`mailto:${info.contact.email}`)} /> : null}
              {info.contact.website ? <Row icon="globe-outline" label="Website" value={info.contact.website} onPress={() => open(info.contact.website)} /> : null}
              {loc.address ? <Row icon="navigate-outline" label="Address" value={loc.address} onPress={maps ? () => open(maps) : undefined} /> : null}
            </>
          ) : (
            <View style={styles.locked}>
              <Ionicons name="lock-closed-outline" size={20} color={COLORS.primary} />
              <AppText style={{ flex: 1, fontFamily: FONTS.regular, fontSize: 14, color: COLORS.gray900 }}>Follow {info.name} to see their phone number, email and address.</AppText>
            </View>
          )}
        </View>
      </Section>

      {page.socials.length ? (
        <Section title="Find them online">
          <View style={styles.socials}>
            {page.socials.map((l) => (
              <Pressable key={l.platform + l.url} onPress={() => open(l.url)} style={styles.social} accessibilityRole="link" accessibilityLabel={`Open ${l.platform}`}>
                <Ionicons name={SOCIAL_ICONS[String(l.platform).toLowerCase()] ?? 'link-outline'} size={22} color={COLORS.primary} />
              </Pressable>
            ))}
          </View>
        </Section>
      ) : null}

      {page.gallery.length ? (
        <Section title="Photos">
          <View style={styles.gallery}>
            {page.gallery.map((g, i) => (
              <Image key={`${g.thumb}-${i}`} source={{ uri: mediaUrl(g.thumb || g.url) }} style={styles.galleryPhoto} contentFit="cover" transition={250} />
            ))}
          </View>
        </Section>
      ) : null}

      {info.join_url ? (
        <Pressable onPress={() => Share.share({ message: `Join ${info.name} on FaithFellows: ${info.join_url}` }).catch(() => {})} style={({ pressed }) => [styles.invite, pressed && { opacity: 0.88 }]} accessibilityRole="button" accessibilityLabel={`Invite a friend to ${info.name}`}>
          <Ionicons name="person-add" size={18} color="#fff" />
          <AppText style={styles.inviteText}>Invite a friend to this church</AppText>
        </Pressable>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  giveCard: { alignItems: 'center', backgroundColor: SOFT.glass, borderRadius: 26, borderWidth: 1.5, borderColor: SOFT.glassBorder, padding: 22, ...SOFT_SHADOW },
  giveIcon: { width: 56, height: 56, borderRadius: 28, backgroundColor: '#12704F', alignItems: 'center', justifyContent: 'center', marginBottom: 12 },
  giveTitle: { fontFamily: FONTS.bold, fontSize: 18, color: SOFT.ink, textAlign: 'center' },
  giveText: { fontFamily: FONTS.regular, fontSize: 14, lineHeight: 21, color: SOFT.muted, textAlign: 'center', marginTop: 4 },
  box: { backgroundColor: SOFT.glass, borderRadius: 24, borderWidth: 1.5, borderColor: SOFT.glassBorder, paddingHorizontal: 14, paddingVertical: 6, ...SOFT_SHADOW, shadowOpacity: 0.16 },
  about: { fontFamily: FONTS.regular, fontSize: 14.5, lineHeight: 23, color: '#2B2B2B', paddingVertical: 10 },
  infoRow: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 10 },
  infoIcon: { width: 36, height: 36, borderRadius: 12, backgroundColor: COLORS.primaryMist, alignItems: 'center', justifyContent: 'center' },
  infoLabel: { fontFamily: FONTS.regular, fontSize: 12.5, color: SOFT.muted },
  infoValue: { fontFamily: FONTS.semibold, fontSize: 14.5, color: SOFT.ink },
  timeRow: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 11 },
  timeBorder: { borderTopWidth: 1, borderTopColor: 'rgba(120,80,100,0.12)' },
  timeName: { fontFamily: FONTS.bold, fontSize: 15, color: SOFT.ink },
  timeAt: { fontFamily: FONTS.semibold, fontSize: 13, color: COLORS.primary },
  locked: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.primaryMist, borderRadius: 16, padding: 14, marginVertical: 8 },
  socials: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  social: { width: 50, height: 50, borderRadius: 25, backgroundColor: '#fff', borderWidth: 1.5, borderColor: COLORS.primaryMist, alignItems: 'center', justifyContent: 'center' },
  gallery: { flexDirection: 'row', flexWrap: 'wrap', gap: 6 },
  galleryPhoto: { width: '32.4%', aspectRatio: 1, borderRadius: 14, backgroundColor: COLORS.gray100 },
  invite: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, height: 50, borderRadius: 999, backgroundColor: COLORS.primary },
  inviteText: { fontFamily: FONTS.bold, fontSize: 15, color: '#fff' },
});
