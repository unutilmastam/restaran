import { describe, expect, it } from 'vitest';

import { formatDate, formatDateTime, formatMoney, formatTime } from '../format';

/** docs/02-I18N-RU-UZ.md §9 — formatlash qoidalari. */
describe('format', () => {
  it('formats money with a space separator in both locales', () => {
    expect(formatMoney(190000, 'uz')).toBe("190 000 so'm");
    expect(formatMoney(190000, 'ru')).toBe('190 000 сум');
  });

  it('accepts the decimal strings the API returns', () => {
    expect(formatMoney('310000.00', 'uz')).toBe("310 000 so'm");
  });

  it('handles small and invalid amounts', () => {
    expect(formatMoney(0, 'ru')).toBe('0 сум');
    expect(formatMoney(999, 'uz')).toBe("999 so'm");
    expect(formatMoney('not-a-number', 'uz')).toBe("0 so'm");
  });

  it('formats date as dd.mm.yyyy and time as 24h', () => {
    const moment = new Date(2026, 8, 2, 14, 25);

    expect(formatDate(moment)).toBe('02.09.2026');
    expect(formatTime(moment)).toBe('14:25');
    expect(formatDateTime(moment)).toBe('02.09.2026 14:25');
  });
});
