import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { StreamableHTTPServerTransport } from "@modelcontextprotocol/sdk/server/streamableHttp.js";
import { createServer } from "node:http";
import { registerSprint0Tools } from "./tools/sprint0.js";
import { registerSprint1Tools } from "./tools/sprint1.js";
import { registerSprint2Tools } from "./tools/sprint2.js";
import { registerSprint3Tools } from "./tools/sprint3.js";
import { registerSprint4Tools } from "./tools/sprint4.js";
import { registerSprint5Tools } from "./tools/sprint5.js";
import { registerSprint6Tools } from "./tools/sprint6.js";
function createMcpServer() {
    const server = new McpServer({ name: "knxstore-mcp", version: "1.0.0" });
    registerSprint0Tools(server);
    registerSprint1Tools(server);
    registerSprint2Tools(server);
    registerSprint3Tools(server);
    registerSprint4Tools(server);
    registerSprint5Tools(server);
    registerSprint6Tools(server);
    return server;
}
const HTTP_MODE = process.env.MCP_HTTP === "1";
const PORT = parseInt(process.env.MCP_PORT ?? "3100");
const API_KEY = process.env.MCP_API_KEY ?? "";
if (HTTP_MODE) {
    const httpServer = createServer(async (req, res) => {
        // Auth check
        const key = req.headers["x-api-key"] ?? "";
        if (API_KEY && key !== API_KEY) {
            res.writeHead(401, { "Content-Type": "application/json" });
            res.end(JSON.stringify({ error: "Unauthorized" }));
            return;
        }
        if (req.url === "/mcp" || req.url?.startsWith("/mcp?")) {
            const transport = new StreamableHTTPServerTransport({ sessionIdGenerator: undefined });
            const server = createMcpServer();
            await server.connect(transport);
            await transport.handleRequest(req, res);
        }
        else {
            res.writeHead(404);
            res.end("Not found");
        }
    });
    httpServer.listen(PORT, () => {
        console.log(`MCP HTTP server running on port ${PORT}`);
    });
}
else {
    const server = createMcpServer();
    const transport = new StdioServerTransport();
    await server.connect(transport);
}
