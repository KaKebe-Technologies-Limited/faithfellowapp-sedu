// Turns what a notification points at (a website address such as /events/12, or a social kind such as a
// connection request) into the matching screen inside the app, so tapping a notification never opens the website.
import { router } from 'expo-router';

const ROUTES = [
  [/^\/events\/(\d+)/, (id) => ({ pathname: '/event/[id]', params: { id } })],
  [/^\/sermons\/(\d+)/, (id) => ({ pathname: '/sermon/[id]', params: { id } })],
  [/^\/devotionals\/(\d+)/, (id) => ({ pathname: '/devotional/[id]', params: { id } })],
  [/^\/announcements\/(\d+)/, (id) => ({ pathname: '/announcement/[id]', params: { id } })],
  [/^\/prayer\/(\d+)/, (id) => ({ pathname: '/prayer/[id]', params: { id } })],
  [/^\/live\/(\d+)/, (id) => ({ pathname: '/live/[id]', params: { id } })],
  [/^\/groups\/(\d+)/, (id) => ({ pathname: '/group/[id]', params: { id } })],
  [/^\/reading-circles\/(\d+)/, (id) => ({ pathname: '/circle/[id]', params: { id } })],
  [/^\/reading-plans\/(\d+)/, (id) => ({ pathname: '/plan/[id]', params: { id } })],
  [/^\/campaigns/, () => '/campaigns'],
  [/^\/games/, () => '/games'],
  [/^\/giving/, () => '/(tabs)/give'],
  [/^\/prayer/, () => '/prayer'],
  [/^\/devotionals/, () => '/devotionals'],
  [/^\/events/, () => '/events'],
  [/^\/bible/, () => '/(tabs)/bible'],
];

/** Where a notification should go inside the app, or null when it has no place to open. */
export function routeFor(notification) {
  if (notification.kind === 'connection_request') return '/connections';
  if (notification.kind === 'connection_accepted' && notification.ref) return { pathname: '/person/[id]', params: { id: String(notification.ref) } };

  let path = '';
  try {
    path = new URL(notification.url).pathname;
  } catch {
    path = String(notification.url ?? '');
  }
  for (const [pattern, build] of ROUTES) {
    const match = path.match(pattern);
    if (match) return build(match[1]);
  }
  return null;
}

export function openNotification(notification) {
  const target = routeFor(notification);
  if (target) router.push(target);
}
