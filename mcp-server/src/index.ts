import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { SSEServerTransport } from "@modelcontextprotocol/sdk/server/sse.js";
import { createServer, IncomingMessage, ServerResponse } from "http";
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
  const sessions = new Map<string, SSEServerTransport>();

  const http = createServer(async (req: IncomingMessage, res: ServerResponse) => {
    if (API_KEY && req.headers["x-api-key"] !== API_KEY) {
      res.writeHead(401, { "Content-Type": "application/json" });
      res.end(JSON.stringify({ error: "Unauthorized" }));
      return;
    }

    // GET /mcp → SSE stream (client subscribes)
    if (req.method === "GET" && req.url === "/mcp") {
      const transport = new SSEServerTransport("/mcp/messages", res);
      sessions.set(transport.sessionId, transport);
      transport.onclose = () => sessions.delete(transport.sessionId);
      await buildServer().connect(transport); // connect() calls start() internally
      return;
    }

    // POST /mcp/messages → client messages
    if (req.method === "POST" && req.url?.startsWith("/mcp/messages")) {
      const sessionId = new URL(req.url, "http://localhost").searchParams.get("sessionId") ?? "";
      const transport = sessions.get(sessionId);
      if (!transport) {
        res.writeHead(404);
        res.end("Session not found");
        return;
      }
      await transport.handlePostMessage(req, res);
      return;
    }

    res.writeHead(404);
    res.end("Not found");
  });

  http.listen(PORT, () => console.log(`MCP SSE listening on :${PORT}`));
} else {
  await buildServer().connect(new StdioServerTransport());
}
