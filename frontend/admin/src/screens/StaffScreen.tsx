import { useTranslation } from '@sr/shared';
import { useState } from 'react';

import { Field, inputClass } from '../components/Field';
import { Modal } from '../components/Modal';
import { api } from '../lib/api';
import { fieldErrors, useCrud } from '../lib/useCrud';

interface StaffRow {
  id: number;
  name: string;
  username: string;
  phone: string | null;
  role: 'OWNER_ADMIN' | 'ADMIN' | 'WAITER';
  status: string | null;
  locale: 'uz' | 'ru';
  is_active: boolean;
  /** Server hisoblaydi — OWNER_ADMIN uchun `false` (docs/06-SAAS.md §1). */
  is_deletable: boolean;
}

const ROLE_KEY = {
  OWNER_ADMIN: 'admin.role_owner',
  ADMIN: 'admin.role_admin',
  WAITER: 'admin.role_waiter',
} as const;

export function StaffScreen() {
  const { t } = useTranslation();
  const staff = useCrud<StaffRow>('/admin/staff');

  const [editing, setEditing] = useState<StaffRow | 'new' | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const remove = async (member: StaffRow) => {
    if (!window.confirm(t('admin.delete_confirm'))) return;

    try {
      await api.delete(`/admin/staff/${member.id}`);
      await staff.reload();
    } catch (caught) {
      setNotice(staff.describe(caught));
    }
  };

  return (
    <div className="space-y-4 p-4 sm:p-6">
      <header className="flex items-center justify-between gap-3">
        <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-400">
          {t('admin.waiters')}
        </h2>
        <button
          type="button"
          onClick={() => setEditing('new')}
          className="h-10 rounded-xl bg-slate-900 px-4 text-sm font-semibold text-white"
        >
          + {t('admin.new_staff')}
        </button>
      </header>

      {notice !== null && (
        <p className="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-900">{notice}</p>
      )}

      <ul className="grid gap-2 lg:grid-cols-2">
        {staff.items.map((member) => (
          <li
            key={member.id}
            className="flex items-center gap-3 rounded-2xl bg-white p-4 ring-1 ring-slate-200"
          >
            <div className="min-w-0 flex-1">
              <p className="truncate font-medium text-slate-900">{member.name}</p>
              <p className="text-xs text-slate-400">
                @{member.username} · {t(ROLE_KEY[member.role])}
                {member.status !== null && ` · ${t(`waiter.status_${member.status.toLowerCase()}`)}`}
              </p>
            </div>

            <button
              type="button"
              onClick={() => setEditing(member)}
              className="h-10 rounded-lg px-3 text-sm text-slate-500 ring-1 ring-slate-200"
            >
              {t('common.edit')}
            </button>

            {member.is_deletable ? (
              <button
                type="button"
                onClick={() => void remove(member)}
                className="h-10 rounded-lg px-3 text-sm text-rose-600 ring-1 ring-rose-200"
              >
                {t('common.delete')}
              </button>
            ) : (
              // docs/06-SAAS.md §1 — restoran o'zini o'zi qulflab qo'ymasin.
              <span
                title={t('admin.owner_cannot_delete')}
                className="flex h-10 items-center rounded-lg bg-slate-50 px-3 text-xs text-slate-400"
              >
                🔒
              </span>
            )}
          </li>
        ))}
      </ul>

      {editing !== null && (
        <StaffForm
          member={editing === 'new' ? null : editing}
          onClose={() => setEditing(null)}
          onSaved={() => {
            setEditing(null);
            void staff.reload();
          }}
          describe={staff.describe}
        />
      )}
    </div>
  );
}

