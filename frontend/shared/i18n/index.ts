import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';

import type { Locale } from '../types';
import ru from './ru.json';
import uz from './uz.json';

// `Locale` yagona joyda — `types/index.ts` da — e'lon qilinadi, shunda
// backend Resource turlari bilan bitta manbadan yuriladi.
export const SUPPORTED_LOCALES = ['uz', 'ru'] as const satisfies readonly Locale[];
export type { Locale };

export const DEFAULT_LOCALE: Locale = 'uz';

const STORAGE_KEY = 'locale';

function isLocale(value: unknown): value is Locale {
  return typeof value === 'string' && (SUPPORTED_LOCALES as readonly string[]).includes(value);
}

/**
 * Til aniqlash tartibi — docs/02-I18N-RU-UZ.md §2:
 *   URL param (?lang=) → localStorage → user.locale → restaurant.default_locale → uz
 *
 * Oxirgi ikki qadam serverdan keladi, shuning uchun ular `userLocale`
 * argumenti orqali beriladi (login qilgandan keyin qayta chaqiriladi).
 */
export function detectLocale(userLocale?: string | null): Locale {
  const fromUrl = new URLSearchParams(window.location.search).get('lang');
  if (isLocale(fromUrl)) return fromUrl;

  try {
    const stored = window.localStorage.getItem(STORAGE_KEY);
    if (isLocale(stored)) return stored;
  } catch {
    // Private mode / cookie bloklangan brauzer — jim davom etamiz.
  }

  if (isLocale(userLocale)) return userLocale;

  return DEFAULT_LOCALE;
}

export function setLocale(locale: Locale): void {
  void i18n.changeLanguage(locale);

  try {
    window.localStorage.setItem(STORAGE_KEY, locale);
  } catch {
    // localStorage mavjud bo'lmasa til faqat shu sahifa uchun o'zgaradi.
  }
}

export function getLocale(): Locale {
  return isLocale(i18n.language) ? i18n.language : DEFAULT_LOCALE;
}

export function initI18n(userLocale?: string | null) {
  if (!i18n.isInitialized) {
    void i18n.use(initReactI18next).init({
      resources: {
        uz: { translation: uz },
        ru: { translation: ru },
      },
      lng: detectLocale(userLocale),
      fallbackLng: DEFAULT_LOCALE,
      interpolation: { escapeValue: false },
      returnNull: false,
    });
  }

  return i18n;
}

export { i18n, ru, uz };
