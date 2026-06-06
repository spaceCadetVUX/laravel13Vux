import { z } from "zod";
import { api, ok } from "../client.js";
export function registerSprint4Tools(server) {
    server.tool("get_brand_context", "Load full brand context trước khi viết content.", {
        slug: z.string().describe("Brand slug"),
    }, async ({ slug }) => ok(await api("GET", `/mcp/brands/${slug}/context`)));
    server.tool("save_brand", "Upsert brand — tạo mới hoặc update content, SEO.", {
        slug: z.string().describe("Brand slug — dùng làm URL"),
        name: z.string().optional().describe("Tên brand"),
        description: z.string().optional().describe("Mô tả brand"),
        website: z.string().optional().describe("Website URL"),
        sort_order: z.number().optional().describe("Thứ tự sắp xếp"),
        overwrite_existing: z.boolean().default(false).describe("true = ghi đè content đã có"),
        dry_run: z.boolean().default(false).describe("true = preview, không lưu DB"),
        seo: z.record(z.object({
            meta_title: z.string().optional().describe("SEO title (≤60 ký tự)"),
            meta_description: z.string().optional().describe("SEO description (≤160 ký tự)"),
            robots: z.string().optional().describe('e.g. "index,follow"'),
        })).optional().describe('{"vi": {...}, "en": {...}}'),
    }, async ({ slug, ...body }) => ok(await api("PUT", `/mcp/brands/${slug}`, body)));
    server.tool("activate_brand", "Activate brand. Observer tự sync JSON-LD + Sitemap.", {
        slug: z.string().describe("Brand slug"),
    }, async ({ slug }) => ok(await api("PATCH", `/mcp/brands/${slug}/activate`, {})));
    server.tool("get_manufacturer_context", "Load full manufacturer context trước khi viết content.", {
        slug: z.string().describe("Manufacturer slug"),
    }, async ({ slug }) => ok(await api("GET", `/mcp/manufacturers/${slug}/context`)));
    server.tool("save_manufacturer", "Upsert manufacturer — tạo mới hoặc update content, SEO.", {
        slug: z.string().describe("Manufacturer slug — dùng làm URL"),
        name: z.string().optional().describe("Tên manufacturer"),
        description: z.string().optional().describe("Mô tả manufacturer"),
        website: z.string().optional().describe("Website URL"),
        country: z.string().optional().describe("Quốc gia, e.g. 'Germany'"),
        overwrite_existing: z.boolean().default(false).describe("true = ghi đè content đã có"),
        dry_run: z.boolean().default(false).describe("true = preview, không lưu DB"),
        seo: z.record(z.object({
            meta_title: z.string().optional().describe("SEO title (≤60 ký tự)"),
            meta_description: z.string().optional().describe("SEO description (≤160 ký tự)"),
            robots: z.string().optional().describe('e.g. "index,follow"'),
        })).optional().describe('{"vi": {...}, "en": {...}}'),
    }, async ({ slug, ...body }) => ok(await api("PUT", `/mcp/manufacturers/${slug}`, body)));
    server.tool("activate_manufacturer", "Activate manufacturer. Observer tự sync JSON-LD + Sitemap.", {
        slug: z.string().describe("Manufacturer slug"),
    }, async ({ slug }) => ok(await api("PATCH", `/mcp/manufacturers/${slug}/activate`, {})));
}
