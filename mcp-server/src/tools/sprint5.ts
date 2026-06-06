import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod";
import { api, ok } from "../client.js";

export function registerSprint5Tools(server: McpServer) {

  server.tool(
    "bulk_seo_fill",
    "Điền SEO meta hàng loạt cho nhiều entity. Tối đa 50 items mỗi lần.",
    {
      items: z.array(z.object({
        model_type: z.enum(["product","category","blog_post","blog_category","brand","manufacturer"]).describe("Loại entity"),
        slug:       z.string().describe("Entity slug"),
      })).max(50).describe("Danh sách entity cần điền SEO, tối đa 50"),
      locales:            z.array(z.string()).default(["vi","en"]).describe('Locales cần điền, e.g. ["vi","en"]'),
      overwrite_existing: z.boolean().default(false).describe("true = ghi đè SEO đã có"),
    },
    async (body) => ok(await api("POST", "/mcp/batch/seo-meta", body)),
  );

  server.tool(
    "bulk_translate",
    "Dịch hàng loạt content sang locale khác. Tối đa 20 items mỗi lần.",
    {
      items: z.array(z.object({
        model_type: z.enum(["product","category","blog_post","blog_category","brand","manufacturer"]).describe("Loại entity"),
        slug:       z.string().describe("Entity slug"),
      })).max(20).describe("Danh sách entity cần dịch, tối đa 20"),
      from_locale:        z.string().default("vi").describe("Locale nguồn"),
      to_locale:          z.string().default("en").describe("Locale đích"),
      fields:             z.array(z.string()).optional().describe('Fields cần dịch, e.g. ["name","description"]. Không truyền = dịch tất cả.'),
      overwrite_existing: z.boolean().default(false).describe("true = ghi đè translation đã có"),
      dry_run:            z.boolean().default(false).describe("true = preview, không lưu DB"),
    },
    async (body) => ok(await api("POST", "/mcp/batch/translate", body)),
  );
}
