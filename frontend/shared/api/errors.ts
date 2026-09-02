import type { ApiEnvelope, ErrorCode, Locale } from '../types';

/**
 * Server qaytargan xato. Ikkala tildagi matn ham saqlanadi, chunki API
 * javobida ikkalasi ham keladi (docs/02-I18N-RU-UZ.md §4) — til
 * almashtirilganda so'rovni qayta yuborish shart emas.
 */
export class ApiError extends Error {
  constructor(
    public readonly code: ErrorCode | string,
    public readonly status: number,
    public readonly messageUz: string,
    public readonly messageRu: string,
    public readonly data: unknown = null,
  ) {
    super(`${code} (${status})`);
    this.name = 'ApiError';
  }

  /** UI shu metodni chaqiradi — t() emas, chunki matn serverdan keladi. */
  localized(locale: Locale): string {
    return locale === 'ru' ? this.messageRu : this.messageUz;
  }

  static fromEnvelope(envelope: ApiEnvelope<unknown>, status: number): ApiError {
    return new ApiError(
      envelope.error_code ?? 'SERVER_ERROR',
      status,
      envelope.message_uz ?? '',
      envelope.message_ru ?? '',
      envelope.data,
    );
  }
}

export function isApiError(error: unknown): error is ApiError {
  return error instanceof ApiError;
}

export function hasErrorCode(error: unknown, code: ErrorCode): boolean {
  return isApiError(error) && error.code === code;
}

/**
 * Foydalanuvchiga ko'rsatiladigan matn.
 *
 * ⚠️ Server matni HAR DOIM ham bo'lmaydi: tarmoq uzilganda yoki javob
 * konvertga mos kelmaganda (`NETWORK_ERROR`, `SERVER_ERROR`) `ApiError`
 * bo'sh xabar bilan yaratiladi. Bunday holda matn FRONTEND lug'atidan
 * olinadi, aks holda foydalanuvchi BO'SH qizil quti ko'radi.
 */
export function errorText(
  error: unknown,
  locale: Locale,
  translate: (key: string) => string,
): string {
  if (!isApiError(error)) return translate('errors.SERVER_ERROR');

  const fromServer = error.localized(locale);

  return fromServer.trim() !== '' ? fromServer : translate(`errors.${error.code}`);
}
