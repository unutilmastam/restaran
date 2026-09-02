import { useTranslation } from '@sr/shared';

interface Props {
  quantity: number;
  onIncrement: () => void;
  onDecrement: () => void;
  disabled?: boolean;
}

/** Katta tugmalar — barmoq bilan bosiladi (min 44px). */
export function QuantityStepper({ quantity, onIncrement, onDecrement, disabled }: Props) {
  const { t } = useTranslation();

  if (quantity === 0) {
    return (
      <button
        type="button"
        onClick={onIncrement}
        disabled={disabled}
        className="h-11 rounded-xl bg-slate-900 px-5 text-sm font-semibold text-white transition disabled:bg-slate-200 disabled:text-slate-400"
      >
        {disabled ? t('customer.unavailable') : t('common.add')}
      </button>
    );
  }

  return (
    <div className="flex h-11 items-center gap-1 rounded-xl bg-slate-900 px-1 text-white">
      <button
        type="button"
        onClick={onDecrement}
        aria-label={t('common.delete')}
        className="h-9 w-9 rounded-lg text-lg font-bold transition active:bg-white/20"
      >
        −
      </button>
      <span aria-live="polite" className="min-w-8 text-center text-sm font-semibold tabular-nums">
        {quantity}
      </span>
      <button
        type="button"
        onClick={onIncrement}
        aria-label={t('common.add')}
        className="h-9 w-9 rounded-lg text-lg font-bold transition active:bg-white/20"
      >
        +
      </button>
    </div>
  );
}
