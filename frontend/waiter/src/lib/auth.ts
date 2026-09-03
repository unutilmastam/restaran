import { create } from 'zustand';

/**
 * Sanctum tokeni. `localStorage` da saqlanadi — admin panel
 * desktop/kassada ochiq turadi va sahifa yangilanganda qayta login
 * so'ralmasligi kerak.
 */
const KEY = 'waiter_token';

export interface WaiterUser {
  id: number;
  name: string;
  username: string;
  role: 'WAITER';
  status: 'FREE' | 'BUSY' | 'OFFLINE' | null;
  locale: 'uz' | 'ru';
  restaurant: {
    name: string;
    slug: string;
    logo: string | null;
    currency: string;
    subscription_status: string;
    expires_at: string | null;
  } | null;
}

interface AuthState {
  token: string | null;
  user: WaiterUser | null;
  setSession: (token: string, user: WaiterUser) => void;
  setUser: (user: WaiterUser) => void;
  clear: () => void;
}

export function readToken(): string | null {
  try {
    return window.localStorage.getItem(KEY);
  } catch {
    return null;
  }
}

export const useAuth = create<AuthState>((set) => ({
  token: readToken(),
  user: null,

  setSession: (token, user) => {
    try {
      window.localStorage.setItem(KEY, token);
    } catch {
      // Storage bloklangan — sessiya faqat shu tab uchun.
    }

    set({ token, user });
  },

  setUser: (user) => set({ user }),

  clear: () => {
    try {
      window.localStorage.removeItem(KEY);
    } catch {
      // e'tiborsiz
    }

    set({ token: null, user: null });
  },
}));
