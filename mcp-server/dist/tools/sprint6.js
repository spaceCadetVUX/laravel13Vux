import { z } from "zod";
import { api, ok } from "../client.js";
export function registerSprint6Tools(server) {
    server.tool("import_from_specs", "Parse datasheet/spec text và suggest content cho product. KHÔNG tự lưu — đọc response rồi gọi save_product để lưu chính thức.", {
        slug: z.string().describe("Product slug — entity đích để import vào"),
        manufacturer_slug: z.string().optional().describe("Slug của manufacturer"),
        category_slug: z.string().optional().describe("Slug của category"),
        specs_text: z.string().describe("Raw text từ datasheet, catalog, hoặc spec sheet"),
        locales: z.array(z.string()).default(["vi", "en"]).describe('Locales cần generate content, e.g. ["vi","en"]'),
        auto_activate: z.boolean().default(false).describe("true = tự activate sau khi import (chỉ khi readiness pass)"),
    }, async (body) => ok(await api("POST", "/mcp/import/product-from-specs", body)));
}
