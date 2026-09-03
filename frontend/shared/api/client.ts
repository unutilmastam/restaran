import { getLocale } from '../i18n';
import type { ApiEnvelope } from '../types';
import { ApiError } from './errors';

/**
 * `fetch` asosidagi mijoz — `axios` ISHLATILMAYDI.
 *
 * SABAB: axios ~12 KB gzip. Customer PWA NFC orqali mobil internetda
 * ochiladi va bundle byudjeti qattiq (≤100 KB gz). Bizga kerak bo'lgani —
 * header qo'shish, JSON parse va xato konvertini ochish; `fetch` bularni
 * qo'shimcha kutubxonasiz bajaradi.
 */

export interface ClientOptions {
  baseURL: string;
  /** Sanctum token (admin / waiter). Customer uchun ishlatilmaydi. */
  getToken?: () => string | null;
  /** Customer session tokeni — `X-Customer-Token` header'ida ketadi. */
  getCustomerToken?: () => string | null;
  /** 401 kelganda chaqiriladi (logout + login sahifasiga yo'naltirish). */
  onUnauthenticated?: () => void;
  /** Millisekund. Sekin mobil tarmoqda so'rov abadiy osilib qolmasin. */
  timeoutMs?: number;
}

export interface ApiResult<T> {
  data: T;
  status: number;
}

export interface ApiClient {
  get: <T>(path: string, init?: RequestInit) => Promise<ApiResult<T>>;
  post: <T>(path: string, body?: unknown, init?: RequestInit) => Promise<ApiResult<T>>;
  put: <T>(path: string, body?: unknown, init?: RequestInit) => Promise<ApiResult<T>>;
  patch: <T>(path: string, body?: unknown, init?: RequestInit) => Promise<ApiResult<T>>;
  delete: <T>(path: string, init?: RequestInit) => Promise<ApiResult<T>>;
}

export function createApiClient(options: ClientOptions): ApiClient {
  const timeoutMs = options.timeoutMs ?? 15000;

  async function request<T>(method: string, path: string, body?: unknown, init?: RequestInit) {
    const headers = new Headers(init?.headers);
    headers.set('Accept', 'application/json');
    // Har bir so'rov joriy til bilan ketadi (docs/02-I18N-RU-UZ.md §2).
    headers.set('Accept-Language', getLocale());

    const token = options.getToken?.();
    if (token) headers.set('Authorization', `Bearer ${token}`);

    const customerToken = options.getCustomerToken?.();
    if (customerToken) headers.set('X-Customer-Token', customerToken);

    if (body !== undefined) headers.set('Content-Type', 'application/json');

    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);

    let response: Response;

    try {
      response = await fetch(options.baseURL + path, {
        ...init,
        method,
        headers,
        body: body === undefined ? undefined : JSON.stringify(body),
        signal: init?.signal ?? controller.signal,
      });
    } catch {
      // Tarmoq uzildi yoki timeout — server javob bermadi, shuning uchun
      // matn frontend i18n'dan olinadi (docs/02 §6 NETWORK_ERROR).
      throw new ApiError('NETWORK_ERROR', 0, '', '');
    } finally {
      clearTimeout(timer);
    }

    let envelope: ApiEnvelope<T> | null = null;

    try {
      envelope = (await response.json()) as ApiEnvelope<T>;
    } catch {
      envelope = null;
    }

    if (!response.ok || envelope === null || envelope.success !== true) {
      if (response.status === 401) options.onUnauthenticated?.();

      if (envelope === null) {
        throw new ApiError('SERVER_ERROR', response.status, '', '');
      }

      throw ApiError.fromEnvelope(envelope, response.status);
    }

    // Konvertni ochib beramiz — komponentlar `success`/`data` bilan
    // ovora bo'lmasin.
    return { data: envelope.data, status: response.status };
  }

  return {
    get: (path, init) => request('GET', path, undefined, init),
    post: (path, body, init) => request('POST', path, body, init),
    put: (path, body, init) => request('PUT', path, body, init),
    patch: (path, body, init) => request('PATCH', path, body, init),
    delete: (path, init) => request('DELETE', path, undefined, init),
  };
}
