import { formatTime, useTranslation } from '@sr/shared';
import { useEffect, useState } from 'react';

import { api } from '../lib/api';

interface Call {
  id: number;
  status: string;
  message: string | null;
  table: { number: number } | null;
  created_at: string;
}

/**
 * Chaqiruvlar — SKELETON.
 *
 * Mijoz tomonidan chaqiruv yaratish va uni afitsantga biriktirish
 * PHASE 11 da (docs/03-PHASES.md). Endpoint allaqachon ishlaydi va
 * bo'sh ro'yxat qaytaradi, shuning uchun ekran hozircha xabar
 * ko'rsatadi va oqim tayyor bo'lgach o'zi to'ladi.
 */
export function CallsScreen() {
  const { t } = useTranslation();
  const [calls, setCalls] = useState<Call[]>([]);

  useEffect(() => {
    void api.get<{ items: Call[] }>('/waiter/calls').then(({ data }) => setCalls(data.items));
  }, []);

  if (calls.length === 0) {
    return (
      <div className="p-8 text-center">
        <p className="text-slate-400">{t('waiter.no_calls')}</p>
        <p className="mt-1 text-xs text-slate-300">{t('waiter.calls_soon')}</p>
      </div>
    );
  }

  return (
    <ul className="space-y-3 p-4">
      {calls.map((call) => (
        <li key={call.id} className="rounded-2xl bg-white p-4 shadow-sm">
          <div className="flex items-baseline justify-between">
            <span className="text-lg font-bold text-slate-900">
              {t('common.table')} {call.table?.number ?? '—'}
            </span>
            <span className="text-xs text-slate-400">{formatTime(call.created_at)}</span>
          </div>
          {call.message !== null && <p className="mt-2 text-sm text-slate-600">{call.message}</p>}
        </li>
      ))}
    </ul>
  );
}
