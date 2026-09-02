import { create } from 'zustand';

/**
 * Sanctum tokeni. `localStorage` da saqlanadi — admin panel
 * desktop/kassada ochiq turadi va sahifa yangilanganda qayta login
 * so'ralmasligi kerak.
 */
const KEY = 'admin_token';

export interface AdminUser {
  id: number;
  name: string;
  username: string;
  role: 'OWNER_ADMIN' | 'ADMIN';
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
  user: AdminUser | null;
  setSession: (token: string, user: AdminUser) => void;
  setUser: (user: AdminUser) => void;
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
