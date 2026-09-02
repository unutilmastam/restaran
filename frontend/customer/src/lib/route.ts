/**
 * Minimal route parser — `react-router-dom` ISHLATILMAYDI.
 *
 * SABAB: router ~17 KB gzip, bizga esa bitta kirish nuqtasi kerak.
 * Mijoz NFC orqali FAQAT bitta URL bilan keladi va undan keyin ekranlar
 * holat (state) orqali almashadi — tarix va deep-link kerak emas.
 *
 * docs/06-SAAS.md §7:
 *   /r/{slug}/t/{nfc_token}   — asosiy shakl
 *   /t/{nfc_token}            — qisqa shakl (NFC tagga yoziladi)
 */
export interface TableRoute {
  slug: string | null;
  nfcToken: string;
}

export function parseRoute(pathname: string = window.location.pathname): TableRoute | null {
  const parts = pathname.split('/').filter(Boolean);

  // /r/{slug}/t/{token}
  if (parts.length === 4 && parts[0] === 'r' && parts[2] === 't') {
    return { slug: parts[1], nfcToken: parts[3] };
  }

  // /t/{token}
  if (parts.length === 2 && parts[0] === 't') {
    return { slug: null, nfcToken: parts[1] };
  }

  return null;
}

/** API yo'li — slug bo'lsa u ham yuboriladi, backend mosligini tekshiradi. */
export function tablePath(route: TableRoute, suffix = ''): string {
  return route.slug
    ? `/r/${route.slug}/t/${route.nfcToken}${suffix}`
    : `/t/${route.nfcToken}${suffix}`;
}
