import { createApiClient } from '@sr/shared';

import { readCustomerToken } from './customerToken';

/**
 * Customer API mijozi. Sanctum tokeni YO'Q — mijoz autentifikatsiyadan
 * o'tmaydi, restoran `nfc_token` orqali aniqlanadi.
 *
 * `X-Customer-Token` session ochilgandan keyin avtomatik qo'shiladi:
 * `GET /orders/{id}` va `GET /sessions/me` aynan shu header'ni talab
 * qiladi (401 qaytaradi).
 */
export const api = createApiClient({
  baseURL: import.meta.env.VITE_API_URL ?? '/api/v1',
  getCustomerToken: () => readCustomerToken(),
});
