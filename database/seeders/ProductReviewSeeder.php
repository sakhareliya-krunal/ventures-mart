<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Database\Seeder;

class ProductReviewSeeder extends Seeder
{
    /** @var list<string> */
    private array $authors = [
        'Ananya S.',
        'Rohan M.',
        'Priya K.',
        'Vikram D.',
        'Neha P.',
        'Arjun T.',
        'Sneha R.',
        'Karan J.',
        'Meera L.',
        'Aditya N.',
        'Ishita B.',
        'Devansh G.',
    ];

    /** @var list<string> */
    private array $bodies = [
        'Really happy with the quality for the price. Packing was neat and delivery was smooth.',
        'Bought this for my kids and they love it. Looks exactly like the photos on the site.',
        'Solid everyday pick. Feels sturdy and the finish is better than I expected.',
        'Good product overall. Colour matched what I ordered and it arrived without any damage.',
        'Useful and well made. Would order again from Ventures Mart without hesitation.',
        'Nice build and practical for daily use. Customer support replied quickly when I asked a question.',
        'Value for money. The details are thoughtful and it holds up well with regular use.',
        'Exactly what we needed. Clean packaging and the item feels premium for this range.',
        'Impressed with the quality. A few small scuffs on the box but the product itself was perfect.',
        'Works well and looks great. Sharing a quick note because I was unsure before ordering.',
    ];

    public function run(): void
    {
        ProductReview::query()->delete();

        Product::query()->orderBy('id')->each(function (Product $product): void {
            $seed = crc32((string) $product->slug);
            $count = 2 + ($seed % 4); // 2–5 reviews

            for ($i = 0; $i < $count; $i++) {
                $ratingPool = [5, 5, 5, 4, 4, 4, 3];
                $rating = $ratingPool[($seed + ($i * 17)) % count($ratingPool)];

                ProductReview::query()->create([
                    'product_id' => $product->id,
                    'user_id' => null,
                    'author_name' => $this->authors[($seed + ($i * 3)) % count($this->authors)],
                    'rating' => $rating,
                    'body' => $this->bodies[($seed + ($i * 5)) % count($this->bodies)],
                ]);
            }

            $product->refreshReviewAggregates();
        });
    }
}
