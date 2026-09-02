import { useTranslation } from '@sr/shared';

interface Props {
  /** Xato kodi — matn `errors.*` lug'atidan olinadi (docs/02 §6). */
  code: string;
}

/**
 * Ikki holat uchun:
 *   RESTAURANT_UNAVAILABLE — obuna tugagan (docs/06-SAAS.md §4)
 *   INVALID_TABLE          — stol topilmadi / restoran arxivlangan
 */
export function UnavailableScreen({ code }: Props) {
  const { t } = useTranslation();

  return (
    <div className="flex min-h-dvh flex-col items-center justify-center gap-4 bg-slate-50 px-8 text-center">
      <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-white ring-1 ring-slate-200">
        <svg
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="1.5"
          strokeLinecap="round"
          className="h-7 w-7 text-slate-400"
          aria-hidden="true"
        >
          <circle cx="12" cy="12" r="9" />
          <path d="M12 8v5M12 16h.01" />
        </svg>
      </div>

      <p className="text-lg font-medium text-slate-900">{t(`errors.${code}`)}</p>
    </div>
  );
}
