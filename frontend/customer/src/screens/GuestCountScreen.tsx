import { useTranslation } from '@sr/shared';
import { useState } from 'react';

interface Props {
  tableNumber: number;
  capacity: number;
  onConfirm: (guestCount: number) => void;
}

/**
 * NFC skanerlangandan keyingi birinchi ekran (docs/03-PHASES.md PHASE 3).
 *
 * Session PHASE 4 da ochiladi — hozir tanlangan son faqat keyingi ekranga
 * uzatiladi.
 */
export function GuestCountScreen({ tableNumber, capacity, onConfirm }: Props) {
  const { t } = useTranslation();
  const [count, setCount] = useState(2);

  const max = Math.max(capacity, 12);

  return (
    <div className="flex flex-1 flex-col justify-center px-6 py-10">
      <p className="text-center text-sm font-medium uppercase tracking-wide text-slate-400">
        {t('common.table')} {tableNumber}
      </p>

      <h1 className="mt-3 text-center text-2xl font-semibold text-slate-900">
        {t('customer.guest_count')}
      </h1>

      <div className="mt-10 flex items-center justify-center gap-6">
        <button
          type="button"
          onClick={() => setCount((value) => Math.max(1, value - 1))}
          disabled={count <= 1}
          aria-label="−"
          className="h-16 w-16 rounded-2xl bg-white text-2xl font-bold text-slate-700 ring-1 ring-slate-200 transition active:bg-slate-100 disabled:text-slate-300"
        >
          −
        </button>

        <span
          aria-live="polite"
          className="min-w-20 text-center text-5xl font-semibold tabular-nums text-slate-900"
        >
          {count}
        </span>

        <button
          type="button"
          onClick={() => setCount((value) => Math.min(max, value + 1))}
          disabled={count >= max}
          aria-label="+"
          className="h-16 w-16 rounded-2xl bg-white text-2xl font-bold text-slate-700 ring-1 ring-slate-200 transition active:bg-slate-100 disabled:text-slate-300"
        >
          +
        </button>
      </div>

      <p aria-live="polite" className="mt-4 text-center text-sm text-slate-500">
        {t('customer.guests', { count })}
      </p>

      <button
        type="button"
        onClick={() => onConfirm(count)}
        className="mt-8 h-14 rounded-2xl bg-slate-900 text-base font-semibold text-white transition active:bg-slate-800"
      >
        {t('customer.view_menu')}
      </button>
    </div>
  );
}
