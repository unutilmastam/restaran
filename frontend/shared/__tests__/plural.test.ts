import { beforeEach, describe, expect, it } from 'vitest';

import { initI18n, pluralCandidates, setLocale, translate } from '../i18n';

/**
 * Ko'plik: rus tilida 3 ta shakl (1 / 2-4 / 5+), o'zbekda 1 ta.
 * Qoidalar `Intl.PluralRules` dan keladi — qo'lda yozilmagan.
 */
describe('plural', () => {
  beforeEach(() => {
    window.localStorage.clear();
    initI18n('uz');
  });

  it('picks the right russian form for 1 / 2-4 / 5+', () => {
    setLocale('ru');

    expect(translate('subscription.days_left', { count: 1 })).toBe('Остался 1 день');
    expect(translate('subscription.days_left', { count: 3 })).toBe('Осталось 3 дня');
    expect(translate('subscription.days_left', { count: 5 })).toBe('Осталось 5 дней');
  });

  it('handles the russian teens and the 21/22 exceptions', () => {
    setLocale('ru');

    // 11-14 — `many`, garchi oxirgi raqam 1-4 bo'lsa ham.
    expect(translate('subscription.days_left', { count: 11 })).toBe('Осталось 11 дней');
    expect(translate('subscription.days_left', { count: 12 })).toBe('Осталось 12 дней');
    // 21 — yana `one`.
    expect(translate('subscription.days_left', { count: 21 })).toBe('Остался 21 день');
    expect(translate('subscription.days_left', { count: 22 })).toBe('Осталось 22 дня');
    expect(translate('subscription.days_left', { count: 25 })).toBe('Осталось 25 дней');
  });

  it('uses a single form in uzbek', () => {
    setLocale('uz');

    for (const count of [1, 3, 5, 11, 21]) {
      expect(translate('subscription.days_left', { count })).toBe(`${count} kun qoldi`);
    }
  });

  it('falls back through _other to the base key', () => {
    expect(pluralCandidates('a.b', 1, 'ru')).toEqual(['a.b_one', 'a.b_other', 'a.b']);
    expect(pluralCandidates('a.b', 5, 'uz')).toEqual(['a.b_other', 'a.b']);
  });

  it('leaves keys without a count untouched', () => {
    setLocale('uz');

    expect(translate('common.total')).toBe('Jami');
  });

  it('interpolates other variables too', () => {
    setLocale('uz');

    expect(translate('waiter.greeting', { name: 'Hasan' })).toBe('Salom, Hasan');
  });
});
