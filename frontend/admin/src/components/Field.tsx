import type { ReactNode } from 'react';

interface Props {
  label: string;
  error?: string;
  hint?: string;
  required?: boolean;
  children: ReactNode;
}

/** Mobil uchun: yorliq tepada, maydon balandligi 44px dan kam emas. */
export function Field({ label, error, hint, required, children }: Props) {
  return (
    <label className="block">
      <span className="text-sm font-medium text-slate-600">
        {label}
        {required === true && <span className="ml-0.5 text-rose-500">*</span>}
      </span>

      <div className="mt-1">{children}</div>

      {hint !== undefined && <p className="mt-1 text-xs text-slate-400">{hint}</p>}
      {error !== undefined && <p className="mt-1 text-xs text-rose-600">{error}</p>}
    </label>
  );
}

export const inputClass =
  'h-11 w-full rounded-xl bg-slate-50 px-4 text-slate-900 ring-1 ring-slate-200 focus:outline-none focus:ring-2 focus:ring-slate-900';
