import { formatMoney, useTranslation } from '@sr/shared';
import { useState } from 'react';

import { Field, inputClass } from '../components/Field';
import { Modal } from '../components/Modal';
import { api } from '../lib/api';
import { fieldErrors, useCrud } from '../lib/useCrud';
import { uploadFile } from '../lib/upload';

interface Category {
  id: number;
  name_uz: string;
  name_ru: string;
  slug: string;
  sort_order: number;
  is_active: boolean;
  products_count?: number;
}

interface Product {
  id: number;
  category_id: number;
  name_uz: string;
  name_ru: string;
  description_uz: string | null;
  description_ru: string | null;
  image: string | null;
  price: number;
  discount: number;
  weight: number | null;
  preparation_time: number | null;
  is_available: boolean;
}

export function MenuScreen() {
  const { t, locale } = useTranslation();

  const categories = useCrud<Category>('/admin/categories');
  const products = useCrud<Product>('/admin/products');

  const [editingCategory, setEditingCategory] = useState<Category | 'new' | null>(null);
  const [editingProduct, setEditingProduct] = useState<Product | 'new' | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const removeCategory = async (category: Category) => {
    if (!window.confirm(t('admin.delete_confirm'))) return;

    try {
      await api.delete(`/admin/categories/${category.id}`);
      await Promise.all([categories.reload(), products.reload()]);
    } catch (caught) {
      setNotice(categories.describe(caught));
    }
  };

  const removeProduct = async (product: Product) => {
    if (!window.confirm(t('admin.delete_confirm'))) return;

    try {
      await api.delete(`/admin/products/${product.id}`);
      await products.reload();
    } catch (caught) {
      setNotice(products.describe(caught));
    }
  };

  const toggleAvailability = async (product: Product) => {
    try {
      await api.patch(`/admin/products/${product.id}/availability`, {
        is_available: !product.is_available,
      });
      await products.reload();
    } catch (caught) {
      setNotice(products.describe(caught));
    }
  };

  return (
    <div className="space-y-8 p-4 sm:p-6">
      {notice !== null && (
        <p className="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-900">{notice}</p>
      )}

      <section>
        <header className="mb-3 flex items-center justify-between gap-3">
          <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-400">
            {t('admin.categories')}
          </h2>
          <button
            type="button"
            onClick={() => setEditingCategory('new')}
            className="h-10 rounded-xl bg-slate-900 px-4 text-sm font-semibold text-white"
          >
            + {t('admin.new_category')}
          </button>
        </header>

        <ul className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
          {categories.items.map((category) => (
            <li
              key={category.id}
              className="flex items-center gap-3 rounded-2xl bg-white p-3 ring-1 ring-slate-200"
            >
              <div className="min-w-0 flex-1">
                <p className="truncate font-medium text-slate-900">
                  {locale === 'ru' ? category.name_ru : category.name_uz}
                </p>
                <p className="text-xs text-slate-400">
                  {locale === 'ru' ? category.name_uz : category.name_ru}
                  {category.products_count !== undefined && ` · ${category.products_count}`}
                </p>
              </div>

              <button
                type="button"
                onClick={() => setEditingCategory(category)}
                className="h-10 rounded-lg px-3 text-sm text-slate-500 ring-1 ring-slate-200"
              >
                {t('common.edit')}
              </button>
              <button
                type="button"
                onClick={() => void removeCategory(category)}
                className="h-10 rounded-lg px-3 text-sm text-rose-600 ring-1 ring-rose-200"
              >
                {t('common.delete')}
              </button>
            </li>
          ))}
        </ul>
      </section>

      <section>
        <header className="mb-3 flex items-center justify-between gap-3">
          <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-400">
            {t('admin.products')}
          </h2>
          <button
            type="button"
            onClick={() => setEditingProduct('new')}
            disabled={categories.items.length === 0}
            className="h-10 rounded-xl bg-slate-900 px-4 text-sm font-semibold text-white disabled:bg-slate-300"
          >
            + {t('admin.new_product')}
          </button>
        </header>

        {products.items.length === 0 ? (
          <p className="rounded-2xl bg-white p-6 text-center text-sm text-slate-400 ring-1 ring-slate-200">
            {t('common.empty')}
          </p>
        ) : (
          <ul className="grid gap-2 lg:grid-cols-2">
            {products.items.map((product) => (
              <li
                key={product.id}
                className="flex items-center gap-3 rounded-2xl bg-white p-3 ring-1 ring-slate-200"
              >
                <div className="h-14 w-14 shrink-0 overflow-hidden rounded-xl bg-slate-100">
                  {product.image !== null && (
                    <img
                      src={product.image}
                      alt=""
                      loading="lazy"
                      className="h-full w-full object-cover"
                    />
                  )}
                </div>

                <div className="min-w-0 flex-1">
                  <p className="truncate font-medium text-slate-900">
                    {locale === 'ru' ? product.name_ru : product.name_uz}
                  </p>
                  <p className="text-sm text-slate-500">
                    {formatMoney(product.price, locale)}
                    {product.discount > 0 && ` · −${product.discount}%`}
                  </p>
                </div>

                <button
                  type="button"
                  onClick={() => void toggleAvailability(product)}
                  className={`h-10 rounded-lg px-3 text-xs font-medium ring-1 ${
                    product.is_available
                      ? 'text-emerald-700 ring-emerald-200'
                      : 'bg-slate-100 text-slate-500 ring-slate-200'
                  }`}
                >
                  {product.is_available ? t('admin.available') : t('admin.unavailable_short')}
                </button>
                <button
                  type="button"
                  onClick={() => setEditingProduct(product)}
                  className="h-10 rounded-lg px-3 text-sm text-slate-500 ring-1 ring-slate-200"
                >
                  {t('common.edit')}
                </button>
                <button
                  type="button"
                  onClick={() => void removeProduct(product)}
                  className="h-10 rounded-lg px-3 text-sm text-rose-600 ring-1 ring-rose-200"
                >
                  {t('common.delete')}
                </button>
              </li>
            ))}
          </ul>
        )}
      </section>

      {editingCategory !== null && (
        <CategoryForm
          category={editingCategory === 'new' ? null : editingCategory}
          onClose={() => setEditingCategory(null)}
          onSaved={() => {
            setEditingCategory(null);
            void categories.reload();
          }}
          describe={categories.describe}
        />
      )}

      {editingProduct !== null && (
        <ProductForm
          product={editingProduct === 'new' ? null : editingProduct}
          categories={categories.items}
          onClose={() => setEditingProduct(null)}
          onSaved={() => {
            setEditingProduct(null);
            void products.reload();
          }}
          describe={products.describe}
        />
      )}
    </div>
  );
}

