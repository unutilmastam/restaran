interface Props {
  src: string | null;
  alt: string;
  className?: string;
}

/**
 * Rasm yo'q mahsulot uchun placeholder.
 *
 * Yangi restoran BO'SH MENYU bilan boshlanadi (docs/06-SAAS.md §9) va
 * egasi hamma mahsulotga rasm qo'ymasligi mumkin — shuning uchun rasmsiz
 * holat istisno emas, ODATIY hol.
 *
 * Placeholder inline SVG: qo'shimcha so'rov ham, fayl ham talab qilmaydi.
 */
export function ProductImage({ src, alt, className = '' }: Props) {
  if (src === null) {
    return (
      <div
        role="img"
        aria-label={alt}
        className={`flex items-center justify-center bg-slate-100 ${className}`}
      >
        <svg
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="1.5"
          className="h-8 w-8 text-slate-300"
          aria-hidden="true"
        >
          <path d="M3 7h18v13H3z" strokeLinejoin="round" />
          <path d="m3 16 5-4 4 3 3-2 6 4" strokeLinejoin="round" />
          <circle cx="8.5" cy="10.5" r="1.5" />
        </svg>
      </div>
    );
  }

  return (
    <img
      src={src}
      alt={alt}
      // Mobil internet — ro'yxatdagi rasmlar faqat kerak bo'lganda yuklanadi.
      loading="lazy"
      decoding="async"
      className={`object-cover ${className}`}
    />
  );
}
