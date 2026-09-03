import { createApiClient } from '@sr/shared';

import { readToken, useAuth } from './auth';

export const api = createApiClient({
  baseURL: import.meta.env.VITE_API_URL ?? '/api/v1',
  getToken: () => readToken(),
  // Token eskirsa yoki bekor qilinsa — darhol login ekraniga.
  onUnauthenticated: () => useAuth.getState().clear(),
});
