import { z } from "zod";
import { api, ok } from "../client.js";
export function registerSprint2Tools(server) {
    server.tool("get_category_context", "Load full category context trước khi viết content. Gọi đầu tiên.", {
        slug: z.string().describe("Category slug"),
    }, async ({ slug }) => ok(await api("GET", `/mcp/categories/${slug}/context`)));
    server.tool("check_category_readiness", "Kiểm tra category đủ điều kiện activate chưa. Gọi trước activate_category.", {
        slug: z.string().describe("Category slug"),
    }, async ({ slug }) => ok(await api("GET", `/mcp/categories/${slug}/readiness`)));
    server.tool("save_category", "Upsert category — tạo mới hoặc update content, translations, SEO, GEO/FAQ.", {
        slug: z.string().describe("Category slug — dùng làm URL"),
        name: z.string().optional().describe("Tên category (fallback)"),
        parent_slug: z.string().optional().describe("Slug category cha"),
        sort_order: z.number().optional().describe("Thứ tự sắp xếp"),
        overwrite_existing: z.boolean().default(false).describe("true = ghi đè content đã có"),
        dry_run: z.boolean().default(false).describe("true = preview, không lưu DB"),
        translations: z.record(z.object({
            name: z.string().optional().describe("Tên category theo locale"),
            slug: z.string().optional().describe("Slug theo locale"),
            description: z.string().optional().describe("Mô tả ngắn"),
            rich_content: z.string().optional().describe("Nội dung rich text (HTML ok)"),
        })).optional().describe('{"vi": {...}, "en": {...}}'),
        seo: z.record(z.object({
            meta_title: z.string().optional().describe("SEO title (≤60 ký tự)"),
            meta_description: z.string().optional().describe("SEO description (≤160 ký tự)"),
            og_title: z.string().optional().describe("Open Graph title"),
            og_description: z.string().optional().describe("Open Graph description"),
            twitter_title: z.string().optional().describe("Twitter card title"),
            twitter_description: z.string().optional().describe("Twitter card description"),
        })).optional().describe('{"vi": {...}, "en": {...}}'),
        geo: z.record(z.object({
            ai_summary: z.string().optional().describe("Tóm tắt cho AI/GEO"),
            use_cases: z.string().optional().describe("Các use case điển hình"),
            target_audience: z.string().optional().describe("Đối tượng mục tiêu"),
            llm_context_hint: z.string().optional().describe("Hint cho LLM"),
            key_facts: z.array(z.object({
                label: z.string().describe("Nhãn"),
                value: z.string().describe("Giá trị"),
            })).optional().describe("Thông tin nổi bật"),
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
    }, async ({ slug, ...body }) => ok(await api("PUT", `/mcp/categories/${slug}`, body)));
    server.tool("activate_category", "Activate category sau khi readiness pass. Observer tự sync JSON-LD + Sitemap.", {
        slug: z.string().describe("Category slug"),
    }, async ({ slug }) => ok(await api("PATCH", `/mcp/categories/${slug}/activate`, {})));
}
