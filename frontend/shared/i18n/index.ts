import { useCallback, useSyncExternalStore } from 'react';

import type { Locale } from '../types';
import ru from './ru.json';
import uz from './uz.json';

/**
 * Bog'liqliksiz i18n — `i18next` + `react-i18next` o'rniga.
 *
 * SABAB: ikkalasi birgalikda ~18 KB gzip. Customer PWA NFC orqali mobil
 * internetda ochiladi, shuning uchun bundle byudjeti qattiq (≤100 KB gz).
 * Bizga kerak bo'lgani — nested kalit va `{{var}}` interpolyatsiya, ular
 * ~50 qatorga sig'adi.
 *
 * ⚠️ Ko'plik shakllari (rus tilida "3 дня / 5 дней") HALI YO'Q. Kerak
 * bo'lganda `plural()` helperi qo'shiladi — hozircha bunday kalit yo'q
 * va i18n parity testi buni nazorat qiladi.
 */

export const SUPPORTED_LOCALES = ['uz', 'ru'] as const satisfies readonly Locale[];
export const DEFAULT_LOCALE: Locale = 'uz';

const STORAGE_KEY = 'locale';
const RESOURCES: Record<Locale, unknown> = { uz, ru };

let current: Locale = DEFAULT_LOCALE;
const listeners = new Set<() => void>();

function isLocale(value: unknown): value is Locale {
  return typeof value === 'string' && (SUPPORTED_LOCALES as readonly string[]).includes(value);
}

/**
 * Til aniqlash tartibi — docs/02-I18N-RU-UZ.md §2:
 *   URL param (?lang=) → localStorage → user.locale → restaurant.default_locale → uz
 */
export function detectLocale(fallback?: string | null): Locale {
  const fromUrl = new URLSearchParams(window.location.search).get('lang');
  if (isLocale(fromUrl)) return fromUrl;

  try {
    const stored = window.localStorage.getItem(STORAGE_KEY);
    if (isLocale(stored)) return stored;
  } catch {
    // Private mode / bloklangan storage — jim davom etamiz.
  }

  return isLocale(fallback) ? fallback : DEFAULT_LOCALE;
}

export function getLocale(): Locale {
  return current;
}

export function setLocale(locale: Locale): void {
  if (!isLocale(locale) || locale === current) return;

  current = locale;
  document.documentElement.lang = locale;

  try {
    window.localStorage.setItem(STORAGE_KEY, locale);
  } catch {
    // Saqlanmasa til faqat shu sessiya uchun o'zgaradi.
  }

  listeners.forEach((listener) => listener());
}

export function initI18n(fallback?: string | null): void {
  current = detectLocale(fallback);
  document.documentElement.lang = current;
}

function lookup(locale: Locale, key: string): string | undefined {
  let node: unknown = RESOURCES[locale];

  for (const part of key.split('.')) {
    if (typeof node !== 'object' || node === null) return undefined;
    node = (node as Record<string, unknown>)[part];
  }

  return typeof node === 'string' ? node : undefined;
}

export function translate(key: string, vars?: Record<string, string | number>): string {
  // Kalit topilmasa kalitning O'ZI qaytadi — i18n bo'shlig'i yashirilmaydi
  // va parity testi uni ushlaydi (docs/02 §1).
  const template = lookup(current, key) ?? lookup(DEFAULT_LOCALE, key) ?? key;

  if (vars === undefined) return template;

  return template.replace(/\{\{(\w+)\}\}/g, (match, name: string) =>
    name in vars ? String(vars[name]) : match,
  );
}

function subscribe(listener: () => void): () => void {
  listeners.add(listener);

  return () => listeners.delete(listener);
}

export interface Translation {
  t: (key: string, vars?: Record<string, string | number>) => string;
  locale: Locale;
  setLocale: (locale: Locale) => void;
}

export function useTranslation(): Translation {
  const locale = useSyncExternalStore(subscribe, getLocale, () => DEFAULT_LOCALE);

  const t = useCallback(
    (key: string, vars?: Record<string, string | number>) => {
      void locale; // til o'zgarganda qayta hisoblanishi uchun

      return translate(key, vars);
    },
    [locale],
  );

  return { t, locale, setLocale };
}

export type { Locale };
export { ru, uz };
