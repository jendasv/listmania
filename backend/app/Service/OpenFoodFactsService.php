<?php

declare(strict_types=1);

namespace App\Service;

use App\Models\GlobalProduct;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenFoodFactsService
{
    private const DATABASES = [
        'open_food_facts' => 'https://world.openfoodfacts.org/api/v2/product/',
        'open_beauty_facts' => 'https://world.openbeautyfacts.org/api/v2/product/',
        'open_products_facts' => 'https://world.openproductsfacts.org/api/v2/product/',
        'open_pet_food_facts' => 'https://world.openpetfoodfacts.org/api/v2/product/',
    ];

    /** Retail product codes (EAN-8, UPC-A, EAN-13, GTIN-14) — the only thing the Open*Facts APIs know. */
    private const GTIN_PATTERN = '/^\d{8,14}$/';

    /** A barcode none of the databases know stays unknown for a while — don't re-ask on every scan. */
    private const MISS_TTL_SECONDS = 86400;

    public function fetchAndStore(string $barcode): ?GlobalProduct
    {
        // The barcode becomes part of the request path: anything other than
        // digits (e.g. '../') would let a user steer the outbound request.
        if (preg_match(self::GTIN_PATTERN, $barcode) !== 1) {
            return null;
        }

        $missKey = 'openfacts:miss:'.$barcode;
        if (Cache::has($missKey)) {
            return null;
        }

        try {
            $responses = Http::pool(function (Pool $pool) use ($barcode) {
                $requests = [];
                foreach (self::DATABASES as $source => $url) {
                    $requests[] = $pool->as($source)
                        ->timeout(5)
                        ->withUserAgent('ShoppingListApp/1.0')
                        ->get($url.$barcode.'.json');
                }

                return $requests;
            });

            $everyServiceAnswered = true;

            foreach (self::DATABASES as $source => $_) {
                $response = $responses[$source];

                // Http::pool() returns the exception itself (not a Response) for a
                // request that failed to connect or timed out — one slow service
                // must not abort the lookup for the others that did respond.
                if (! $response instanceof Response) {
                    $everyServiceAnswered = false;

                    continue;
                }

                if (! $response->ok()) {
                    $everyServiceAnswered = $everyServiceAnswered && $response->status() === 404;

                    continue;
                }

                $data = $response->json();

                if (($data['status'] ?? 0) !== 1 || empty($data['product'])) {
                    continue;
                }

                $product = $data['product'];
                $name = $product['product_name'] ?? $product['product_name_en'] ?? null;

                if (empty($name)) {
                    continue;
                }

                return GlobalProduct::firstOrCreate(
                    ['barcode' => $barcode],
                    [
                        'name' => $name,
                        'brand' => $product['brands'] ?? null,
                        'image_url' => $product['image_front_small_url'] ?? $product['image_url'] ?? null,
                        'source' => $source,
                        'verified' => false,
                        'scan_count' => 0,
                    ],
                );
            }

            // Only a definitive "not found" from every service is cached — a
            // timeout is transient and the next scan should try again.
            if ($everyServiceAnswered) {
                Cache::put($missKey, true, self::MISS_TTL_SECONDS);
            }

            return null;
        } catch (\Throwable $e) {
            Log::warning('OpenFacts lookup failed', ['barcode' => $barcode, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
