import { useTranslation } from '@sr/shared';
import { useCallback, useEffect, useRef, useState } from 'react';

interface Props {
  label: string;
  holdingLabel: string;
  onComplete: () => void;
  disabled?: boolean;
}

/**
 * BOSIB TURISH tugmasi — bir bosishda ishlamaydi.
 *
 * SABAB: "Yetkazildi" tugmasi faqat ovqat mijozga BERILGANDAN KEYIN
 * bosilishi kerak. Afitsant telefonni bir qo'lda ushlab, yurib
 * ketayotganda oddiy tugmani tasodifan bosib yuborishi juda oson —
 * bunda buyurtma yetkazilgan deb belgilanadi va afitsant bo'sh
 * hisoblanib, unga darhol yangi buyurtma biriktiriladi.
 *
 * Shuning uchun bu tugma 800 ms ushlab turishni talab qiladi va
 * jarayonni progress bilan ko'rsatadi.
 */
const HOLD_MS = 800;

export function HoldButton({ label, holdingLabel, onComplete, disabled }: Props) {
  const { t } = useTranslation();
  const [progress, setProgress] = useState(0);
  const timer = useRef<number | null>(null);
  const started = useRef<number>(0);

  const stop = useCallback(() => {
    if (timer.current !== null) {
      cancelAnimationFrame(timer.current);
      timer.current = null;
    }

    setProgress(0);
  }, []);

  useEffect(() => stop, [stop]);

  const tick = useCallback(() => {
    const elapsed = Date.now() - started.current;
    const percent = Math.min(100, (elapsed / HOLD_MS) * 100);

    setProgress(percent);

    if (percent >= 100) {
      stop();
      onComplete();

      return;
    }

    timer.current = requestAnimationFrame(tick);
  }, [onComplete, stop]);

  const start = useCallback(() => {
    if (disabled === true) return;

    started.current = Date.now();
    timer.current = requestAnimationFrame(tick);
  }, [disabled, tick]);

  return (
    <button
      type="button"
      disabled={disabled}
      onPointerDown={start}
      onPointerUp={stop}
      onPointerLeave={stop}
      onPointerCancel={stop}
      // Bosib turganda brauzer matnni belgilamasin / sahifa siljimasin.
      className="relative h-16 w-full touch-none select-none overflow-hidden rounded-2xl bg-emerald-600 text-lg font-bold text-white transition disabled:bg-slate-200 disabled:text-slate-400"
      aria-describedby="hold-hint"
    >
      <span
        aria-hidden="true"
        className="absolute inset-y-0 left-0 bg-emerald-800 transition-none"
        style={{ width: `${progress}%` }}
      />
      <span className="relative">{progress > 0 ? holdingLabel : label}</span>
      <span id="hold-hint" className="sr-only">
        {t('waiter.swipe_hint')}
      </span>
    </button>
  );
}
