import { errorText, isApiError, useTranslation } from '@sr/shared';
import { useCallback, useEffect, useState } from 'react';

import { api } from './api';

/**
 * Ro'yxat + xato holati — uch CRUD ekrani uchun umumiy.
 *
 * Xato matni server konvertidan olinadi; `LIMIT_EXCEEDED` esa
 * `used/max` bilan tushunarli qilib ko'rsatiladi (docs/06-SAAS.md §8).
 */
export function useCrud<T>(path: string) {
  const { t, locale } = useTranslation();

  const [items, setItems] = useState<T[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);

    try {
      const { data } = await api.get<{ items: T[] }>(path);
      setItems(data.items);
      setError(null);
    } catch (caught) {
      setError(errorText(caught, locale, t));
    } finally {
      setLoading(false);
    }
  }, [path, locale, t]);

  useEffect(() => {
    void load();
  }, [load]);

  /** Xatoni o'qiladigan matnga aylantiradi. */
  const describe = useCallback(
    (caught: unknown): string => {
      if (isApiError(caught) && caught.code === 'LIMIT_EXCEEDED') {
        const payload = caught.data as { used?: number; max?: number } | null;

        return t('admin.limit_reached', {
          used: payload?.used ?? 0,
          max: payload?.max ?? 0,
        });
      }

      // Validatsiya xatolari maydon bo'yicha keladi — birinchisini
      // ko'rsatamiz, qolgani forma ichida.
      if (isApiError(caught) && caught.code === 'VALIDATION_FAILED') {
        const fields = (caught.data as { fields?: Record<string, string[]> } | null)?.fields;
        const first = fields === undefined ? undefined : Object.values(fields)[0]?.[0];

        if (typeof first === 'string') return first;
      }

      return errorText(caught, locale, t);
    },
    [t, locale],
  );

  return { items, loading, error, setError, reload: load, describe };
}

/** Validatsiya xatolarini maydon bo'yicha ajratadi. */
export function fieldErrors(caught: unknown): Record<string, string> {
  if (!isApiError(caught) || caught.code !== 'VALIDATION_FAILED') return {};

  const fields = (caught.data as { fields?: Record<string, string[]> } | null)?.fields ?? {};

  return Object.fromEntries(
    Object.entries(fields).map(([key, messages]) => [key, messages[0] ?? '']),
  );
}
