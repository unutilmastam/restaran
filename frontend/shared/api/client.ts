import axios, { AxiosError, type AxiosInstance } from 'axios';

import { getLocale } from '../i18n';
import type { ApiEnvelope } from '../types';
import { ApiError } from './errors';

export interface ClientOptions {
  baseURL: string;
  /** Sanctum token (admin / waiter). Customer uchun ishlatilmaydi. */
  getToken?: () => string | null;
  /** Customer session tokeni — `X-Customer-Token` header'ida ketadi. */
  getCustomerToken?: () => string | null;
  /** 401 kelganda chaqiriladi (logout + login sahifasiga yo'naltirish). */
  onUnauthenticated?: () => void;
}

/**
 * Barcha so'rovlar `Accept-Language` header'i bilan ketadi
 * (docs/02-I18N-RU-UZ.md §2) va javob konverti ochib beriladi:
 * chaqiruvchi `data` ni oladi, xato bo'lsa `ApiError` tashlanadi.
 */
export function createApiClient(options: ClientOptions): AxiosInstance {
  const client = axios.create({
    baseURL: options.baseURL,
    timeout: 15000,
    headers: { Accept: 'application/json' },
  });

  client.interceptors.request.use((config) => {
    config.headers.set('Accept-Language', getLocale());

    const token = options.getToken?.();
    if (token) config.headers.set('Authorization', `Bearer ${token}`);

    const customerToken = options.getCustomerToken?.();
    if (customerToken) config.headers.set('X-Customer-Token', customerToken);

    return config;
  });

  client.interceptors.response.use(
    (response) => {
      const envelope = response.data as ApiEnvelope<unknown>;

      // Konvertni ochib beramiz — komponentlar `success`/`data` bilan
      // ovora bo'lmasin.
      response.data = envelope?.success === true ? envelope.data : envelope;

      return response;
    },
    (error: AxiosError<ApiEnvelope<unknown>>) => {
      if (!error.response) {
        // Tarmoq uzildi — docs/02 §6 lug'atidagi NETWORK_ERROR.
        // Matn frontend i18n'dan olinadi, chunki server javob bermadi.
        throw new ApiError('NETWORK_ERROR', 0, '', '');
      }

      const { status, data } = error.response;

      if (status === 401) options.onUnauthenticated?.();

      throw ApiError.fromEnvelope(data as ApiEnvelope<unknown>, status);
    },
  );

  return client;
}
