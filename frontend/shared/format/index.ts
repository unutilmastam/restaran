import type { Locale } from '../types';

/**
 * Formatlash qoidalari — docs/02-I18N-RU-UZ.md §9.
 * Pul:   190 000 so'm / 190 000 сум  (bo'sh joy ajratgich)
 * Sana:  02.09.2026
 * Vaqt:  24 soatlik 14:25
 */

const CURRENCY_LABEL: Record<Locale, string> = {
  uz: "so'm",
  ru: 'сум',
};

/** Guruh ajratgich sifatida oddiy bo'sh joy ishlatiladi (NBSP emas — docs §9). */
export function formatMoney(amount: number | string, locale: Locale): string {
  const value = typeof amount === 'string' ? Number(amount) : amount;

  if (!Number.isFinite(value)) return `0 ${CURRENCY_LABEL[locale]}`;

  const rounded = Math.round(value);
  const grouped = String(Math.abs(rounded)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
  const sign = rounded < 0 ? '-' : '';

  return `${sign}${grouped} ${CURRENCY_LABEL[locale]}`;
}

/** Ajratgichsiz son — input maydonlari uchun. */
export function formatNumber(value: number): string {
  return String(Math.round(value)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

function pad(value: number): string {
  return String(value).padStart(2, '0');
}

export function formatDate(input: string | Date): string {
  const date = typeof input === 'string' ? new Date(input) : input;

  return `${pad(date.getDate())}.${pad(date.getMonth() + 1)}.${date.getFullYear()}`;
}

export function formatTime(input: string | Date): string {
  const date = typeof input === 'string' ? new Date(input) : input;

  return `${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export function formatDateTime(input: string | Date): string {
  return `${formatDate(input)} ${formatTime(input)}`;
}
