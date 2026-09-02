import { parseRoute } from './route';

/**
 * Customer session tokeni — stol bo'yicha alohida kalitda.
 *
 * Bir telefon turli stollarda ishlatilishi mumkin (masalan afitsant
 * ko'rsatib bergan), shuning uchun tokenlar aralashmasligi kerak —
 * cart bilan bir xil printsip.
 */
const PREFIX = 'customer_token';

export function tokenKey(nfcToken: string): string {
  return `${PREFIX}:${nfcToken}`;
}

export function readCustomerToken(nfcToken?: string): string | null {
  const token = nfcToken ?? parseRoute()?.nfcToken;

  if (token === undefined || token === null) return null;

  try {
    return window.localStorage.getItem(tokenKey(token));
  } catch {
    return null;
  }
}

export function writeCustomerToken(nfcToken: string, value: string): void {
  try {
    window.localStorage.setItem(tokenKey(nfcToken), value);
  } catch {
    // Storage bloklangan — token faqat shu sahifa uchun qoladi.
  }
}
