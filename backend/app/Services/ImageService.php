<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Rasm yuklash — docs/01-ARCHITECTURE.md §13 + 1 GB disk byudjeti.
 *
 * Har bir rasm webp'ga o'giriladi va o'lchami cheklanadi. Original
 * SAQLANMAYDI: cPanel'da 1 GB disk bor, telefondan kelgan 4 MB'lik
 * JPEG'lar uni bir necha kunda to'ldirardi.
 *
 * Yo'l: `storage/app/public/products/{restaurant_id}/...` — restoranlar
 * fayllari ham ajratilgan (docs/06-SAAS.md §10.4).
 */
class ImageService
{
    private readonly ImageManager $manager;

    public function __construct(?ImageManager $manager = null)
    {
        // GD drayveri — cPanel'da Imagick odatda yo'q, GD esa har doim bor.
        $this->manager = $manager ?? new ImageManager(new Driver);
    }

    public function storeProductImage(UploadedFile $file, int $restaurantId, ?string $previous = null): string
    {
        $this->guard($file);

        $config = config('smart_restaurant.image');
        /*
         * ⚠️ Dekodlash xatosi 500 ga aylanmasligi kerak.
         *
         * MIME tekshiruvi yetarli emas: `.png` deb nomlangan fayl
         * (yoki shunchaki buzilgan rasm) dekoderda istisno tashlaydi.
         * Foydalanuvchi 500 emas, tushunarli 422 ko'rishi kerak.
         */
        try {
            // v4 da `read()` emas, `decodePath()`.
            $image = $this->manager->decodePath($file->getRealPath());
        } catch (Throwable) {
            throw new BusinessException('VALIDATION_FAILED', 422);
        }

        // Nisbatni saqlab kichraytiramiz; kichik rasm kattalashtirilmaydi.
        $image->scaleDown(width: $config['max_width'], height: $config['max_width']);

        // Sifatni pasaytirib hajm chegarasiga tushamiz.
        $encoded = null;

        foreach ([85, 70, 55, 40] as $quality) {
            $encoded = $image->encode(new WebpEncoder(quality: $quality));

            if (strlen((string) $encoded) <= $config['max_kb'] * 1024) {
                break;
            }
        }

        $path = sprintf('products/%d/%s.webp', $restaurantId, Str::random(24));
        Storage::disk('public')->put($path, (string) $encoded);

        // Eski rasm darhol o'chadi — disk to'lib qolmasin.
        if ($previous !== null && $previous !== '') {
            Storage::disk('public')->delete($previous);
        }

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * ⚠️ MIME kengaytmadan EMAS, fayl MAZMUNIDAN aniqlanadi.
     * `.png` deb nomlangan PHP skript yuklanmasligi uchun.
     */
    private function guard(UploadedFile $file): void
    {
        $config = config('smart_restaurant.image');

        if (! $file->isValid()) {
            throw new BusinessException('VALIDATION_FAILED', 422);
        }

        if (! in_array($file->getMimeType(), $config['mimes'], true)) {
            throw new BusinessException('VALIDATION_FAILED', 422);
        }

        // Yuklashdan oldingi chegara: 5 MB. Undan keyin webp'ga
        // siqilib `max_kb` gacha tushadi.
        if ($file->getSize() > 5 * 1024 * 1024) {
            throw new BusinessException('VALIDATION_FAILED', 422);
        }
    }
}
