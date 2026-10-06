<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Images on the storefront home page, managed by admins and managers.
        Schema::create('home_banners', function (Blueprint $table) {
            $table->id();
            $table->string('section'); // hero, tile, lifestyle, editorial
            $table->string('image', 2048);
            $table->string('eyebrow')->nullable();
            $table->string('title')->nullable();
            $table->string('text', 500)->nullable();
            $table->string('cta')->nullable();
            $table->string('link', 2048)->nullable();
            $table->string('alt')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['section', 'position']);
        });

        // Start with the images the home page shipped with, so nothing changes until someone edits it.
        $rows = [
            ['section' => 'hero', 'eyebrow' => 'New Arrival', 'title' => 'Watch Collection', 'text' => 'Timeless precision engineered with premium rose gold and sapphire crystal.', 'cta' => 'Explore Timepieces', 'link' => '/products?category=accessories', 'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuCHib0zh3Ln8byCKUMaBulHvG4HbyzaqczYu_MXEZ_1Tw0ACxzzIL01eDJPRp6X_qazVezCtziqBzJbx1eAZ7u2rZ9Rov7dAihd-nsMLwhgJiLTcRUyjJsrxvoql86v3dUnM-ic5B3b15NPpnE8kWoPQV9zQuDVgTvIJYkUWE72LW_SfYE8zA9PPrdLctb4gX52mF9hzT-tXN_NZIbIyIRbSGn--ElgScHk7jJW1t2Y1Bw7hVIy6V9oEQ', 'alt' => 'Luxury gold chronograph watch'],
            ['section' => 'hero', 'eyebrow' => 'Festive Edit', 'title' => 'Panjabi Collection', 'text' => 'Hand-finished embroidery and rich fabrics crafted for every celebration.', 'cta' => 'Shop Panjabi', 'link' => '/products?category=panjabi', 'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuBcA0IyM0CEjZCyNFjvOVu0ga7LNN_XqIawD9yWZEY_n53zTUtQ9JZqtGoVtP7-qWfqQ5L4JEtpOHZib6dga-ETWdxkh2-GJLAfhs1T8617wIjKjP5iVJy6HcMNKoeOYUBi4HVnWZR5aM2Dxm_cNcTA34iGP247Um_RmQXGfxIPVHPfgztZ5gtkiyM0DE1NsQh9lQkeLPSjnOPuQJrc-skICZ2bmYxt05QvOcOp5ahEBbnHTnWjV3bIGw', 'alt' => 'Model in embroidered panjabi'],
            ['section' => 'hero', 'eyebrow' => 'Summer Ready', 'title' => 'Polo Edition', 'text' => 'Breathable piqué cotton polos in a palette made for sunny days.', 'cta' => 'Shop Polos', 'link' => '/products?category=polo', 'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuAhWWZ57PEPg4qQScg_HW-wYOAWQqxtpmi3ZIRzxhiH0t2LEN90tVQal3gb2VP1pbBrvkzRaYI6jpKOxUI7pRLltEx9ZLdcghfssMOnEWHXSSjZ8mO5OtfNew-DlVi9e2VmvDryqfjOyGZGO1Bm1ITXNv3X6Ftr8T12Gc5nMIqhEMI-fPyCCqHEN7dLzqK2g1ZYtSqYoBXJwESQMbcpd5u4SV7k7TDXOzbMGlZtqvByap-i-9U9Orgawg', 'alt' => 'Model in blue polo shirt'],
            ['section' => 'tile', 'title' => 'Polo Shirts', 'link' => '/products?category=polo', 'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuAhWWZ57PEPg4qQScg_HW-wYOAWQqxtpmi3ZIRzxhiH0t2LEN90tVQal3gb2VP1pbBrvkzRaYI6jpKOxUI7pRLltEx9ZLdcghfssMOnEWHXSSjZ8mO5OtfNew-DlVi9e2VmvDryqfjOyGZGO1Bm1ITXNv3X6Ftr8T12Gc5nMIqhEMI-fPyCCqHEN7dLzqK2g1ZYtSqYoBXJwESQMbcpd5u4SV7k7TDXOzbMGlZtqvByap-i-9U9Orgawg', 'alt' => 'Model in blue polo shirt on a yacht'],
            ['section' => 'tile', 'title' => 'Panjabis', 'link' => '/products?category=panjabi', 'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuBcA0IyM0CEjZCyNFjvOVu0ga7LNN_XqIawD9yWZEY_n53zTUtQ9JZqtGoVtP7-qWfqQ5L4JEtpOHZib6dga-ETWdxkh2-GJLAfhs1T8617wIjKjP5iVJy6HcMNKoeOYUBi4HVnWZR5aM2Dxm_cNcTA34iGP247Um_RmQXGfxIPVHPfgztZ5gtkiyM0DE1NsQh9lQkeLPSjnOPuQJrc-skICZ2bmYxt05QvOcOp5ahEBbnHTnWjV3bIGw', 'alt' => 'Model in embroidered ethnic panjabi'],
            ['section' => 'tile', 'title' => 'Casual Shirt', 'link' => '/products?category=shirt', 'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuA5VBXvY_9WWQKCajVMryj-1xNbrfm83I0AJ4P80xInPuBiFZhCJRhrYDUEL8on6h9JnHkckYR5l6Nmd07ZaNYAK9oquNNMFaAv2mOFCMMYbh_a1QoYSQ5ygj4VOJjl8GjJj4rQJt2k59YoeFrEQQI1mc75HElV8IMpg4no_7oONnlm3MnKLNImgckMMEuIO8VpnwhC8CCbonTKX2kownHbDpBzvwueCvX1bnsCnyz9IReXGfpgBN322Q', 'alt' => 'Model in printed casual shirt and fedora'],
            ['section' => 'lifestyle', 'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuAihe0m-yyJskfnyhXfHBaO_PJ76ZRxtgzAj_TJuagV5MTsbvwQy3q6uk4XVE5pLetAGSGnNoPQRVWEoMC7LAbceeG4BZMKtVx_VZylGy0R4EjD-5IJxYiXvO57NLcTMRC-L_wucvUqKkl4fj9yyFAsF41_EC_LcJNvb9Wo67rLUk_m3dZhbSAB2Qgjjtk02SlkfSJxfdLHL5U26a__SBsm7QIm8_h42H5lVQfgSHwt3sMdcXfUkEpd4w', 'alt' => 'Men in stylish polo shirts and sunglasses'],
            ['section' => 'lifestyle', 'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuAMG0jb9Yuva1e33zSQgbEIfwlHHvO4v8QGx9urzekuC30OU5taWnXiS6wNT8Wgkwu_LQUcvtxduP5e4DOLawgveJ4XCqLJSia7DO1cx3NLa_rmaFZVXbK4gEZMjFYyTAnDXBy4Q6a6pMpOIYZz4fXwxPZAiFpEnH-4eoe9QYz4Ca6bPBF_xxAFIdW-xCdsbuiwb3F80bRCRqu8zCHThRVwbYkAx43UEOhII_8_JiFg9s4kHUvNxYzaTg', 'alt' => 'Model outdoors wearing a green polo shirt'],
            ['section' => 'lifestyle', 'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuCW2yFIOBru6HmVGUVDWq9h--g8SMsjO5CN8KyuYuqmPIva-8DOzpEAB8ttveopu4j6wwPRV_u3VKYRxx-TIwfCkjgc6BG21LNbR3yJN7SmZqljTIKPBzUkMfSJ7xzE_ddmTvdxgyoLwhLzbx5klLxn-InHgEjAgs0fcsXqtBS5dqSJ__G1alfLWoT0RGPH71V-inT5XHredGbn6ea12zicDoBsEyPjwA5Ot1C5iyNm5WVuVOM3pMnjMw', 'alt' => 'Two gentlemen in formal button-down shirts'],
            ['section' => 'editorial', 'title' => 'Our History', 'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuAwdJkoJmgSJeGqrmwSHHd7buLW2PKFEatl8i4BRhzCGZrWlI5JdRtiWGWCmaF9GX6197M0IWWmvp68Q7O3-tchm14zTb_5VDpMTQq-noUdU4_VG3OMZKaaLOq9OyXKKpPuIadeGzoqieaq-C2fDZZSgh6qOUQyA3R_NstKUuIcR2rBMuDpEWeD5ROQZ2mPbGeKO9uLxaBe3X51N7WaCWvCnJTuiVpL2VG8wW7E1doToP41u1vxfuRgcg', 'alt' => 'Gentleman next to a luxury car'],
            ['section' => 'editorial', 'title' => 'Our Journal', 'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuCw6Rw5vFL9qG8OGkQCaee93IxiNeO15AoqzTHUhe2E8DPWK_dJ_n1EBeVP4ge6uuVGW8thxkPm8nzrysU8xC9VhJIvJuiwsBdrhnWLz2ZH-1UV-3D1PO_uWs63w8N9nAjtVY6-iThOFmdzS7HuyFbhPz5tp2y6L5WTeKYWUA1VsZg1y1rdGf8MLCF-sLGZR-HbLIm6JlfTOvWSrUrW72KyfH0sl4iCj5vozv2xA2rsqE4CXnB_rFyoNg', 'alt' => 'Businessman working on a laptop outdoors'],
        ];

        $positions = [];
        foreach ($rows as $row) {
            $positions[$row['section']] = ($positions[$row['section']] ?? 0) + 1;
            DB::table('home_banners')->insert($row + ['position' => $positions[$row['section']], 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('home_banners');
    }
};
