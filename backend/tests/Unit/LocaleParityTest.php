<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * docs/02-I18N-RU-UZ.md §10 — `lang/ru` va `lang/uz` kalitlari 1:1 mos
 * bo'lishi SHART. Bitta tilga kalit qo'shib, ikkinchisini unutish —
 * eng ko'p uchraydigan i18n xatosi, shuning uchun test PHASE 1 da yoziladi.
 */
class LocaleParityTest extends TestCase
{
    /** @return list<array{string}> */
    public static function localeFiles(): array
    {
        $files = glob(__DIR__.'/../../lang/uz/*.php') ?: [];

        return array_map(fn (string $path) => [basename($path)], $files);
    }

    #[DataProvider('localeFiles')]
    public function test_ru_and_uz_files_have_identical_keys(string $file): void
    {
        $ru = $this->flatten(require __DIR__."/../../lang/ru/{$file}");
        $uz = $this->flatten(require __DIR__."/../../lang/uz/{$file}");

        sort($ru);
        sort($uz);

        $this->assertSame($ru, $uz, sprintf(
            "lang/ru/%s va lang/uz/%s kalitlari mos emas.\nFaqat ru da: %s\nFaqat uz da: %s",
            $file,
            $file,
            implode(', ', array_diff($ru, $uz)) ?: '—',
            implode(', ', array_diff($uz, $ru)) ?: '—',
        ));
    }

    public function test_both_locales_have_the_same_file_list(): void
    {
        $ru = array_map('basename', glob(__DIR__.'/../../lang/ru/*.php') ?: []);
        $uz = array_map('basename', glob(__DIR__.'/../../lang/uz/*.php') ?: []);

        sort($ru);
        sort($uz);

        $this->assertSame($ru, $uz, 'lang/ru va lang/uz fayl ro\'yxati mos emas.');
        $this->assertNotEmpty($ru, 'Til fayllari topilmadi.');
    }

    public function test_no_translation_value_is_left_empty(): void
    {
        foreach (['ru', 'uz'] as $locale) {
            foreach (glob(__DIR__."/../../lang/{$locale}/*.php") ?: [] as $path) {
                foreach ($this->flatten(require $path, '', true) as $key => $value) {
                    $this->assertNotSame('', trim((string) $value), sprintf(
                        '%s/%s: "%s" kaliti bo\'sh.', $locale, basename($path), $key
                    ));
                }
            }
        }
    }

    /**
     * @param  array<mixed>  $array
     * @return array<int|string, mixed>
     */
    private function flatten(array $array, string $prefix = '', bool $withValues = false): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $full = $prefix.$key;

            if (is_array($value)) {
                $result = array_merge($result, $this->flatten($value, $full.'.', $withValues));

                continue;
            }

            if ($withValues) {
                $result[$full] = $value;
            } else {
                $result[] = $full;
            }
        }

        return $result;
    }
}
