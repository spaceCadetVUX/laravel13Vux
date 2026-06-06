import { z } from "zod";
import { api, ok } from "../client.js";
export function registerSprint0Tools(server) {
    server.tool("audit_site_content", "Site-wide audit — tìm entity thiếu content, SEO, translations. Gọi đầu tiên để nắm tình trạng.", {
        model_type: z.string().optional().describe('Lọc theo loại: "product,category,blog_post,..."'),
        locale: z.string().optional().describe('Lọc theo locale: "vi,en"'),
        missing: z.string().optional().describe('Lọc theo field thiếu: "description,meta_title,..."'),
        is_active: z.boolean().optional().describe("Lọc theo trạng thái active"),
        per_page: z.number().default(50).describe("Số kết quả mỗi trang"),
        page: z.number().default(1).describe("Trang hiện tại"),
    }, async (params) => ok(await api("GET", "/mcp/audit", params)));
    server.tool("list_entities", "List entities theo loại với filter. Dùng để xem danh sách cần xử lý.", {
        model_type: z.enum(["products", "categories", "blog-posts", "blog-categories", "brands", "manufacturers"]).describe("Loại entity cần list"),
        has_description: z.boolean().optional().describe("Lọc theo có/không có description"),
        has_seo: z.boolean().optional().describe("Lọc theo có/không có SEO meta"),
        locale: z.string().optional().describe("Lọc theo locale"),
        per_page: z.number().default(20).describe("Số kết quả mỗi trang"),
    }, async ({ model_type, ...params }) => ok(await api("GET", `/mcp/${model_type}`, params)));
    server.tool("search_entities", "Tìm entity theo keyword. Dùng khi biết tên sản phẩm/danh mục nhưng không biết slug.", {
        q: z.string().describe("Từ khóa tìm kiếm"),
        types: z.string().optional().describe('Lọc theo loại: "product,category,brand,manufacturer"'),
        locale: z.string().default("vi").describe("Locale tìm kiếm"),
        per_page: z.number().default(10).describe("Số kết quả tối đa"),
    }, async (params) => ok(await api("GET", "/mcp/search", params)));
    server.tool("get_review_queue", "Lấy danh sách draft MCP chưa được review. Dùng để biết việc gì đang chờ xử lý.", {
        model_type: z.string().optional().describe("Lọc theo loại entity"),
        drafted_after: z.string().optional().describe("Lọc draft sau ngày này (ISO date, e.g. 2024-01-01)"),
        per_page: z.number().default(20).describe("Số kết quả mỗi trang"),
    }, async (params) => ok(await api("GET", "/mcp/review-queue", params)));
}
