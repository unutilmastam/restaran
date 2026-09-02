import { describe, expect, it } from 'vitest';

import ru from '../i18n/ru.json';
import uz from '../i18n/uz.json';

/**
 * docs/02-I18N-RU-UZ.md §10 — `ru.json` va `uz.json` kalitlari 1:1 mos
 * bo'lishi SHART. Bitta tilga kalit qo'shib ikkinchisini unutish eng
 * ko'p uchraydigan i18n xatosi, shuning uchun test PHASE 1 da yoziladi.
 */
type Tree = { [key: string]: string | Tree };

function flatten(tree: Tree, prefix = ''): string[] {
  return Object.entries(tree).flatMap(([key, value]) =>
    typeof value === 'object' ? flatten(value, `${prefix}${key}.`) : [`${prefix}${key}`],
  );
}

function values(tree: Tree, prefix = ''): [string, string][] {
  return Object.entries(tree).flatMap(([key, value]) =>
    typeof value === 'object'
      ? values(value, `${prefix}${key}.`)
      : ([[`${prefix}${key}`, value]] as [string, string][]),
  );
}

describe('i18n ru/uz parity', () => {
  const ruKeys = flatten(ru as Tree).sort();
  const uzKeys = flatten(uz as Tree).sort();

  it('has identical keys in both locales', () => {
    expect(uzKeys).toEqual(ruKeys);
  });

  it('reports which keys are missing where', () => {
    expect(ruKeys.filter((key) => !uzKeys.includes(key))).toEqual([]);
    expect(uzKeys.filter((key) => !ruKeys.includes(key))).toEqual([]);
  });

  it('has no empty translation', () => {
    for (const [locale, tree] of [
      ['ru', ru],
      ['uz', uz],
    ] as const) {
      for (const [key, value] of values(tree as Tree)) {
        expect(value.trim(), `${locale}: "${key}" bo'sh`).not.toBe('');
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

    for (const code of required) {
      expect(uzKeys).toContain(`errors.${code}`);
      expect(ruKeys).toContain(`errors.${code}`);
    }
  });
});
