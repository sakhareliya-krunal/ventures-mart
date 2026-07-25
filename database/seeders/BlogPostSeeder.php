<?php

namespace Database\Seeders;

use App\Models\Post;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class BlogPostSeeder extends Seeder
{
    public function run(): void
    {
        Post::query()->delete();

        $posts = [
            [
                'title' => 'Packing a school lunch box kids actually open',
                'slug' => 'packing-school-lunch-box-kids-open',
                'excerpt' => 'Simple packing habits that keep Indian school lunches fresh, inviting, and easy for little hands to manage.',
                'cover_image' => '/products/lunch-box/hero-showcase/koi-koi-steel-lunch-box-blue.jpeg',
                'published_at' => Carbon::parse('2026-07-02 10:00:00'),
                'body' => <<<'HTML'
<p>A packed lunch only works if your child opens it. In Indian school routines—morning rush, short breaks, shared classrooms—the difference between a finished tiffin and a returned one is usually packing, not recipes.</p>
<h2>Start with compartments that make sense</h2>
<p>Give each food a clear place: roti or rice in one section, sabzi or dal in another, and a small treat or fruit in the last. When everything is jumbled, kids dig around and often give up. A well-designed <a href="/category/lunch-box">lunch box</a> with secure lids keeps flavours separate and spills rare.</p>
<h2>Pack for the break, not the fridge</h2>
<p>Think about what still tastes good at room temperature by late morning. Soft rotis, mild sabzis, cut fruit, and dry snacks travel better than heavy gravies. If something needs reheating, skip it for school days.</p>
<h2>Make it easy to open</h2>
<p>Clip lids should be firm enough for bags, soft enough for small fingers. Test the latch at home once. A lunch that needs adult help rarely gets finished on a busy playground break.</p>
<h2>Keep portions realistic</h2>
<p>Overpacking looks caring but overwhelms. Match the portion to your child’s appetite and the length of their break. Empty boxes build confidence; overflowing ones get left behind.</p>
<p>Browse our curated <a href="/category/lunch-box">lunch box collection</a> for steel and everyday options built for school bags—or start from the full <a href="/shop">shop</a>.</p>
HTML,
            ],
            [
                'title' => 'Steel vs plastic tiffins for Indian school days',
                'slug' => 'steel-vs-plastic-tiffins-school',
                'excerpt' => 'A practical comparison for parents choosing everyday lunch boxes—durability, taste, weight, and what lasts a school year.',
                'cover_image' => '/products/lunch-box/delicious-steel-lunch-box/13.jpg',
                'published_at' => Carbon::parse('2026-07-05 10:00:00'),
                'body' => <<<'HTML'
<p>Choosing a tiffin is less about trends and more about the school bag: weight, leaks, smell, and whether it still looks decent after a term of tumbles.</p>
<h2>Where steel shines</h2>
<p>Steel lunch boxes handle heat and daily scrubbing well. They do not hold onto strong food smells as easily, and they feel sturdy for older kids who pack denser meals. Many Indian families prefer steel for hot sabzis and rice because it feels familiar and durable.</p>
<h2>Where plastic (or lighter boxes) help</h2>
<p>Lighter boxes cut bag weight for younger children. Clear sections can make packing faster. The trade-off is choosing food-safe materials, checking lid quality, and replacing boxes that warp or crack.</p>
<h2>What to check before you buy</h2>
<ul>
<li>Secure lids that survive a sideways bag</li>
<li>Easy cleaning—narrow corners trap leftovers</li>
<li>Size that matches one school meal, not a picnic</li>
<li>Handles or shapes that fit the school bag pocket</li>
</ul>
<h2>Our take at Venture Smart</h2>
<p>We curate lunch boxes for everyday Indian routines—not endless clutter. If you want something sturdy for school years ahead, start with steel options in our <a href="/category/lunch-box">lunch box category</a>. Prefer browsing everything together? Visit the <a href="/shop">shop</a>.</p>
HTML,
            ],
            [
                'title' => 'Age-appropriate toys for creative play',
                'slug' => 'age-appropriate-toys-creative-play',
                'excerpt' => 'How to pick toys that invite building, pretend play, and soft companionship—without filling the house with unused clutter.',
                'cover_image' => '/products/toys/wooden-building-blocks/01.jpg',
                'published_at' => Carbon::parse('2026-07-08 10:00:00'),
                'body' => <<<'HTML'
<p>Creative play does not need a huge toy mountain. A few well-chosen pieces—blocks, pretend kitchens, soft companions—can stretch imagination across years if they match the child’s stage.</p>
<h2>Toddlers: simple, sturdy, sensory</h2>
<p>Look for chunky shapes, soft edges, and toys that survive chewing and dropping. Soft toys and large blocks invite exploration without frustration.</p>
<h2>Preschool: pretend and build</h2>
<p>This is when kitchens, small figures, and building sets shine. Kids rehearse daily life—cooking, caring, stacking cities—and those stories deepen with open-ended toys rather than single-use gadgets.</p>
<h2>Early school age: challenge without overwhelm</h2>
<p>Slightly more complex builds and role-play sets keep interest high. Avoid toys that only do one flashy thing once. If a toy can be rebuilt, restaged, or shared with siblings, it earns its shelf space.</p>
<h2>Curate, don’t clutter</h2>
<p>At Venture Smart we keep the catalog focused: creative toys chosen for daily family life. Explore the <a href="/category/toys">toys collection</a>, or pair playtime picks with practical <a href="/category/lunch-box">lunch boxes</a> when you are shopping for the whole routine.</p>
HTML,
            ],
            [
                'title' => 'Gift ideas: toys and lunch boxes for birthdays',
                'slug' => 'gift-ideas-toys-lunch-boxes-birthdays',
                'excerpt' => 'Birthday gifts Indian families actually use—creative toys kids love and lunch boxes that earn a place in the school bag.',
                'cover_image' => '/images/home5-slideshow2.jpg',
                'published_at' => Carbon::parse('2026-07-12 10:00:00'),
                'body' => <<<'HTML'
<p>Great birthday gifts solve a daily need or unlock new play. For kids in India, that often means something for school mornings or something for after-homework imagination.</p>
<h2>When a lunch box is the perfect gift</h2>
<p>New school year, first big bag, or a child who finally wants to pack their own tiffin—these moments make a lunch box feel special, not ordinary. Choose secure lids, the right size, and a colour they will claim proudly.</p>
<h2>When toys make the celebration</h2>
<p>Building sets, pretend kitchens, and soft companions turn a birthday into weeks of play. Pick for age and interest, not just packaging. A gift that invites stories lasts longer than one that only lights up once.</p>
<h2>Bundle the day</h2>
<p>A thoughtful pair works beautifully: one creative toy for play, one practical lunch box for school. It feels complete without being excessive—and it matches how Venture Smart thinks about family routines.</p>
<p>Start with <a href="/category/toys">toys</a> or <a href="/category/lunch-box">lunch boxes</a>, or browse the full <a href="/shop">catalog</a> for something that fits the child you know.</p>
HTML,
            ],
            [
                'title' => 'Shopping with confidence at Venture Smart',
                'slug' => 'shopping-confidence-shipping-replacement',
                'excerpt' => 'How delivery across India, free shipping thresholds, and 7-day replacement support work when you order toys or lunch boxes.',
                'cover_image' => '/images/home5-info1.png',
                'published_at' => Carbon::parse('2026-07-16 10:00:00'),
                'body' => <<<'HTML'
<p>Ordering online for kids’ essentials should feel calm. Here is how Venture Smart supports you from add-to-cart through delivery—without inventing fine print.</p>
<h2>Delivery across India</h2>
<p>We ship toys and lunch boxes nationwide, subject to courier coverage. After you place an order, we prepare it for dispatch and share updates through the contact details you provide at checkout.</p>
<h2>Free shipping on orders over ₹999</h2>
<p>When your cart clears ₹999, shipping is free. It is a simple threshold designed for stocking up on the focused catalog—without pushing clutter you do not need.</p>
<h2>7-day replacement support</h2>
<p>If something arrives damaged or incorrect, reach out within seven days with your order details. We will guide you on next steps. You can also read more on our <a href="/returns">returns</a> and <a href="/shipping">shipping</a> pages, or <a href="/contact">contact us</a> directly.</p>
<h2>Pay the way that suits you</h2>
<p>Checkout supports UPI, cards, net banking, COD where available, and Razorpay for secure online payments. Details live on our <a href="/payments">payments</a> page.</p>
<p>Ready when you are—explore the <a href="/shop">shop</a>, or jump into <a href="/category/toys">toys</a> and <a href="/category/lunch-box">lunch boxes</a>.</p>
HTML,
            ],
        ];

        foreach ($posts as $post) {
            Post::query()->create($post);
        }
    }
}
