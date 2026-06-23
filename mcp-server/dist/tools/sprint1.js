import { z } from "zod";
import { api, ok } from "../client.js";
export function registerSprint1Tools(server) {
    server.tool("get_product_context", "Load full product context trước khi viết content. Gọi đầu tiên để hiểu sản phẩm.", {
        slug: z.string().describe("Product slug, e.g. knx-push-button-4fold"),
    }, async ({ slug }) => ok(await api("GET", `/mcp/products/${slug}/context`)));
    server.tool("check_product_readiness", "Kiểm tra product đủ điều kiện activate chưa. Gọi trước activate_product.", {
        slug: z.string().describe("Product slug"),
    }, async ({ slug }) => ok(await api("GET", `/mcp/products/${slug}/readiness`)));
    server.tool("save_product", "Upsert product — tạo mới hoặc update content, translations, SEO, GEO/FAQ, attributes.", {
        slug: z.string().describe("Product slug — dùng làm URL"),
        name: z.string().optional().describe("Tên sản phẩm (fallback nếu không có translations)"),
        sku: z.string().optional().describe("SKU sản phẩm"),
        price: z.number().optional().describe("Giá sản phẩm (decimal)"),
        sale_price: z.number().nullable().optional().describe("Giá khuyến mãi root — null = xoá KM, bỏ qua field = không đổi"),
        currency: z.enum(["VND", "USD"]).optional().describe("Currency root — VND cho vi, USD cho en. Auto-promote xuống translations nếu chưa set"),
        manufacturer_slug: z.string().optional().describe("Slug của manufacturer"),
        category_slug: z.string().optional().describe("Slug của category"),
        overwrite_existing: z.boolean().default(false).describe("true = ghi đè content đã có"),
        dry_run: z.boolean().default(false).describe("true = preview, không lưu DB"),
        _stubs: z.object({
            manufacturer: z.object({
                slug: z.string().describe("Manufacturer slug"),
                name: z.string().describe("Manufacturer name"),
                country: z.string().optional().describe("Quốc gia"),
                website: z.string().optional().describe("Website URL"),
            }).optional().describe("Tạo manufacturer nếu chưa tồn tại"),
            category: z.object({
                slug: z.string().describe("Category slug"),
                translations: z.record(z.object({
                    name: z.string().describe("Tên category theo locale"),
                    slug: z.string().describe("Slug category theo locale"),
                })).describe('{"vi": {name, slug}, "en": {name, slug}}'),
            }).optional().describe("Tạo category nếu chưa tồn tại"),
        }).optional().describe("Tạo entity liên quan nếu chưa tồn tại"),
        translations: z.record(z.object({
            name: z.string().optional().describe("Tên sản phẩm theo locale"),
            description: z.string().optional().describe("Mô tả đầy đủ (HTML ok)"),
            short_description: z.string().optional().describe("Mô tả ngắn"),
            price: z.number().optional().describe("Giá bán theo locale (VND cho vi, USD cho en)"),
            sale_price: z.number().optional().describe("Giá khuyến mãi theo locale — null = không KM"),
            currency: z.enum(["VND", "USD"]).optional().describe("Currency cho locale này, e.g. VND | USD"),
        })).optional().describe('{"vi": {price, sale_price, currency, ...}, "en": {price, sale_price, currency, ...}}'),
        seo: z.record(z.object({
            meta_title: z.string().optional().describe("SEO title (≤60 ký tự)"),
            meta_description: z.string().optional().describe("SEO description (≤160 ký tự)"),
            robots: z.string().optional().describe('e.g. "index,follow"'),
        })).optional().describe('{"vi": {...}, "en": {...}}'),
        geo: z.record(z.object({
            ai_summary: z.string().optional().describe("Tóm tắt cho AI/GEO"),
            use_cases: z.string().optional().describe("Các use case điển hình"),
            target_audience: z.string().optional().describe("Đối tượng mục tiêu"),
            llm_context_hint: z.string().optional().describe("Hint cho LLM khi dùng sản phẩm này"),
            key_facts: z.array(z.object({
                label: z.string().describe("Nhãn thông số"),
                value: z.string().describe("Giá trị thông số"),
            })).optional().describe("Các thông số kỹ thuật nổi bật"),
            faq: z.array(z.object({
                question: z.string().describe("Câu hỏi"),
                answer: z.string().describe("Câu trả lời"),
            })).optional().describe("FAQ theo locale — ưu tiên hơn faq_items_vi/en"),
        })).optional().describe('{"vi": {...}, "en": {...}} — ưu tiên dùng field này'),
        faq_items_vi: z.array(z.object({
            question: z.string().describe("Câu hỏi tiếng Việt"),
            answer: z.string().describe("Câu trả lời tiếng Việt"),
        })).optional().describe("[Deprecated — dùng geo.vi.faq thay thế]"),
        faq_items_en: z.array(z.object({
            question: z.string().describe("Câu hỏi tiếng Anh"),
            answer: z.string().describe("Câu trả lời tiếng Anh"),
        })).optional().describe("[Deprecated — dùng geo.en.faq thay thế]"),
        attributes: z.array(z.object({
            name: z.string().describe("Tên thuộc tính tiếng Việt, e.g. 'Số kênh'"),
            name_en: z.string().nullable().optional().describe("Attribute name in English, e.g. 'Channels'"),
            value: z.string().describe("Giá trị tiếng Việt, e.g. '4'"),
            value_en: z.string().nullable().optional().describe("Attribute value in English, e.g. '4'"),
            unit: z.string().nullable().optional().describe("Đơn vị, e.g. 'kênh', null nếu không có"),
        })).optional().describe("Thông số kỹ thuật dạng key-value — hỗ trợ song ngữ VN + EN"),
    }, async ({ slug, ...body }) => ok(await api("PUT", `/mcp/products/${slug}`, body)));
    server.tool("activate_product", "Activate product sau khi readiness pass. Observer tự sync JSON-LD + Sitemap.", {
        slug: z.string().describe("Product slug"),
    }, async ({ slug }) => ok(await api("PATCH", `/mcp/products/${slug}/activate`, {})));
}
