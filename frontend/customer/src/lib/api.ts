import { createApiClient } from '@sr/shared';

/**
 * Customer API mijozi. Sanctum tokeni YO'Q — mijoz autentifikatsiyadan
 * o'tmaydi, restoran `nfc_token` orqali aniqlanadi.
 *
 * `X-Customer-Token` PHASE 4 da (session ochilgach) qo'shiladi.
 */
export const api = createApiClient({
  baseURL: import.meta.env.VITE_API_URL ?? '/api/v1',
});
