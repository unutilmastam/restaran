import { formatMoney, useTranslation, type Product } from '@sr/shared';
import { useState } from 'react';

import { ProductImage } from '../components/ProductImage';
import { QuantityStepper } from '../components/QuantityStepper';
import { useCart } from '../store/cart';

interface Props {
  products: Product[];
  onBack: () => void;
  onSubmit: (note: string | null) => void;
  submitting: boolean;
  /** Buyurtma bloklangan bo'lsa xato kodi (`errors.*` lug'atidan). */
  blockedReason: string | null;
}

export function CartScreen({ products, onBack, onSubmit, submitting, blockedReason }: Props) {
  const { t, locale } = useTranslation();
  const [note, setNote] = useState('');

  const lines = useCart((state) => state.lines);
  const add = useCart((state) => state.add);
  const remove = useCart((state) => state.remove);
  const clear = useCart((state) => state.clear);

  const rows = lines
    .map((line) => ({ line, product: products.find((item) => item.id === line.productId) }))
    .filter((row): row is { line: (typeof lines)[number]; product: Product } => row.product !== undefined);

  const total = rows.reduce((sum, row) => sum + row.product.effective_price * row.line.quantity, 0);

  return (
    <div className="flex min-h-dvh flex-col bg-slate-50">
      <header className="sticky top-0 z-10 flex items-center gap-3 border-b border-slate-200 bg-slate-50/95 px-4 py-3 backdrop-blur">
        <button
          type="button"
          onClick={onBack}
          className="-ml-2 flex h-11 w-11 items-center justify-center rounded-xl text-slate-600 transition active:bg-slate-200"
          aria-label={t('common.back')}
        >
          <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            className="h-5 w-5"
            aria-hidden="true"
          >
            <path d="m15 18-6-6 6-6" />
          </svg>
        </button>

        <h1 className="flex-1 text-lg font-semibold text-slate-900">{t('customer.cart')}</h1>

        {rows.length > 0 && (
          <button
            type="button"
            onClick={clear}
            className="rounded-lg px-3 py-2 text-sm font-medium text-slate-500 transition active:bg-slate-200"
          >
            {t('common.delete')}
          </button>
        )}
      </header>

      {rows.length === 0 ? (
        <p className="flex flex-1 items-center justify-center text-sm text-slate-400">
          {t('customer.cart_empty')}
        </p>
      ) : (
        <>
          <ul className="flex-1 divide-y divide-slate-100">
            {rows.map(({ line, product }) => (
              <li key={product.id} className="flex items-center gap-3 px-4 py-3">
                <ProductImage
                  src={product.image}
                  alt={product.name}
                  className="h-16 w-16 shrink-0 rounded-xl"
                />

                <div className="min-w-0 flex-1">
                  <p className="truncate font-medium text-slate-900">{product.name}</p>
                  <p className="mt-0.5 text-sm text-slate-500">
                    {formatMoney(product.effective_price, locale)} × {line.quantity}
                  </p>
                  <p className="mt-0.5 font-semibold text-slate-900">
                    {formatMoney(product.effective_price * line.quantity, locale)}
                  </p>
                </div>

                <QuantityStepper
                  quantity={line.quantity}
                  onIncrement={() => add(product.id)}
                  onDecrement={() => remove(product.id)}
                />
              </li>
            ))}
          </ul>

          <div className="sticky bottom-0 space-y-3 border-t border-slate-200 bg-white p-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
            <input
              type="text"
              value={note}
              onChange={(event) => setNote(event.target.value)}
              maxLength={255}
              placeholder={t('customer.note_placeholder')}
              className="h-11 w-full rounded-xl bg-slate-50 px-4 text-sm text-slate-900 ring-1 ring-slate-200 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-900"
            />

            <div className="flex items-baseline justify-between">
              <span className="text-slate-500">{t('common.total')}</span>
              <span className="text-xl font-semibold text-slate-900">
                {formatMoney(total, locale)}
              </span>
            </div>

            {blockedReason !== null && (
              <p className="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">
                {t(`errors.${blockedReason}`)}
              </p>
            )}

            <button
              type="button"
              onClick={() => onSubmit(note.trim() === '' ? null : note.trim())}
              disabled={submitting || blockedReason !== null}
              className="h-14 w-full rounded-2xl bg-slate-900 text-base font-semibold text-white transition active:bg-slate-800 disabled:bg-slate-200 disabled:text-slate-400"
            >
              {submitting ? t('customer.submitting') : t('customer.place_order')}
            </button>
          </div>
        </>
      )}
    </div>
  );
}
