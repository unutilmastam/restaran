import { create } from 'zustand';

/**
 * Cart FAQAT frontendda yashaydi va localStorage'da saqlanadi.
 *
 * ⚠️ Narx SAQLANMAYDI — faqat `product_id` va `quantity`. Order submit'da
 * narx DB'dan qayta hisoblanadi (CLAUDE.md §2.6, §2.7), shuning uchun
 * cartdagi eskirgan narx hech qachon hisobga olinmaydi. Ko'rsatiladigan
 * summa har doim yangi menyu ma'lumotidan hisoblanadi.
 *
 * Kalit `nfc_token` bo'yicha alohida: mijoz boshqa stolga o'tsa yoki
 * telefon boshqa stolda ishlatilsa cartlar ARALASHMAYDI.
 */
export interface CartLine {
  productId: number;
  quantity: number;
}

interface CartState {
  tableKey: string | null;
  lines: CartLine[];
  load: (nfcToken: string) => void;
  add: (productId: number) => void;
  remove: (productId: number) => void;
  setQuantity: (productId: number, quantity: number) => void;
  clear: () => void;
  quantityOf: (productId: number) => number;
  totalItems: () => number;
}

const MAX_QUANTITY = 99;

function storageKey(nfcToken: string): string {
  return `cart:${nfcToken}`;
}

function read(nfcToken: string): CartLine[] {
  try {
    const raw = window.localStorage.getItem(storageKey(nfcToken));
    if (!raw) return [];

    const parsed: unknown = JSON.parse(raw);
    if (!Array.isArray(parsed)) return [];

    return parsed.filter(
      (line): line is CartLine =>
        typeof line === 'object' &&
        line !== null &&
        typeof (line as CartLine).productId === 'number' &&
        typeof (line as CartLine).quantity === 'number' &&
        (line as CartLine).quantity > 0,
    );
  } catch {
    // Buzilgan JSON yoki bloklangan storage — bo'sh cart bilan davom etamiz.
    return [];
  }
}

function write(nfcToken: string, lines: CartLine[]): void {
  try {
    window.localStorage.setItem(storageKey(nfcToken), JSON.stringify(lines));
  } catch {
    // Storage to'lgan yoki bloklangan — cart faqat xotirada qoladi.
  }
}

export const useCart = create<CartState>((set, get) => ({
  tableKey: null,
  lines: [],

  load: (nfcToken) => set({ tableKey: nfcToken, lines: read(nfcToken) }),

  setQuantity: (productId, quantity) => {
    const { tableKey, lines } = get();
    const safe = Math.max(0, Math.min(MAX_QUANTITY, Math.trunc(quantity)));

    const next =
      safe === 0
        ? lines.filter((line) => line.productId !== productId)
        : lines.some((line) => line.productId === productId)
          ? lines.map((line) => (line.productId === productId ? { ...line, quantity: safe } : line))
          : [...lines, { productId, quantity: safe }];

    if (tableKey) write(tableKey, next);
    set({ lines: next });
  },

  add: (productId) => get().setQuantity(productId, get().quantityOf(productId) + 1),

  remove: (productId) => get().setQuantity(productId, get().quantityOf(productId) - 1),

  clear: () => {
    const { tableKey } = get();
    if (tableKey) write(tableKey, []);
    set({ lines: [] });
  },

  quantityOf: (productId) => get().lines.find((line) => line.productId === productId)?.quantity ?? 0,

  totalItems: () => get().lines.reduce((sum, line) => sum + line.quantity, 0),
}));
