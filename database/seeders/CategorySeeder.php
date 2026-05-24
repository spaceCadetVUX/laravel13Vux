<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Support\LocaleUrl;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [

            // ── 1. Đèn LED ────────────────────────────────────────────────────
            [
                'category' => [
                    'name'        => 'Đèn LED',
                    'slug'        => 'den-led',
                    'description' => 'Danh mục đèn LED bao gồm toàn bộ các sản phẩm chiếu sáng LED: bóng đèn, downlight, panel, spotlight và đèn công nghiệp từ các thương hiệu hàng đầu.',
                    'is_active'   => true,
                    'sort_order'  => 1,
                    'parent_id'   => null,
                ],
                'trans_vi' => [
                    'locale'      => 'vi',
                    'name'        => 'Đèn LED',
                    'slug'        => 'den-led',
                    'description' => 'Danh mục đèn LED bao gồm toàn bộ các sản phẩm chiếu sáng LED: bóng đèn, downlight, panel, spotlight và đèn công nghiệp từ các thương hiệu hàng đầu.',
                    'rich_content' => [
                        'type'    => 'doc',
                        'content' => [
                            [
                                'type'    => 'heading',
                                'attrs'   => ['level' => 2],
                                'content' => [['type' => 'text', 'text' => 'Đèn LED là gì? Tại sao nên chuyển sang LED?']],
                            ],
                            [
                                'type'    => 'paragraph',
                                'attrs'   => ['textAlign' => 'start'],
                                'content' => [['type' => 'text', 'text' => 'Đèn LED (Light Emitting Diode) là công nghệ chiếu sáng thế hệ mới sử dụng diode phát quang để tạo ra ánh sáng. So với đèn sợi đốt và đèn huỳnh quang truyền thống, đèn LED vượt trội về mọi mặt: tiết kiệm điện đến 80%, tuổi thọ 15.000–25.000 giờ (gấp 10–25 lần), không chứa thủy ngân, bật sáng tức thì và không phát nhiệt.']],
                            ],
                            [
                                'type'    => 'heading',
                                'attrs'   => ['level' => 2],
                                'content' => [['type' => 'text', 'text' => 'Phân loại đèn LED phổ biến']],
                            ],
                            [
                                'type'    => 'bulletList',
                                'content' => [
                                    ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'Bóng đèn LED bulb: '], ['type' => 'text', 'text' => 'thay thế trực tiếp bóng sợi đốt, đuôi E27/E14, công suất 3W–20W.']]]]],
                                    ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'Đèn downlight LED: '], ['type' => 'text', 'text' => 'âm trần hoặc nổi, phổ biến cho nhà ở, văn phòng, trung tâm thương mại.']]]]],
                                    ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'Đèn panel LED: '], ['type' => 'text', 'text' => 'dạng tấm phẳng 300×300, 300×600, 600×600mm cho văn phòng, bệnh viện, trường học.']]]]],
                                    ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'Đèn ống LED T8: '], ['type' => 'text', 'text' => 'thay thế đèn huỳnh quang T8, không cần thay ballast, tiết kiệm 50% điện.']]]]],
                                    ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'Đèn highbay LED: '], ['type' => 'text', 'text' => 'chiếu sáng nhà xưởng, kho bãi, sân thể thao, công suất 50W–300W.']]]]],
                                ],
                            ],
                            [
                                'type'    => 'heading',
                                'attrs'   => ['level' => 2],
                                'content' => [['type' => 'text', 'text' => 'Hướng dẫn chọn đèn LED phù hợp']],
                            ],
                            [
                                'type'    => 'paragraph',
                                'attrs'   => ['textAlign' => 'start'],
                                'content' => [['type' => 'text', 'text' => 'Chọn đèn LED cần quan tâm 4 yếu tố: (1) Công suất (W) — xác định theo diện tích phòng, thường 10W/m²; (2) Nhiệt độ màu — 2700K–3000K (ánh vàng ấm) cho phòng ngủ, 4000K–6500K (trắng lạnh) cho văn phòng; (3) Chỉ số hoàn màu CRI — ≥80 cho gia đình, ≥90 cho showroom và y tế; (4) Thương hiệu và bảo hành — ưu tiên hàng chính hãng bảo hành tối thiểu 2 năm.']],
                            ],
                        ],
                    ],
                ],
                'trans_en' => [
                    'locale'      => 'en',
                    'name'        => 'LED Lighting',
                    'slug'        => 'led-lighting',
                    'description' => 'LED Lighting category covering all LED products: bulbs, downlights, panels, spotlights and industrial LED fixtures from top brands.',
                    'rich_content' => [
                        'type'    => 'doc',
                        'content' => [
                            [
                                'type'    => 'heading',
                                'attrs'   => ['level' => 2],
                                'content' => [['type' => 'text', 'text' => 'What is LED Lighting and Why Switch to LED?']],
                            ],
                            [
                                'type'    => 'paragraph',
                                'attrs'   => ['textAlign' => 'start'],
                                'content' => [['type' => 'text', 'text' => 'LED (Light Emitting Diode) lighting is the latest-generation technology using semiconductor diodes to produce light. Compared to incandescent and fluorescent lamps, LEDs are superior in every way: up to 80% energy savings, 15,000–25,000 hour lifespan (10–25x longer), no mercury, instant-on, and minimal heat output.']],
                            ],
                            [
                                'type'    => 'heading',
                                'attrs'   => ['level' => 2],
                                'content' => [['type' => 'text', 'text' => 'Popular LED Light Types']],
                            ],
                            [
                                'type'    => 'bulletList',
                                'content' => [
                                    ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'LED Bulbs: '], ['type' => 'text', 'text' => 'direct replacement for incandescent bulbs, E27/E14 base, 3W–20W output.']]]]],
                                    ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'LED Downlights: '], ['type' => 'text', 'text' => 'recessed or surface-mounted, popular for homes, offices, and shopping centers.']]]]],
                                    ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'LED Panels: '], ['type' => 'text', 'text' => 'flat 300×300, 300×600, 600×600mm panels for offices, hospitals, schools.']]]]],
                                    ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'LED T8 Tubes: '], ['type' => 'text', 'text' => 'direct fluorescent T8 replacement, no ballast change required, 50% energy savings.']]]]],
                                    ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'LED Highbay: '], ['type' => 'text', 'text' => 'factory, warehouse, sports hall lighting, 50W–300W output.']]]]],
                                ],
                            ],
                            [
                                'type'    => 'heading',
                                'attrs'   => ['level' => 2],
                                'content' => [['type' => 'text', 'text' => 'How to Choose the Right LED Light']],
                            ],
                            [
                                'type'    => 'paragraph',
                                'attrs'   => ['textAlign' => 'start'],
                                'content' => [['type' => 'text', 'text' => 'Choosing LED lighting requires 4 key considerations: (1) Wattage (W) — sized to room area, typically 10W/m²; (2) Color temperature — 2700K–3000K (warm white) for bedrooms, 4000K–6500K (cool white) for offices; (3) CRI (Color Rendering Index) — ≥80 for homes, ≥90 for showrooms and healthcare; (4) Brand and warranty — prioritize genuine products with at least a 2-year warranty.']],
                            ],
                        ],
                    ],
                ],
                'seo_vi' => [
                    'meta_title'          => 'Đèn LED chính hãng — Philips, OSRAM, Panasonic, Rạng Đông',
                    'meta_description'    => 'Mua đèn LED chính hãng: bóng đèn, downlight, panel, đèn ống T8, đèn công nghiệp. Thương hiệu Philips, OSRAM, Panasonic, Rạng Đông, Điện Quang. Bảo hành 2 năm, giao toàn quốc.',
                    'meta_keywords'       => 'đèn LED, mua đèn LED, đèn LED chính hãng, đèn downlight LED, đèn panel LED, đèn LED giá rẻ',
                    'canonical_url'       => LocaleUrl::for('category', 'den-led', 'vi'),
                    'robots'              => 'index, follow',
                    'og_title'            => 'Đèn LED chính hãng — Tiết kiệm điện, bền bỉ',
                    'og_description'      => 'Hàng trăm mẫu đèn LED từ Philips, OSRAM, Panasonic. Tiết kiệm 80% điện, bảo hành 2 năm, giao hàng toàn quốc.',
                    'og_type'             => 'website',
                    'twitter_card'        => 'summary_large_image',
                    'twitter_title'       => 'Đèn LED chính hãng — Philips, OSRAM, Panasonic',
                    'twitter_description' => 'Đèn LED tiết kiệm 80% điện, bền 25.000 giờ. Chính hãng, bảo hành 2 năm.',
                ],
                'seo_en' => [
                    'meta_title'          => 'LED Lighting — Philips, OSRAM, Panasonic, Rang Dong',
                    'meta_description'    => 'Buy genuine LED lighting: bulbs, downlights, panels, T8 tubes, industrial LED. Top brands: Philips, OSRAM, Panasonic, Rang Dong, Dien Quang. 2-year warranty, nationwide delivery.',
                    'meta_keywords'       => 'LED lighting, buy LED lights, genuine LED, LED downlight, LED panel, LED bulbs Vietnam',
                    'canonical_url'       => LocaleUrl::for('category', 'led-lighting', 'en'),
                    'robots'              => 'index, follow',
                    'og_title'            => 'LED Lighting — Energy Saving, Long Lasting',
                    'og_description'      => 'Hundreds of LED models from Philips, OSRAM, Panasonic. Up to 80% energy savings, 2-year warranty, nationwide delivery.',
                    'og_type'             => 'website',
                    'twitter_card'        => 'summary_large_image',
                    'twitter_title'       => 'LED Lighting — Philips, OSRAM, Panasonic',
                    'twitter_description' => 'LED lights save 80% energy, last 25,000 hours. Genuine brands, 2-year warranty.',
                ],
                'geo_vi' => [
                    'ai_summary'       => 'Danh mục Đèn LED bao gồm toàn bộ sản phẩm chiếu sáng LED trên site, từ bóng đèn dân dụng đến đèn công nghiệp. Các thương hiệu chính: Philips (số 1 thế giới, LED CRI cao), OSRAM (Đức, chuyên nghiệp), Panasonic (Nhật, bền bỉ), Rạng Đông và Điện Quang (Việt Nam, giá tốt). Đèn LED tiết kiệm 80% điện so với bóng sợi đốt, tuổi thọ 15.000–25.000 giờ. Phù hợp mọi không gian từ nhà ở, văn phòng đến nhà xưởng.',
                    'key_facts'        => [
                        ['label' => 'Tiết kiệm điện',      'value' => 'Lên đến 80% so với đèn sợi đốt'],
                        ['label' => 'Tuổi thọ trung bình', 'value' => '15.000 – 25.000 giờ'],
                        ['label' => 'Nhiệt độ màu',        'value' => '2700K (ấm) đến 6500K (lạnh)'],
                        ['label' => 'Chỉ số hoàn màu',     'value' => 'CRI 80–95 tùy dòng sản phẩm'],
                        ['label' => 'Thương hiệu có sẵn',  'value' => 'Philips, OSRAM, Panasonic, Rạng Đông, Điện Quang'],
                        ['label' => 'Bảo hành',            'value' => '2–3 năm chính hãng'],
                    ],
                    'faq'              => [
                        ['question' => 'Đèn LED tiêu thụ bao nhiêu điện so với đèn thường?',
                         'answer'   => 'Đèn LED tiêu thụ ít hơn 80% so với đèn sợi đốt cùng độ sáng. Ví dụ: bóng LED 9W thay thế bóng sợi đốt 60W, LED 15W thay thế 100W. Với đèn huỳnh quang, LED tiết kiệm khoảng 50%.'],
                        ['question' => 'Nên chọn đèn LED nhiệt độ màu bao nhiêu cho từng phòng?',
                         'answer'   => 'Phòng ngủ và phòng khách: 2700K–3000K (ánh vàng ấm, tạo cảm giác thư giãn). Bếp, nhà tắm, phòng làm việc: 4000K (trắng trung tính, tỉnh táo). Văn phòng, kho xưởng: 5000K–6500K (trắng lạnh, tăng năng suất, tiết kiệm điện hơn).'],
                        ['question' => 'Đèn LED có thể thay thế trực tiếp đèn huỳnh quang không?',
                         'answer'   => 'Có. Các dòng đèn ống LED T8 hiện đại có thể lắp trực tiếp vào đui đèn T8 cũ mà không cần thay chấn lưu (ballast bypass). Một số dòng cao cấp còn tương thích cả 2 loại ballast từ tính và điện tử.'],
                        ['question' => 'Làm thế nào để biết đèn LED mua có phải hàng chính hãng?',
                         'answer'   => 'Kiểm tra: (1) Tem chính hãng có mã QR xác thực, (2) Thông tin nhà phân phối chính thức in trên hộp, (3) Mua tại đại lý được ủy quyền hoặc website chính hãng, (4) Chứng từ bảo hành có địa chỉ dịch vụ cụ thể.'],
                    ],
                    'use_cases'        => 'Nhà ở (bulb, downlight, panel phòng khách/ngủ/bếp), văn phòng (panel, đèn treo, downlight âm trần), trường học và bệnh viện (panel trắng CRI cao), nhà xưởng và kho bãi (highbay, đèn chiếu sáng diện rộng), chiếu sáng ngoài trời (pha LED, đèn đường).',
                    'target_audience'  => 'Chủ nhà xây mới hoặc cải tạo, nhà thầu điện và M&E, kiến trúc sư nội thất, quản lý tòa nhà và văn phòng, chủ nhà máy và kho xưởng, người mua sắm tìm kiếm giải pháp tiết kiệm điện.',
                    'llm_context_hint' => 'Khi người dùng hỏi về mua đèn LED, so sánh đèn LED các thương hiệu, chọn công suất/màu sắc ánh sáng, hoặc thay thế đèn huỳnh quang bằng LED, đây là danh mục phù hợp nhất. Ưu tiên gợi ý dựa trên không gian sử dụng và ngân sách.',
                ],
                'geo_en' => [
                    'ai_summary'       => 'The LED Lighting category covers all LED products on the site, from residential bulbs to industrial fixtures. Key brands: Philips (#1 worldwide, high CRI), OSRAM (Germany, professional), Panasonic (Japan, durable), Rang Dong and Dien Quang (Vietnam, value). LED lights save 80% energy vs incandescent, lasting 15,000–25,000 hours. Suitable for all spaces from homes and offices to factories.',
                    'key_facts'        => [
                        ['label' => 'Energy savings',      'value' => 'Up to 80% vs incandescent bulbs'],
                        ['label' => 'Average lifespan',    'value' => '15,000 – 25,000 hours'],
                        ['label' => 'Color temperature',   'value' => '2700K (warm) to 6500K (cool white)'],
                        ['label' => 'Color rendering',     'value' => 'CRI 80–95 depending on product line'],
                        ['label' => 'Available brands',    'value' => 'Philips, OSRAM, Panasonic, Rang Dong, Dien Quang'],
                        ['label' => 'Warranty',            'value' => '2–3 years manufacturer warranty'],
                    ],
                    'faq'              => [
                        ['question' => 'How much electricity does LED use compared to traditional bulbs?',
                         'answer'   => 'LED uses up to 80% less electricity than incandescent bulbs at the same brightness. Example: 9W LED replaces 60W incandescent, 15W LED replaces 100W. Compared to fluorescent, LED saves about 50%.'],
                        ['question' => 'What color temperature should I choose for each room?',
                         'answer'   => 'Bedrooms and living rooms: 2700K–3000K (warm white, relaxing feel). Kitchens, bathrooms, study rooms: 4000K (neutral white, alert). Offices, warehouses: 5000K–6500K (cool white, productivity boost, more energy efficient).'],
                        ['question' => 'Can LED tubes directly replace fluorescent T8 tubes?',
                         'answer'   => 'Yes. Modern T8 LED tubes can be installed directly into old T8 fixtures without replacing the ballast (ballast bypass). Premium lines are compatible with both magnetic and electronic ballasts.'],
                        ['question' => 'How do I verify I am buying genuine LED products?',
                         'answer'   => 'Check: (1) Authentic hologram/QR code for product verification, (2) Authorized distributor information printed on packaging, (3) Purchase from authorized dealers or official brand websites, (4) Warranty documentation with specific service center addresses.'],
                    ],
                    'use_cases'        => 'Residential (bulbs, downlights, panels for living/bedroom/kitchen), offices (panels, suspended lights, recessed downlights), schools and hospitals (high-CRI white panels), factories and warehouses (highbay, wide-area lighting), outdoor lighting (LED flood lights, street lights).',
                    'target_audience'  => 'Homeowners building or renovating, electrical and M&E contractors, interior architects, building and office managers, factory and warehouse owners, shoppers looking for energy-saving solutions.',
                    'llm_context_hint' => 'When users ask about buying LED lights, comparing LED brands, choosing wattage/color temperature, or replacing fluorescent with LED, this is the most relevant category. Prioritize suggestions based on space and budget.',
                ],
            ],

            // ── 2. Thiết bị điện ─────────────────────────────────────────────────
            [
                'category' => [
                    'name'        => 'Thiết bị điện',
                    'slug'        => 'thiet-bi-dien',
                    'description' => 'Danh mục thiết bị điện hạ thế: ổ cắm, công tắc, aptomat, tủ điện từ các thương hiệu Schneider Electric, Panasonic, Legrand.',
                    'is_active'   => true,
                    'sort_order'  => 2,
                    'parent_id'   => null,
                ],
                'trans_vi' => [
                    'locale'      => 'vi',
                    'name'        => 'Thiết bị điện',
                    'slug'        => 'thiet-bi-dien',
                    'description' => 'Danh mục thiết bị điện hạ thế dân dụng và thương mại: ổ cắm, công tắc, aptomat MCB, ELCB, tủ điện từ Schneider Electric, Panasonic, Legrand.',
                    'rich_content' => [
                        'type'    => 'doc',
                        'content' => [
                            [
                                'type'    => 'heading',
                                'attrs'   => ['level' => 2],
                                'content' => [['type' => 'text', 'text' => 'Thiết bị điện hạ thế — Nền tảng an toàn cho mọi công trình']],
                            ],
                            [
                                'type'    => 'paragraph',
                                'attrs'   => ['textAlign' => 'start'],
                                'content' => [['type' => 'text', 'text' => 'Thiết bị điện hạ thế bao gồm tất cả các sản phẩm phân phối và kiểm soát điện trong nhà ở, văn phòng và công trình thương mại. Việc lựa chọn thiết bị điện chính hãng, đạt chuẩn an toàn là yếu tố then chốt bảo vệ con người và tài sản khỏi nguy cơ điện giật, chập cháy.']],
                            ],
                            [
                                'type'    => 'heading',
                                'attrs'   => ['level' => 2],
                                'content' => [['type' => 'text', 'text' => 'Các loại thiết bị điện cần thiết cho mỗi công trình']],
                            ],
                            [
                                'type'    => 'bulletList',
                                'content' => [
                                    ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'Ổ cắm và công tắc: '], ['type' => 'text', 'text' => 'phần giao tiếp trực tiếp nhất với người dùng — cần bền bỉ, tiếp điểm ổn định, thiết kế phù hợp không gian.']]]]],
                                    ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'Aptomat MCB: '], ['type' => 'text', 'text' => 'bảo vệ mạch điện khỏi quá tải và ngắn mạch, lắp trong tủ điện tổng và tủ phân nhánh.']]]]],
                                    ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'ELCB/RCBO: '], ['type' => 'text', 'text' => 'bảo vệ chống giật điện (rò điện sang người), bắt buộc cho khu vực ẩm ướt như bếp, nhà tắm.']]]]],
                                    ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'Tủ điện: '], ['type' => 'text', 'text' => 'tập hợp aptomat và thiết bị bảo vệ vào một hộp có cấu trúc, bảo vệ thiết bị và giúp quản lý hệ thống điện dễ dàng.']]]]],
                                ],
                            ],
                        ],
                    ],
                ],
                'trans_en' => [
                    'locale'      => 'en',
                    'name'        => 'Electrical Equipment',
                    'slug'        => 'electrical-equipment',
                    'description' => 'Low-voltage residential and commercial electrical equipment: sockets, switches, MCB circuit breakers, ELCB, and switchboards from Schneider Electric, Panasonic, Legrand.',
                    'rich_content' => [
                        'type'    => 'doc',
                        'content' => [
                            [
                                'type'    => 'heading',
                                'attrs'   => ['level' => 2],
                                'content' => [['type' => 'text', 'text' => 'Low-Voltage Electrical Equipment — The Safety Foundation']],
                            ],
                            [
                                'type'    => 'paragraph',
                                'attrs'   => ['textAlign' => 'start'],
                                'content' => [['type' => 'text', 'text' => 'Low-voltage electrical equipment includes all products for distributing and controlling electricity in homes, offices, and commercial buildings. Choosing genuine, safety-certified products is critical to protecting people and property from electrical shock and fire hazards.']],
                            ],
                            [
                                'type'    => 'heading',
                                'attrs'   => ['level' => 2],
                                'content' => [['type' => 'text', 'text' => 'Essential Electrical Equipment for Every Project']],
                            ],
                            [
                                'type'    => 'bulletList',
                                'content' => [
                                    ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'Sockets and Switches: '], ['type' => 'text', 'text' => 'most-used user interface — must be durable, have stable contacts, and fit the space aesthetically.']]]]],
                                    ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'MCB Circuit Breakers: '], ['type' => 'text', 'text' => 'protect circuits from overload and short circuit, installed in main and sub-distribution boards.']]]]],
                                    ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'ELCB/RCBO: '], ['type' => 'text', 'text' => 'protect against electric shock (earth leakage), mandatory for wet areas like kitchens and bathrooms.']]]]],
                                    ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'Switchboards: '], ['type' => 'text', 'text' => 'consolidate breakers and protection devices into a structured enclosure for easy system management.']]]]],
                                ],
                            ],
                        ],
                    ],
                ],
                'seo_vi' => [
                    'meta_title'          => 'Thiết bị điện chính hãng — Schneider, Panasonic, Legrand',
                    'meta_description'    => 'Mua thiết bị điện chính hãng: ổ cắm, công tắc, aptomat, tủ điện Schneider Electric, Panasonic, Legrand. Bảo hành 2 năm, đạt chuẩn IEC, giao toàn quốc.',
                    'meta_keywords'       => 'thiết bị điện, ổ cắm chính hãng, aptomat Schneider, công tắc Panasonic, thiết bị điện Legrand',
                    'canonical_url'       => LocaleUrl::for('category', 'thiet-bi-dien', 'vi'),
                    'robots'              => 'index, follow',
                    'og_title'            => 'Thiết bị điện chính hãng — An toàn cho mọi công trình',
                    'og_description'      => 'Ổ cắm, công tắc, aptomat Schneider, Panasonic, Legrand chính hãng. Bảo hành 2 năm, đạt chuẩn IEC.',
                    'og_type'             => 'website',
                    'twitter_card'        => 'summary_large_image',
                    'twitter_title'       => 'Thiết bị điện chính hãng — Schneider, Panasonic, Legrand',
                    'twitter_description' => 'Ổ cắm, aptomat Schneider, Panasonic, Legrand. Chính hãng, bảo hành 2 năm.',
                ],
                'seo_en' => [
                    'meta_title'          => 'Electrical Equipment — Schneider, Panasonic, Legrand',
                    'meta_description'    => 'Buy genuine electrical equipment: sockets, switches, MCB circuit breakers, switchboards from Schneider Electric, Panasonic, Legrand. 2-year warranty, IEC certified, nationwide delivery.',
                    'meta_keywords'       => 'electrical equipment, genuine sockets, Schneider circuit breaker, Panasonic switches, Legrand electrical',
                    'canonical_url'       => LocaleUrl::for('category', 'electrical-equipment', 'en'),
                    'robots'              => 'index, follow',
                    'og_title'            => 'Electrical Equipment — Safety for Every Project',
                    'og_description'      => 'Schneider, Panasonic, Legrand sockets, switches and circuit breakers. Genuine, 2-year warranty, IEC certified.',
                    'og_type'             => 'website',
                    'twitter_card'        => 'summary_large_image',
                    'twitter_title'       => 'Electrical Equipment — Schneider, Panasonic, Legrand',
                    'twitter_description' => 'Sockets, circuit breakers Schneider, Panasonic, Legrand. Genuine, 2-year warranty.',
                ],
                'geo_vi' => [
                    'ai_summary'       => 'Danh mục Thiết bị điện gồm ổ cắm, công tắc, aptomat MCB/ELCB và tủ điện dân dụng/thương mại. Thương hiệu chính: Schneider Electric (Pháp, top 1 thế giới về quản lý năng lượng), Panasonic (Nhật, thiết kế tinh tế), Legrand (Pháp, phân khúc luxury Céliane/Mosaic). Tiêu chí an toàn: IEC, CE, chuẩn Bộ Công Thương VN. Phù hợp từ nhà ở phổ thông đến công trình thương mại cao cấp.',
                    'key_facts'        => [
                        ['label' => 'Thương hiệu chính',   'value' => 'Schneider Electric, Panasonic, Legrand'],
                        ['label' => 'Tiêu chuẩn an toàn', 'value' => 'IEC, CE, chuẩn Bộ Công Thương Việt Nam'],
                        ['label' => 'Phân khúc',           'value' => 'Từ phổ thông đến cao cấp (luxury)'],
                        ['label' => 'Bảo hành',            'value' => '2 năm chính hãng'],
                        ['label' => 'Sản xuất tại VN',     'value' => 'Schneider (Hà Nội), Panasonic (Đồng Nai)'],
                        ['label' => 'Ứng dụng',            'value' => 'Nhà ở, văn phòng, khách sạn, nhà máy'],
                    ],
                    'faq'              => [
                        ['question' => 'Aptomat và ELCB khác nhau thế nào, tôi cần loại nào?',
                         'answer'   => 'Aptomat (MCB) bảo vệ mạch điện khỏi quá tải và ngắn mạch. ELCB/RCBO bảo vệ người khỏi điện giật do rò điện. Nhà ở cần cả hai: MCB cho từng nhánh, ELCB cho các khu vực ẩm ướt (bếp, nhà tắm, ngoài trời). RCBO kết hợp cả hai chức năng trong một thiết bị.'],
                        ['question' => 'Schneider, Panasonic và Legrand — nên chọn thương hiệu nào?',
                         'answer'   => 'Schneider: giá trung bình, đa dạng dòng sản phẩm, phổ biến nhất VN. Panasonic: chất lượng Nhật, thiết kế thanh lịch, bảo hành tốt. Legrand: cao cấp nhất (Céliane, Mosaic), phù hợp biệt thự và khách sạn 4–5 sao. Ngân sách thường → Schneider. Trung cao cấp → Panasonic. Luxury → Legrand.'],
                        ['question' => 'Tủ điện nên đặt ở đâu trong nhà?',
                         'answer'   => 'Tủ điện nên đặt ở vị trí trung tâm, dễ tiếp cận (hành lang, sảnh), tránh ẩm ướt và trực tiếp ánh nắng. Độ cao lý tưởng 1,2–1,8m so với sàn. Không đặt trong phòng ngủ, bếp hoặc nhà tắm.'],
                    ],
                    'use_cases'        => 'Nhà ở dân dụng (ổ cắm, công tắc, tủ điện aptomat), căn hộ cao cấp (Panasonic Advance, Legrand Céliane), văn phòng và tòa nhà thương mại (tủ điện Schneider Prisma), khách sạn và resort (Legrand Mosaic với thiết kế custom), nhà máy công nghiệp (biến tần, khởi động mềm Schneider).',
                    'target_audience'  => 'Chủ nhà xây mới hoặc cải tạo, nhà thầu điện M&E, kỹ sư thiết kế hệ thống điện, kiến trúc sư nội thất (ổ cắm cao cấp), quản lý tòa nhà và khu công nghiệp.',
                    'llm_context_hint' => 'Khi người dùng hỏi về aptomat, ổ cắm, tủ điện, chống giật điện ELCB, hoặc so sánh Schneider vs Panasonic vs Legrand, đây là danh mục phù hợp. Tư vấn theo phân khúc ngân sách và loại công trình.',
                ],
                'geo_en' => [
                    'ai_summary'       => 'The Electrical Equipment category covers sockets, switches, MCB/ELCB circuit breakers, and residential/commercial switchboards. Main brands: Schneider Electric (France, #1 worldwide energy management), Panasonic (Japan, elegant design), Legrand (France, luxury Céliane/Mosaic segment). Safety certifications: IEC, CE, Vietnam MOIT standards. Suitable for everything from standard residential to premium commercial projects.',
                    'key_facts'        => [
                        ['label' => 'Main brands',         'value' => 'Schneider Electric, Panasonic, Legrand'],
                        ['label' => 'Safety standards',    'value' => 'IEC, CE, Vietnam MOIT certified'],
                        ['label' => 'Market segments',     'value' => 'Standard to premium luxury'],
                        ['label' => 'Warranty',            'value' => '2-year manufacturer warranty'],
                        ['label' => 'Made in Vietnam',     'value' => 'Schneider (Hanoi), Panasonic (Dong Nai)'],
                        ['label' => 'Applications',        'value' => 'Homes, offices, hotels, factories'],
                    ],
                    'faq'              => [
                        ['question' => 'What is the difference between MCB and ELCB? Which do I need?',
                         'answer'   => 'MCB protects circuits from overload and short circuit. ELCB/RCBO protects people from electric shock due to earth leakage. Homes need both: MCB for each branch circuit, ELCB for wet areas (kitchen, bathroom, outdoors). RCBO combines both functions in a single device.'],
                        ['question' => 'Schneider, Panasonic or Legrand — which brand to choose?',
                         'answer'   => 'Schneider: mid-range price, widest product range, most popular in Vietnam. Panasonic: Japanese quality, elegant design, good warranty. Legrand: most premium (Céliane, Mosaic), ideal for villas and 4–5 star hotels. Budget → Schneider. Mid-premium → Panasonic. Luxury → Legrand.'],
                        ['question' => 'Where should the electrical panel (switchboard) be located at home?',
                         'answer'   => 'The panel should be centrally located and easily accessible (hallway, foyer), away from moisture and direct sunlight. Ideal height 1.2–1.8m from floor. Do not place in bedrooms, kitchens, or bathrooms.'],
                    ],
                    'use_cases'        => 'Standard residential (sockets, switches, MCB panels), premium apartments (Panasonic Advance, Legrand Céliane), offices and commercial buildings (Schneider Prisma switchboards), hotels and resorts (Legrand Mosaic custom design), industrial facilities (Schneider drives, soft starters).',
                    'target_audience'  => 'Homeowners building or renovating, electrical M&E contractors, electrical system design engineers, interior architects (premium sockets), building and industrial zone managers.',
                    'llm_context_hint' => 'When users ask about circuit breakers, sockets, switchboards, ELCB shock protection, or compare Schneider vs Panasonic vs Legrand, this is the right category. Advise based on budget segment and project type.',
                ],
            ],

        ];

        foreach ($categories as $data) {
            $category = Category::updateOrCreate(
                ['slug' => $data['category']['slug']],
                $data['category'],
            );

            // Translations vi + en
            foreach (['trans_vi' => 'vi', 'trans_en' => 'en'] as $key => $locale) {
                CategoryTranslation::updateOrCreate(
                    ['category_id' => $category->id, 'locale' => $locale],
                    $data[$key],
                );
            }

            // SEO vi + en
            $category->seoMetaVi()->updateOrCreate(['locale' => 'vi'], $data['seo_vi']);
            $category->seoMetaEn()->updateOrCreate(['locale' => 'en'], $data['seo_en']);

            // GEO profile vi + en
            $category->geoProfileVi()->updateOrCreate(['locale' => 'vi'], $data['geo_vi']);
            $category->geoProfileEn()->updateOrCreate(['locale' => 'en'], $data['geo_en']);
        }
    }
}
