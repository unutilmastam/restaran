import { useTranslation } from '@sr/shared';
import { useEffect, type ReactNode } from 'react';

interface Props {
  title: string;
  onClose: () => void;
  children: ReactNode;
  /** Saqlash tugmasi — pastda YOPISHIB turadi. */
  footer?: ReactNode;
}

/**
 * Mobil ekranda pastdan chiqadigan sheet, desktopda markazda modal.
 * Restoran egasi menyuni ko'pincha TELEFONDAN kiritadi.
 *
 * ⚠️ Sarlavha tepada, tugma pastda YOPISHIB turadi. Mahsulot formasi
 * uzun (10 dan ortiq maydon) va telefon ekraniga sig'maydi — tugma
 * oqim ichida qolsa foydalanuvchi uni topish uchun har safar pastgacha
 * varaqlashi kerak bo'lardi.
 */
export function Modal({ title, onClose, children, footer }: Props) {
  const { t } = useTranslation();

  useEffect(() => {
    const onKey = (event: KeyboardEvent) => event.key === 'Escape' && onClose();
    window.addEventListener('keydown', onKey);

    // Orqa fon varaqlanmasin.
    const previous = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    return () => {
      window.removeEventListener('keydown', onKey);
      document.body.style.overflow = previous;
    };
  }, [onClose]);

  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 sm:items-center">
      <div
        role="dialog"
        aria-modal="true"
        aria-label={title}
        className="flex max-h-[92dvh] w-full flex-col rounded-t-3xl bg-white shadow-xl sm:max-w-lg sm:rounded-3xl"
      >
        <header className="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
          <h2 className="text-lg font-semibold text-slate-900">{title}</h2>
          <button
            type="button"
            onClick={onClose}
            aria-label={t('common.close')}
            className="-mr-2 flex h-11 w-11 items-center justify-center rounded-xl text-slate-400 transition active:bg-slate-100"
          >
            ✕
          </button>
        </header>

        <div className="min-h-0 flex-1 overflow-y-auto px-5 py-4">{children}</div>

        {footer !== undefined && (
          <footer className="border-t border-slate-100 px-5 py-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
            {footer}
          </footer>
        )}
      </div>
    </div>
  );
}
