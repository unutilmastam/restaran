import { useTranslation } from '@sr/shared';
import { useEffect, useState } from 'react';

import { Field, inputClass } from '../components/Field';
import { Modal } from '../components/Modal';
import { api } from '../lib/api';
import { fieldErrors, useCrud } from '../lib/useCrud';

interface TableRow {
  id: number;
  number: number;
  name: string | null;
  capacity: number;
  status: string;
  is_active: boolean;
  nfc_token: string;
  nfc_url: string;
}

export function TablesScreen() {
  const { t } = useTranslation();
  const tables = useCrud<TableRow>('/admin/tables');

  const [editing, setEditing] = useState<TableRow | 'new' | null>(null);
  const [qrFor, setQrFor] = useState<TableRow | null>(null);
  const [copiedId, setCopiedId] = useState<number | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const copy = async (table: TableRow) => {
    try {
      await navigator.clipboard.writeText(table.nfc_url);
      setCopiedId(table.id);
      setTimeout(() => setCopiedId(null), 2000);
    } catch {
      // Clipboard API HTTPS talab qiladi — bo'lmasa URL ko'rinib turadi.
    }
  };

  const regenerate = async (table: TableRow) => {
    if (!window.confirm(t('admin.regenerate_warning'))) return;

    try {
      await api.post(`/admin/tables/${table.id}/regenerate-token`);
      await tables.reload();
    } catch (caught) {
      setNotice(tables.describe(caught));
    }
  };

  const remove = async (table: TableRow) => {
    if (!window.confirm(t('admin.delete_confirm'))) return;

    try {
      await api.delete(`/admin/tables/${table.id}`);
      await tables.reload();
    } catch (caught) {
      setNotice(tables.describe(caught));
    }
  };

  return (
    <div className="space-y-4 p-4 sm:p-6">
      <header className="flex items-center justify-between gap-3">
        <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-400">
          {t('admin.tables')}
        </h2>
        <button
          type="button"
          onClick={() => setEditing('new')}
          className="h-10 rounded-xl bg-slate-900 px-4 text-sm font-semibold text-white"
        >
          + {t('admin.new_table')}
        </button>
      </header>

      {notice !== null && (
        <p className="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-900">{notice}</p>
      )}

      <ul className="grid gap-2 lg:grid-cols-2">
        {tables.items.map((table) => (
          <li key={table.id} className="rounded-2xl bg-white p-4 ring-1 ring-slate-200">
            <div className="flex items-center gap-3">
              <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-lg font-semibold text-white tabular-nums">
                {table.number}
              </span>

              <div className="min-w-0 flex-1">
                <p className="font-medium text-slate-900">
                  {table.name ?? `${t('common.table')} ${table.number}`}
                </p>
                <p className="text-xs text-slate-400">
                  {t('customer.guests', { count: table.capacity })}
                </p>
              </div>

              <button
                type="button"
                onClick={() => setEditing(table)}
                className="h-10 rounded-lg px-3 text-sm text-slate-500 ring-1 ring-slate-200"
              >
                {t('common.edit')}
              </button>
              <button
                type="button"
                onClick={() => void remove(table)}
                className="h-10 rounded-lg px-3 text-sm text-rose-600 ring-1 ring-rose-200"
              >
                {t('common.delete')}
              </button>
            </div>

            <p className="mt-3 truncate rounded-lg bg-slate-50 px-3 py-2 font-mono text-xs text-slate-500">
              {table.nfc_url}
            </p>

            <div className="mt-2 flex flex-wrap gap-2">
              <button
                type="button"
                onClick={() => void copy(table)}
                className="h-10 rounded-lg px-3 text-sm font-medium text-slate-600 ring-1 ring-slate-200"
              >
                {copiedId === table.id ? t('admin.copied') : t('admin.copy')}
              </button>
              <button
                type="button"
                onClick={() => setQrFor(table)}
                className="h-10 rounded-lg px-3 text-sm font-medium text-slate-600 ring-1 ring-slate-200"
              >
                {t('admin.qr_code')}
              </button>
              <button
                type="button"
                onClick={() => void regenerate(table)}
                className="h-10 rounded-lg px-3 text-sm font-medium text-amber-700 ring-1 ring-amber-200"
              >
                {t('admin.regenerate_token')}
              </button>
            </div>
          </li>
        ))}
      </ul>

      {editing !== null && (
        <TableForm
          table={editing === 'new' ? null : editing}
          onClose={() => setEditing(null)}
          onSaved={() => {
            setEditing(null);
            void tables.reload();
          }}
          describe={tables.describe}
        />
      )}

      {qrFor !== null && <QrModal table={qrFor} onClose={() => setQrFor(null)} />}
    </div>
  );
}

