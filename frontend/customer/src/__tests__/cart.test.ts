import { beforeEach, describe, expect, it } from 'vitest';

import { useCart } from '../store/cart';

/**
 * Cart faqat frontendda yashaydi. Eng muhim ikki xossa:
 *   1. Narx SAQLANMAYDI (CLAUDE.md §2.6) — faqat product_id va quantity
 *   2. Kalit nfc_token bo'yicha alohida — stollar aralashmaydi
 */
describe('cart store', () => {
  beforeEach(() => {
    window.localStorage.clear();
    useCart.setState({ tableKey: null, lines: [] });
  });

  it('adds and increments a product', () => {
    const cart = useCart.getState();
    cart.load('table-a');

    cart.add(7);
    cart.add(7);
    cart.add(9);

    expect(useCart.getState().lines).toEqual([
      { productId: 7, quantity: 2 },
      { productId: 9, quantity: 1 },
    ]);
    expect(useCart.getState().totalItems()).toBe(3);
  });

  it('removes a line when its quantity reaches zero', () => {
    const cart = useCart.getState();
    cart.load('table-a');

    cart.add(7);
    cart.remove(7);

    expect(useCart.getState().lines).toEqual([]);
    expect(useCart.getState().quantityOf(7)).toBe(0);
  });

  it('never stores a price — only product id and quantity', () => {
    const cart = useCart.getState();
    cart.load('table-a');
    cart.add(7);

    const raw = window.localStorage.getItem('cart:table-a') ?? '';

    expect(raw).toContain('productId');
    expect(raw).toContain('quantity');
    expect(raw).not.toContain('price');
  });

  it('keeps each table in its own storage key', () => {
    const cart = useCart.getState();

    cart.load('table-a');
    cart.add(1);

    cart.load('table-b');
    expect(useCart.getState().lines).toEqual([]);

    cart.add(2);

    cart.load('table-a');
    expect(useCart.getState().lines).toEqual([{ productId: 1, quantity: 1 }]);

    expect(window.localStorage.getItem('cart:table-a')).not.toBeNull();
    expect(window.localStorage.getItem('cart:table-b')).not.toBeNull();
  });

  it('survives a reload from localStorage', () => {
    useCart.getState().load('table-a');
    useCart.getState().add(3);
    useCart.getState().add(3);

    useCart.setState({ tableKey: null, lines: [] });
    useCart.getState().load('table-a');

    expect(useCart.getState().lines).toEqual([{ productId: 3, quantity: 2 }]);
  });

  it('ignores corrupted storage instead of crashing', () => {
    window.localStorage.setItem('cart:table-a', 'not json at all');

    useCart.getState().load('table-a');

    expect(useCart.getState().lines).toEqual([]);
  });

  it('drops malformed lines', () => {
    window.localStorage.setItem(
      'cart:table-a',
      JSON.stringify([{ productId: 1, quantity: 2 }, { productId: 'x' }, { quantity: 0 }]),
    );

    useCart.getState().load('table-a');

    expect(useCart.getState().lines).toEqual([{ productId: 1, quantity: 2 }]);
  });

  it('clamps the quantity and rejects fractions', () => {
    const cart = useCart.getState();
    cart.load('table-a');

    cart.setQuantity(1, 500);
    expect(useCart.getState().quantityOf(1)).toBe(99);

    cart.setQuantity(1, 2.7);
    expect(useCart.getState().quantityOf(1)).toBe(2);

    cart.setQuantity(1, -5);
    expect(useCart.getState().quantityOf(1)).toBe(0);
  });

  it('clears the cart', () => {
    const cart = useCart.getState();
    cart.load('table-a');
    cart.add(1);
    cart.add(2);

    cart.clear();

    expect(useCart.getState().lines).toEqual([]);
    expect(window.localStorage.getItem('cart:table-a')).toBe('[]');
  });
});
