// What the app does right after a payment has been started (a gift or an event ticket): go to the waiting
// screen for that reference. Mobile money shows "approve on your phone"; card payments carry the secure
// checkout address, which the waiting screen opens in the in-app browser.
import { router } from 'expo-router';

/** `response` is the body of POST …/give or POST /rsvps/{id}/pay: { data: donation, next: { action, redirect_url } }. */
export function openPayment(response) {
  const reference = response?.data?.reference;
  if (!reference) return;
  router.replace({ pathname: '/pay/[reference]', params: { reference, redirect: response.next?.action === 'redirect' ? response.next.redirect_url : '' } });
}

/** A phone number the way the API wants it: digits only, local 07… or +2567…. */
export function cleanPhone(phone = '') {
  return String(phone).replace(/[\s-]/g, '');
}
