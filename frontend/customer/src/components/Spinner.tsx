import { useTranslation } from '@sr/shared';

export function Spinner() {
  const { t } = useTranslation();

  return (
    <div className="flex min-h-dvh items-center justify-center bg-slate-50">
      <p className="text-sm text-slate-400">{t('common.loading')}</p>
    </div>
  );
}
