import { z } from "zod";
import { api, ok } from "../client.js";
export function registerSprint3Tools(server) {
    server.tool("get_blog_post_context", "Load full blog post context trước khi viết content. Gọi đầu tiên.", {
        slug: z.string().describe("Blog post slug"),
    }, async ({ slug }) => ok(await api("GET", `/mcp/blog-posts/${slug}/context`)));
    server.tool("save_blog_post", "Upsert blog post — tạo mới hoặc update content, translations, SEO, GEO/FAQ.", {
        slug: z.string().describe("Blog post slug — dùng làm URL"),
        blog_category_slug: z.string().optional().describe("Slug của blog category"),
        author_slug: z.string().optional().describe("Slug của tác giả"),
        tags: z.array(z.string()).optional().describe("Danh sách tag slugs"),
        overwrite_existing: z.boolean().default(false).describe("true = ghi đè content đã có"),
        dry_run: z.boolean().default(false).describe("true = preview, không lưu DB"),
        translations: z.record(z.object({
            title: z.string().optional().describe("Tiêu đề bài viết theo locale"),
            slug: z.string().optional().describe("Slug theo locale"),
            excerpt: z.string().optional().describe("Tóm tắt ngắn"),
            body: z.string().optional().describe("Nội dung đầy đủ (HTML/Markdown ok)"),
        })).optional().describe('{"vi": {...}, "en": {...}}'),
        seo: z.record(z.object({
            meta_title: z.string().optional().describe("SEO title (≤60 ký tự)"),
            meta_description: z.string().optional().describe("SEO description (≤160 ký tự)"),
            meta_keywords: z.string().optional().describe("Keywords, phân cách bằng dấu phẩy"),
            canonical_url: z.string().optional().describe("Canonical URL — để trống = tự sinh từ slug"),
            og_title: z.string().optional().describe("OG title cho Facebook/Zalo"),
            og_description: z.string().optional().describe("OG description"),
            og_image: z.string().optional().describe("OG image path (từ media library)"),
            robots: z.string().optional().describe("index,follow | noindex,follow | noindex,nofollow"),
        })).optional().describe('{"vi": {...}, "en": {...}}'),
        geo: z.record(z.object({
            ai_summary: z.string().optional().describe("Tóm tắt cho AI/GEO"),
            use_cases: z.string().optional().describe("Các use case điển hình"),
            target_audience: z.string().optional().describe("Đối tượng mục tiêu"),
            llm_context_hint: z.string().optional().describe("Hint cho LLM"),
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
    }, async ({ slug, ...body }) => ok(await api("PUT", `/mcp/blog-posts/${slug}`, body)));
    server.tool("publish_blog_post", "Publish blog post. Có thể lên lịch bằng published_at.", {
        slug: z.string().describe("Blog post slug"),
        published_at: z.string().optional().describe("Thời điểm publish (ISO 8601). Không truyền = publish ngay."),
    }, async ({ slug, ...body }) => ok(await api("PATCH", `/mcp/blog-posts/${slug}/publish`, body)));
    server.tool("get_blog_category_context", "Load full blog category context trước khi viết content.", {
        slug: z.string().describe("Blog category slug"),
    }, async ({ slug }) => ok(await api("GET", `/mcp/blog-categories/${slug}/context`)));
    server.tool("save_blog_category", "Upsert blog category — tạo mới hoặc update content, translations, SEO, GEO/AI.", {
        slug: z.string().describe("Blog category slug — dùng làm URL"),
        name: z.string().optional().describe("Tên nội bộ (dùng trong admin)"),
        parent_slug: z.string().optional().describe("Slug của category cha"),
        sort_order: z.number().optional().describe("Thứ tự sắp xếp"),
        overwrite_existing: z.boolean().default(false).describe("true = ghi đè content đã có"),
        dry_run: z.boolean().default(false).describe("true = preview, không lưu DB"),
        translations: z.record(z.object({
            name: z.string().optional().describe("Tên hiển thị theo locale"),
            slug: z.string().optional().describe("URL slug theo locale"),
            description: z.string().optional().describe("Mô tả ngắn (hiển thị trên trang category)"),
            rich_content: z.string().optional().describe("Nội dung dài (HTML) — hiển thị cuối trang"),
        })).optional().describe('{"vi": {...}, "en": {...}}'),
        seo: z.record(z.object({
            meta_title: z.string().optional().describe("SEO title (≤60 ký tự)"),
            meta_description: z.string().optional().describe("SEO description (≤160 ký tự)"),
            meta_keywords: z.string().optional().describe("Keywords, phân cách bằng dấu phẩy"),
            canonical_url: z.string().optional().describe("Canonical URL — để trống = tự tạo từ slug"),
            og_title: z.string().optional().describe("OG title cho Facebook/Zalo"),
            og_description: z.string().optional().describe("OG description"),
            og_image: z.string().optional().describe("OG image path (từ media library)"),
            robots: z.string().optional().describe("index,follow | noindex,follow | noindex,nofollow"),
        })).optional().describe('{"vi": {...}, "en": {...}}'),
        geo: z.record(z.object({
            ai_summary: z.string().optional().describe("Tóm tắt 2–4 câu cho AI/GEO hiểu category này"),
            use_cases: z.string().optional().describe("Chủ đề / ứng dụng điển hình của category"),
            target_audience: z.string().optional().describe("Đối tượng độc giả mục tiêu"),
            llm_context_hint: z.string().optional().describe("Gợi ý thêm cho LLM khi sinh nội dung"),
            key_facts: z.array(z.object({
                label: z.string().describe("Nhãn, VD: Số bài viết"),
                value: z.string().describe("Giá trị, VD: 50+"),
            })).optional().describe("Các số liệu nổi bật"),
            faq: z.array(z.object({
                question: z.string().describe("Câu hỏi"),
                answer: z.string().describe("Câu trả lời"),
            })).optional().describe("FAQ cho category — ít nhất 3 câu để đạt readiness"),
        })).optional().describe('{"vi": {...}, "en": {...}}'),
    }, async ({ slug, ...body }) => ok(await api("PUT", `/mcp/blog-categories/${slug}`, body)));
    server.tool("activate_blog_category", "Activate blog category. Observer tự sync JSON-LD + Sitemap.", {
        slug: z.string().describe("Blog category slug"),
    }, async ({ slug }) => ok(await api("PATCH", `/mcp/blog-categories/${slug}/activate`, {})));
}