function CategoryForm({
  category,
  onClose,
  onSaved,
  describe,
}: {
  category: Category | null;
  onClose: () => void;
  onSaved: () => void;
  describe: (error: unknown) => string;
}) {
  const { t } = useTranslation();
  const [form, setForm] = useState({
    name_uz: category?.name_uz ?? '',
    name_ru: category?.name_ru ?? '',
    slug: category?.slug ?? '',
    sort_order: category?.sort_order ?? 0,
    is_active: category?.is_active ?? true,
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [notice, setNotice] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async (event: React.FormEvent) => {
    event.preventDefault();
    setBusy(true);
    setErrors({});
    setNotice(null);

    try {
      if (category === null) {
        await api.post('/admin/categories', form);
      } else {
        await api.patch(`/admin/categories/${category.id}`, form);
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
      title={category === null ? t('admin.new_category') : t('admin.edit_category')}
      onClose={onClose}
      footer={
        <button
          type="submit"
          form="category-form"
          disabled={busy}
          className="h-12 w-full rounded-xl bg-slate-900 font-semibold text-white disabled:bg-slate-300"
        >
          {busy ? t('common.loading') : t('common.save')}
        </button>
      }
    >
      <form id="category-form" onSubmit={submit} className="space-y-4">
        {/* docs/02-I18N-RU-UZ.md §3 — ikkala til ham MAJBURIY. */}
        <Field label={t('admin.name_uz')} error={errors.name_uz} required>
          <input
            value={form.name_uz}
            onChange={(event) => setForm({ ...form, name_uz: event.target.value })}
            required
            className={inputClass}
          />
        </Field>

        <Field label={t('admin.name_ru')} error={errors.name_ru} required>
          <input
            value={form.name_ru}
            onChange={(event) => setForm({ ...form, name_ru: event.target.value })}
            required
            className={inputClass}
          />
        </Field>

        <Field label={t('admin.slug')} error={errors.slug} required>
          <input
            value={form.slug}
            onChange={(event) => setForm({ ...form, slug: event.target.value })}
            required
            pattern="[A-Za-z0-9_\-]+"
            className={inputClass}
          />
        </Field>

        <Field label={t('admin.sort_order')} error={errors.sort_order}>
          <input
            type="number"
            inputMode="numeric"
            value={form.sort_order}
            onChange={(event) => setForm({ ...form, sort_order: Number(event.target.value) })}
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

function ProductForm({
  product,
  categories,
  onClose,
  onSaved,
  describe,
}: {
  product: Product | null;
  categories: Category[];
  onClose: () => void;
  onSaved: () => void;
  describe: (error: unknown) => string;
}) {
  const { t, locale } = useTranslation();

  const [form, setForm] = useState({
    category_id: product?.category_id ?? categories[0]?.id ?? 0,
    name_uz: product?.name_uz ?? '',
    name_ru: product?.name_ru ?? '',
    description_uz: product?.description_uz ?? '',
    description_ru: product?.description_ru ?? '',
    price: product?.price ?? 0,
    discount: product?.discount ?? 0,
    weight: product?.weight ?? '',
    preparation_time: product?.preparation_time ?? '',
    is_available: product?.is_available ?? true,
  });

  const [errors, setErrors] = useState<Record<string, string>>({});
  const [notice, setNotice] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [preview, setPreview] = useState<string | null>(product?.image ?? null);
  const [file, setFile] = useState<File | null>(null);
  const [progress, setProgress] = useState<number | null>(null);

  const pickImage = (event: React.ChangeEvent<HTMLInputElement>) => {
    const picked = event.target.files?.[0] ?? null;
    setFile(picked);

    // Darhol ko'rinadi — yuklashni kutmaydi.
    if (picked !== null) setPreview(URL.createObjectURL(picked));
  };

  const submit = async (event: React.FormEvent) => {
    event.preventDefault();
    setBusy(true);
    setErrors({});
    setNotice(null);

    const payload = {
      ...form,
      weight: form.weight === '' ? null : Number(form.weight),
      preparation_time: form.preparation_time === '' ? null : Number(form.preparation_time),
      description_uz: form.description_uz === '' ? null : form.description_uz,
      description_ru: form.description_ru === '' ? null : form.description_ru,
    };

    try {
      const id =
        product === null
          ? (await api.post<{ product: Product }>('/admin/products', payload)).data.product.id
          : (await api.patch<{ product: Product }>(`/admin/products/${product.id}`, payload)).data
              .product.id;

      if (file !== null) {
        setProgress(0);
        await uploadFile(`/admin/products/${id}/image`, file, 'image', setProgress);
      }

      onSaved();
    } catch (caught) {
      setErrors(fieldErrors(caught));
      setNotice(describe(caught));
    } finally {
      setBusy(false);
      setProgress(null);
    }
  };

  return (
    <Modal
      title={product === null ? t('admin.new_product') : t('admin.edit_product')}
      onClose={onClose}
      footer={
        <button
          type="submit"
          form="product-form"
          disabled={busy}
          className="h-12 w-full rounded-xl bg-slate-900 font-semibold text-white disabled:bg-slate-300"
        >
          {busy ? t('common.loading') : t('common.save')}
        </button>
      }
    >
      <form id="product-form" onSubmit={submit} className="space-y-4">
        <Field label={t('admin.category')} error={errors.category_id} required>
          <select
            value={form.category_id}
            onChange={(event) => setForm({ ...form, category_id: Number(event.target.value) })}
            className={inputClass}
          >
            {categories.map((category) => (
              <option key={category.id} value={category.id}>
                {locale === 'ru' ? category.name_ru : category.name_uz}
              </option>
            ))}
          </select>
        </Field>

        <Field label={t('admin.name_uz')} error={errors.name_uz} required>
          <input
            value={form.name_uz}
            onChange={(event) => setForm({ ...form, name_uz: event.target.value })}
            required
            className={inputClass}
          />
        </Field>

        <Field label={t('admin.name_ru')} error={errors.name_ru} required>
          <input
            value={form.name_ru}
            onChange={(event) => setForm({ ...form, name_ru: event.target.value })}
            required
            className={inputClass}
          />
        </Field>

        <Field label={t('admin.description_uz')} error={errors.description_uz}>
          <textarea
            value={form.description_uz}
            onChange={(event) => setForm({ ...form, description_uz: event.target.value })}
            rows={2}
            className={`${inputClass} h-auto py-2`}
          />
        </Field>

        <Field label={t('admin.description_ru')} error={errors.description_ru}>
          <textarea
            value={form.description_ru}
            onChange={(event) => setForm({ ...form, description_ru: event.target.value })}
            rows={2}
            className={`${inputClass} h-auto py-2`}
          />
        </Field>

        <div className="grid grid-cols-2 gap-3">
          <Field label={t('admin.price')} error={errors.price} required>
            <input
              type="number"
              inputMode="decimal"
              min={0}
              step={100}
              value={form.price}
              onChange={(event) => setForm({ ...form, price: Number(event.target.value) })}
              required
              className={inputClass}
            />
          </Field>

          <Field label={t('admin.discount_percent')} error={errors.discount}>
            <input
              type="number"
              inputMode="numeric"
              min={0}
              max={100}
              value={form.discount}
              onChange={(event) => setForm({ ...form, discount: Number(event.target.value) })}
              className={inputClass}
            />
          </Field>

          <Field label={t('admin.weight')} error={errors.weight}>
            <input
              type="number"
              inputMode="numeric"
              min={0}
              value={form.weight}
              onChange={(event) => setForm({ ...form, weight: event.target.value })}
              className={inputClass}
            />
          </Field>

          <Field label={t('admin.prep_time')} error={errors.preparation_time}>
            <input
              type="number"
              inputMode="numeric"
              min={0}
              value={form.preparation_time}
              onChange={(event) => setForm({ ...form, preparation_time: event.target.value })}
              className={inputClass}
            />
          </Field>
        </div>

        <Field label={t('admin.image')}>
          <div className="flex items-center gap-3">
            <div className="h-16 w-16 shrink-0 overflow-hidden rounded-xl bg-slate-100">
              {preview !== null && <img src={preview} alt="" className="h-full w-full object-cover" />}
            </div>

            <label className="flex h-11 flex-1 cursor-pointer items-center justify-center rounded-xl bg-slate-50 text-sm font-medium text-slate-600 ring-1 ring-slate-200">
              {t('admin.upload_image')}
              <input
                type="file"
                accept="image/jpeg,image/png,image/webp"
                onChange={pickImage}
                className="sr-only"
              />
            </label>
          </div>

          {progress !== null && (
            <div className="mt-2">
              <div className="h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                <div
                  className="h-full bg-slate-900 transition-all"
                  style={{ width: `${progress}%` }}
                />
              </div>
              <p className="mt-1 text-xs text-slate-400">
                {t('admin.uploading')} {progress}%
              </p>
            </div>
          )}
        </Field>

        <label className="flex items-center gap-3">
          <input
            type="checkbox"
            checked={form.is_available}
            onChange={(event) => setForm({ ...form, is_available: event.target.checked })}
            className="h-5 w-5 rounded"
          />
          <span className="text-sm text-slate-700">{t('admin.available')}</span>
        </label>

        {notice !== null && (
          <p className="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-900">{notice}</p>
        )}

      </form>
    </Modal>
  );
}