function StaffForm({
  member,
  onClose,
  onSaved,
  describe,
}: {
  member: StaffRow | null;
  onClose: () => void;
  onSaved: () => void;
  describe: (error: unknown) => string;
}) {
  const { t } = useTranslation();

  const [form, setForm] = useState({
    name: member?.name ?? '',
    username: member?.username ?? '',
    phone: member?.phone ?? '',
    password: '',
    pin: '',
    role: member?.role === 'OWNER_ADMIN' ? 'OWNER_ADMIN' : (member?.role ?? 'WAITER'),
    locale: member?.locale ?? 'uz',
    is_active: member?.is_active ?? true,
  });

  const [errors, setErrors] = useState<Record<string, string>>({});
  const [notice, setNotice] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const isOwner = member?.role === 'OWNER_ADMIN';

  const submit = async (event: React.FormEvent) => {
    event.preventDefault();
    setBusy(true);
    setErrors({});
    setNotice(null);

    const payload: Record<string, unknown> = {
      ...form,
      phone: form.phone === '' ? null : form.phone,
      pin: form.pin === '' ? null : form.pin,
    };

    // Bo'sh parol yuborilmaydi — eskisi saqlanadi.
    if (form.password === '') delete payload.password;

    try {
      if (member === null) {
        await api.post('/admin/staff', payload);
      } else {
        await api.put(`/admin/staff/${member.id}`, payload);
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
      title={member === null ? t('admin.new_staff') : t('admin.edit_staff')}
      onClose={onClose}
      footer={
        <button
          type="submit"
          form="staff-form"
          disabled={busy}
          className="h-12 w-full rounded-xl bg-slate-900 font-semibold text-white disabled:bg-slate-300"
        >
          {busy ? t('common.loading') : t('common.save')}
        </button>
      }
    >
      <form id="staff-form" onSubmit={submit} className="space-y-4">
        <Field label={t('admin.full_name')} error={errors.name} required>
          <input
            value={form.name}
            onChange={(event) => setForm({ ...form, name: event.target.value })}
            required
            className={inputClass}
          />
        </Field>

        <Field label={t('admin.username')} error={errors.username} required>
          <input
            value={form.username}
            onChange={(event) => setForm({ ...form, username: event.target.value })}
            required
            pattern="[A-Za-z0-9_\-]+"
            autoComplete="off"
            className={inputClass}
          />
        </Field>

        <Field
          label={t('admin.password')}
          error={errors.password}
          hint={member === null ? undefined : t('admin.password_hint')}
          required={member === null}
        >
          <input
            type="password"
            value={form.password}
            onChange={(event) => setForm({ ...form, password: event.target.value })}
            required={member === null}
            autoComplete="new-password"
            className={inputClass}
          />
        </Field>

        <Field label={t('admin.pin')} error={errors.pin}>
          <input
            inputMode="numeric"
            value={form.pin}
            onChange={(event) => setForm({ ...form, pin: event.target.value })}
            className={inputClass}
          />
        </Field>

        <Field label={t('admin.role')} error={errors.role} required>
          <select
            value={form.role}
            onChange={(event) => setForm({ ...form, role: event.target.value as StaffRow['role'] })}
            // OWNER_ADMIN rolini bu yerdan o'zgartirib bo'lmaydi —
            // buni faqat SUPER_ADMIN qiladi (docs/06-SAAS.md §1).
            disabled={isOwner}
            className={`${inputClass} disabled:text-slate-400`}
          >
            {isOwner && <option value="OWNER_ADMIN">{t('admin.role_owner')}</option>}
            <option value="WAITER">{t('admin.role_waiter')}</option>
            <option value="ADMIN">{t('admin.role_admin')}</option>
          </select>
        </Field>

        <label className="flex items-center gap-3">
          <input
            type="checkbox"
            checked={form.is_active}
            onChange={(event) => setForm({ ...form, is_active: event.target.checked })}
            className="h-5 w-5 rounded"
          />
          <span className="text-sm text-slate-700">{t('admin.active')}</span>
        </label>

        {notice !== null && (
          <p className="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-900">{notice}</p>
        )}

      </form>
    </Modal>
  );
}
