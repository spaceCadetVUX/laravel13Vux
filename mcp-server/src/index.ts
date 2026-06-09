import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { StreamableHTTPServerTransport } from "@modelcontextprotocol/sdk/server/streamableHttp.js";
import { createServer } from "http";
import { registerSprint0Tools } from "./tools/sprint0.js";
import { registerSprint1Tools } from "./tools/sprint1.js";
import { registerSprint2Tools } from "./tools/sprint2.js";
import { registerSprint3Tools } from "./tools/sprint3.js";
import { registerSprint4Tools } from "./tools/sprint4.js";
import { registerSprint5Tools } from "./tools/sprint5.js";
import { registerSprint6Tools } from "./tools/sprint6.js";

function buildServer() {
  const s = new McpServer({ name: "knxstore-mcp", version: "1.0.0" });
  registerSprint0Tools(s);
  registerSprint1Tools(s);
  registerSprint2Tools(s);
  registerSprint3Tools(s);
  registerSprint4Tools(s);
  registerSprint5Tools(s);
  registerSprint6Tools(s);
  return s;
}

const HTTP_MODE = process.env["MCP_HTTP"] === "1";
const PORT      = Number(process.env["MCP_PORT"] ?? 3100);
const API_KEY   = process.env["MCP_API_KEY"] ?? "";

if (HTTP_MODE) {
  const http = createServer(async (req, res) => {
    if (API_KEY && req.headers["x-api-key"] !== API_KEY) {
      res.writeHead(401, { "Content-Type": "application/json" });
      res.end(JSON.stringify({ error: "Unauthorized" }));
      return;
    }
    if (req.url === "/mcp" || req.url?.startsWith("/mcp?")) {
      const transport = new StreamableHTTPServerTransport({ sessionIdGenerator: undefined });
      await buildServer().connect(transport);
      await transport.handleRequest(req, res);
    } else {
      res.writeHead(404);
      res.end("Not found");
    }
  });
  http.listen(PORT, () => console.log(`MCP HTTP listening on :${PORT}`));
} else {
  await buildServer().connect(new StdioServerTransport());
}
