import { SUPPORTED_LOCALES, setLocale, useTranslation, type Locale } from '@sr/shared';

/** docs/02-I18N-RU-UZ.md §5 — yuqori o'ng burchakda. */
export function LanguageSwitcher() {
  const { t, locale } = useTranslation();

  return (
    <nav aria-label={t('common.language')} className="flex gap-1">
      {SUPPORTED_LOCALES.map((option: Locale) => (
        <button
          key={option}
          type="button"
          onClick={() => setLocale(option)}
          aria-current={locale === option}
          className={`min-w-11 rounded-lg px-3 py-2 text-sm font-semibold uppercase transition ${
            locale === option
              ? 'bg-slate-900 text-white'
              : 'bg-white text-slate-500 ring-1 ring-slate-200'
          }`}
        >
          {option}
        </button>
      ))}
    </nav>
  );
}
