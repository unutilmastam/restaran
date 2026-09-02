/**
 * Backend API Resource'lariga mos turlar.
 * Manba: docs/05-PHASE0-PLAN.md §3 (API contract).
 *
 * DIQQAT: customer javoblarida `restaurant_id`, `waiter_id`, ichki
 * `session_id`/`table_id` HECH QACHON kelmaydi (docs/01 §13) — shuning
 * uchun ular bu turlarda ham yo'q.
 */

export type Locale = 'uz' | 'ru';

/** docs/01-ARCHITECTURE.md §9 — barcha javoblarning yagona konverti. */
export interface ApiEnvelope<T> {
  success: boolean;
  data: T;
  message_ru: string | null;
  message_uz: string | null;
  error_code: string | null;
}

/** docs/02-I18N-RU-UZ.md §6 — xato kodlari lug'ati. */
export type ErrorCode =
  // SaaS — docs/06-SAAS.md
  | 'LIMIT_EXCEEDED'
  | 'SUBSCRIPTION_EXPIRED'
  | 'SUBSCRIPTION_SUSPENDED'
  | 'RESTAURANT_UNAVAILABLE'
  | 'RESTAURANT_ARCHIVED'
  | 'OWNER_ADMIN_PROTECTED'
  | 'CONFIRMATION_MISMATCH'
  | 'PRODUCT_UNAVAILABLE'
  | 'SESSION_NOT_FOUND'
  | 'ORDER_NOT_DELIVERED'
  | 'SESSION_WAITING_PAYMENT'
  | 'NO_FREE_WAITER'
  | 'NETWORK_ERROR'
  | 'ORDER_DUPLICATE'
  | 'INVALID_TABLE'
  | 'INVALID_STATUS_TRANSITION'
  | 'PRICE_CHANGED'
  | 'VALIDATION_FAILED'
  | 'UNAUTHENTICATED'
  | 'FORBIDDEN'
  | 'NOT_FOUND'
  | 'TOO_MANY_REQUESTS'
  | 'SERVER_ERROR';

/** docs/01 §3 + docs/05 §2.4 (DRAFT qo'shildi). */
export type OrderStatus =
  | 'DRAFT'
  | 'PENDING'
  | 'ACCEPTED'
  | 'WAITING_FOR_WAITER'
  | 'ASSIGNED'
  | 'WAITER_ACCEPTED'
  | 'DELIVERING'
  | 'DELIVERED'
  | 'CANCELLED'
  | 'EXPIRED';

export type SessionStatus = 'ACTIVE' | 'WAITING_PAYMENT' | 'PAID' | 'CLOSED';

export type TableStatus =
  | 'AVAILABLE'
  | 'ACTIVE'
  | 'ORDER_PENDING'
  | 'WAITER_ASSIGNED'
  | 'DELIVERED'
  | 'WAITING_PAYMENT';

export type WaiterStatus = 'FREE' | 'BUSY' | 'OFFLINE';
export type PaymentMethod = 'CASH' | 'CARD' | 'OTHER';
export type PaymentStatus = 'PENDING' | 'PAID' | 'REFUNDED';
/**
 * docs/06-SAAS.md §1. `OWNER_ADMIN` — restoranning birinchi admini:
 * o'chirib bo'lmaydi, rolini faqat SUPER_ADMIN o'zgartira oladi.
 * `SUPER_ADMIN` da `restaurant_id = null` — u hech qaysi restoranga
 * tegishli emas.
 */
export type UserRole = 'SUPER_ADMIN' | 'OWNER_ADMIN' | 'ADMIN' | 'WAITER';

/** docs/06-SAAS.md §3 — kunlik scheduler hisoblaydi. */
export type SubscriptionStatus = 'TRIAL' | 'ACTIVE' | 'EXPIRING' | 'EXPIRED' | 'SUSPENDED';

export type PaymentConfirmMethod = 'CLICK' | 'CASH' | 'TRANSFER' | 'OTHER';

export interface Plan {
  id: number;
  code: 'MONTHLY' | 'QUARTERLY' | 'YEARLY' | string;
  name: string;
  days: number;
  price: string;
  is_active: boolean;
}

/**
 * To'lov tarixi. Summa va tarif nomi SNAPSHOT — `plans` o'zgarsa ham bu
 * yozuv o'zgarmaydi (docs/06-SAAS.md §2), shuning uchun UI hech qachon
 * `Plan` ga bog'lanmaydi.
 */
export interface SubscriptionPayment {
  id: number;
  amount: string;
  plan_code_snapshot: string;
  plan_name_snapshot: string;
  plan_days_snapshot: number;
  method: PaymentConfirmMethod;
  reference: string | null;
  paid_at: string;
}

/** Admin panelidagi "To'lov" sahifasi uchun (docs/06-SAAS.md §5). */
export interface SubscriptionState {
  status: SubscriptionStatus;
  expires_at: string;
  days_left: number;
  /** Grace period ichida panel read-only ishlaydi (§4). */
  is_read_only: boolean;
  plans: Plan[];
  contact: {
    phone: string | null;
    telegram: string | null;
    note: string | null;
  };
}

/** Kategoriya va mahsulot nomlari serverda `Accept-Language` bo'yicha tanlanadi. */
export interface Category {
  id: number;
  name: string;
  slug: string;
  image: string | null;
  sort_order: number;
}

export interface Product {
  id: number;
  category_id: number;
  name: string;
  description: string | null;
  image: string | null;
  price: string;
  weight: number | null;
  preparation_time: number | null;
  is_available: boolean;
}

export interface TableInfo {
  number: number;
  name: string | null;
  capacity: number;
  status: TableStatus;
}

export interface SessionInfo {
  public_id: string;
  status: SessionStatus;
  guest_count: number;
  total_amount: string;
  opened_at: string;
}

export interface OrderItem {
  product_id: number;
  name: string;
  price: string;
  quantity: number;
  subtotal: string;
}

export interface Order {
  id: number;
  order_number: string;
  status: OrderStatus;
  subtotal: string;
  discount: string;
  total: string;
  comment: string | null;
  items: OrderItem[];
  created_at: string;
}

/** Cart faqat frontendda yashaydi — narx hech qachon serverga yuborilmaydi. */
export interface CartLine {
  product_id: number;
  quantity: number;
}
