import { describe, expect, it } from 'vitest';

import { SUPPORTED_LOCALES, type Locale } from '../i18n';
import ru from '../i18n/ru.json';
import uz from '../i18n/uz.json';

/**
 * docs/02-I18N-RU-UZ.md §10 — `ru.json` va `uz.json` kalitlari 1:1 mos
 * bo'lishi SHART.
 *
 * Ko'plik shakllari bundan istisno: rus tilida 4 ta shakl (`_one`,
 * `_few`, `_many`, `_other`), o'zbekda 1 ta (`_other`). Shuning uchun
 * taqqoslash BAZAVIY kalit bo'yicha, shakllar esa har til uchun
 * `Intl.PluralRules` talab qilganicha alohida tekshiriladi.
 */
type Tree = { [key: string]: string | Tree };

const PLURAL_SUFFIXES = ['zero', 'one', 'two', 'few', 'many', 'other'];

function flatten(tree: Tree, prefix = ''): [string, string][] {
  return Object.entries(tree).flatMap(([key, value]) =>
    typeof value === 'object'
      ? flatten(value, `${prefix}${key}.`)
      : ([[`${prefix}${key}`, value]] as [string, string][]),
  );
}

/** `subscription.days_left_few` → `subscription.days_left` */
function baseKey(key: string): string {
  const match = /^(.*)_([a-z]+)$/.exec(key);

  return match !== null && PLURAL_SUFFIXES.includes(match[2]) ? match[1] : key;
}

const locales: Record<Locale, Tree> = { uz: uz as Tree, ru: ru as Tree };
const entries = {
  uz: flatten(uz as Tree),
  ru: flatten(ru as Tree),
} satisfies Record<Locale, [string, string][]>;

describe('i18n ru/uz parity', () => {
  it('has identical base keys in both locales', () => {
    const base = (locale: Locale) => [...new Set(entries[locale].map(([key]) => baseKey(key)))].sort();

    expect(base('uz')).toEqual(base('ru'));
  });

  it('has no empty translation', () => {
    for (const locale of SUPPORTED_LOCALES) {
      for (const [key, value] of entries[locale]) {
        expect(value.trim(), `${locale}: "${key}" bo'sh`).not.toBe('');
      }
    }
  });

  it('provides every plural form each locale grammatically needs', () => {
    // Bazaviy kalitlar — birortasida ko'plik shakli bo'lganlar.
    const pluralised = new Set(
      SUPPORTED_LOCALES.flatMap((locale) =>
        entries[locale].filter(([key]) => baseKey(key) !== key).map(([key]) => baseKey(key)),
      ),
    );

    expect(pluralised.size).toBeGreaterThan(0);

    for (const locale of SUPPORTED_LOCALES) {
      const categories = new Intl.PluralRules(locale).resolvedOptions().pluralCategories;
      const present = new Set(entries[locale].map(([key]) => key));

      for (const key of pluralised) {
        // `_other` — fallback zanjirining oxirgi bo'g'ini, har doim kerak.
        expect(present.has(`${key}_other`), `${locale}: "${key}_other" yetishmayapti`).toBe(true);

        // Til bitta shakl bilan kifoyalanishi mumkin (o'zbekchada
        // "1 kun" va "5 kun" bir xil) — u holda faqat `_other` bo'ladi.
        // Ammo boshqa shakl KIRITILGAN bo'lsa, HAMMASI bo'lishi shart:
        // ruschada `_one` va `_few` bor, `_many` unutilgan — eng ko'p
        // uchraydigan xato, test aynan shuni ushlaydi.
        const usesCategories = categories.some(
          (category) => category !== 'other' && present.has(`${key}_${category}`),
        );

        if (!usesCategories) continue;

        for (const category of categories) {
          expect(
            present.has(`${key}_${category}`),
            `${locale}: "${key}_${category}" yetishmayapti`,
          ).toBe(true);
        }
      }
    }
  });

  it('never mixes a plain key with its plural forms', () => {
    // `days_left` VA `days_left_one` birga bo'lsa qaysi biri ishlatilishi
    // noaniq bo'ladi — faqat bittasi bo'lsin.
    for (const locale of SUPPORTED_LOCALES) {
      const present = new Set(entries[locale].map(([key]) => key));

      for (const key of present) {
        if (baseKey(key) !== key) {
          expect(present.has(baseKey(key)), `${locale}: "${baseKey(key)}" ortiqcha`).toBe(false);
        }
      }
    }
  });

  it('covers every error_code from docs/02 §6', () => {
    const required = [
      'PRODUCT_UNAVAILABLE',
      'SESSION_NOT_FOUND',
      'ORDER_NOT_DELIVERED',
      'SESSION_WAITING_PAYMENT',
      'NO_FREE_WAITER',
      'NETWORK_ERROR',
      'ORDER_DUPLICATE',
      'INVALID_TABLE',
      'INVALID_STATUS_TRANSITION',
      'PRICE_CHANGED',
    ];

    for (const locale of SUPPORTED_LOCALES) {
      const present = new Set(entries[locale].map(([key]) => key));

      for (const code of required) {
        expect(present.has(`errors.${code}`), `${locale}: errors.${code}`).toBe(true);
      }
    }
  });

  it('keeps interpolation variables consistent across locales', () => {
    const vars = (locale: Locale, key: string) =>
      [...(entries[locale].find(([k]) => k === key)?.[1].matchAll(/\{\{(\w+)\}\}/g) ?? [])]
        .map((match) => match[1])
        .sort();

    for (const [key] of entries.uz) {
      const uzVars = vars('uz', key);
      if (uzVars.length === 0) continue;

      // Bir xil bazaviy kalitning ru shakllari ham shu o'zgaruvchilarni
      // ishlatishi kerak, aks holda matn to'liq chiqmaydi.
      const ruKeys = entries.ru.filter(([k]) => baseKey(k) === baseKey(key)).map(([k]) => k);

      for (const ruKey of ruKeys) {
        expect(vars('ru', ruKey), `ru: "${ruKey}"`).toEqual(uzVars);
      }
    }
  });

  it('exposes both locales through SUPPORTED_LOCALES', () => {
    expect([...SUPPORTED_LOCALES].sort()).toEqual(Object.keys(locales).sort());
  });
});