function TableForm({
  table,
  onClose,
  onSaved,
  describe,
}: {
  table: TableRow | null;
  onClose: () => void;
  onSaved: () => void;
  describe: (error: unknown) => string;
}) {
  const { t } = useTranslation();
  const [form, setForm] = useState({
    number: table?.number ?? 1,
    name: table?.name ?? '',
    capacity: table?.capacity ?? 4,
    is_active: table?.is_active ?? true,
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [notice, setNotice] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async (event: React.FormEvent) => {
    event.preventDefault();
    setBusy(true);
    setErrors({});
    setNotice(null);

    const payload = { ...form, name: form.name === '' ? null : form.name };

    try {
      if (table === null) {
        await api.post('/admin/tables', payload);
      } else {
        await api.patch(`/admin/tables/${table.id}`, payload);
      }

      onSaved();
    } catch (caught) {
      setErrors(fieldErrors(caught));
      setNotice(describe(caught));
    } finally {
      setBusy(false);
    }
  };

  return (
    <Modal
      title={table === null ? t('admin.new_table') : t('admin.edit_table')}
      onClose={onClose}
      footer={
        <button
          type="submit"
          form="table-form"
          disabled={busy}
          className="h-12 w-full rounded-xl bg-slate-900 font-semibold text-white disabled:bg-slate-300"
        >
          {busy ? t('common.loading') : t('common.save')}
        </button>
      }
    >
      <form id="table-form" onSubmit={submit} className="space-y-4">
        <Field label={t('admin.table_number')} error={errors.number} required>
          <input
            type="number"
            inputMode="numeric"
            min={1}
            value={form.number}
            onChange={(event) => setForm({ ...form, number: Number(event.target.value) })}
            required
            className={inputClass}
          />
        </Field>

        <Field label={t('admin.table_name')} error={errors.name}>
          <input
            value={form.name}
            onChange={(event) => setForm({ ...form, name: event.target.value })}
            className={inputClass}
          />
        </Field>

        <Field label={t('admin.capacity')} error={errors.capacity} required>
          <input
            type="number"
            inputMode="numeric"
            min={1}
            value={form.capacity}
            onChange={(event) => setForm({ ...form, capacity: Number(event.target.value) })}
            required
            className={inputClass}
          />
        </Field>

        {notice !== null && (
          <p className="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-900">{notice}</p>
        )}

      </form>
    </Modal>
  );
}

/**
 * QR kod — stolga yopishtirish uchun.
 *
 * `qrcode` kutubxonasi DINAMIK import qilinadi: u faqat shu modal
 * ochilganda yuklanadi va asosiy bundle hajmiga ta'sir qilmaydi.
 */
function QrModal({ table, onClose }: { table: TableRow; onClose: () => void }) {
  const { t } = useTranslation();
  const [dataUrl, setDataUrl] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    void import('qrcode')
      .then((qr) => qr.toDataURL(table.nfc_url, { width: 512, margin: 2 }))
      .then((url) => {
        if (!cancelled) setDataUrl(url);
      });

    return () => {
      cancelled = true;
    };
  }, [table.nfc_url]);

  return (
    <Modal title={`${t('common.table')} ${table.number}`} onClose={onClose}>
      <div className="space-y-4 text-center">
        {dataUrl === null ? (
          <p className="py-16 text-sm text-slate-400">{t('common.loading')}</p>
        ) : (
          <img src={dataUrl} alt={t('admin.qr_code')} className="mx-auto w-64 rounded-xl" />
        )}

        <p className="break-all font-mono text-xs text-slate-400">{table.nfc_url}</p>

        {dataUrl !== null && (
          <a
            href={dataUrl}
            download={`stol-${table.number}-qr.png`}
            className="flex h-12 items-center justify-center rounded-xl bg-slate-900 font-semibold text-white"
          >
            {t('admin.qr_code')} ↓
          </a>
        )}
      </div>
    </Modal>
  );
}
