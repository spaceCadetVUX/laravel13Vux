import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { StreamableHTTPServerTransport } from "@modelcontextprotocol/sdk/server/streamableHttp.js";
import { createServer } from "http";
import { randomUUID } from "crypto";
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
function readBody(req) {
    return new Promise((resolve, reject) => {
        let data = "";
        req.on("data", (chunk) => (data += chunk));
        req.on("end", () => resolve(data));
        req.on("error", reject);
    });
}
const HTTP_MODE = process.env["MCP_HTTP"] === "1";
const PORT = Number(process.env["MCP_PORT"] ?? 3100);
const API_KEY = process.env["MCP_API_KEY"] ?? "";
if (HTTP_MODE) {
    const sessions = new Map();
    const http = createServer(async (req, res) => {
        // Auth — accept X-Api-Key or Authorization: Bearer <key>
        if (API_KEY) {
            const xKey = req.headers["x-api-key"];
            const bearer = (req.headers["authorization"] ?? "").toString().replace(/^Bearer\s+/i, "");
            if (xKey !== API_KEY && bearer !== API_KEY) {
                res.writeHead(401, { "Content-Type": "application/json" });
                res.end(JSON.stringify({ error: "Unauthorized" }));
                return;
            }
        }
        if (req.url !== "/mcp") {
            res.writeHead(404);
            res.end("Not found");
            return;
        }
        // POST /mcp — initialize or tool call
        if (req.method === "POST") {
            let parsed;
            try {
                const raw = await readBody(req);
                parsed = JSON.parse(raw);
            }
            catch {
                res.writeHead(400, { "Content-Type": "application/json" });
                res.end(JSON.stringify({ error: "Invalid JSON" }));
                return;
            }
            const isInit = parsed["method"] === "initialize";
            const sessionId = req.headers["mcp-session-id"];
            if (isInit) {
                // New session
                let transport;
                transport = new StreamableHTTPServerTransport({
                    sessionIdGenerator: () => randomUUID(),
                    onsessioninitialized: (id) => { sessions.set(id, transport); },
                });
                transport.onclose = () => {
                    if (transport.sessionId)
                        sessions.delete(transport.sessionId);
                };
                await buildServer().connect(transport);
                await transport.handleRequest(req, res, parsed);
                return;
            }
            if (sessionId && sessions.has(sessionId)) {
                await sessions.get(sessionId).handleRequest(req, res, parsed);
                return;
            }
            res.writeHead(400, { "Content-Type": "application/json" });
            res.end(JSON.stringify({ error: "No valid session. Send initialize first." }));
            return;
        }
        // GET /mcp — server-sent notifications
        if (req.method === "GET") {
            const sessionId = req.headers["mcp-session-id"];
            if (sessionId && sessions.has(sessionId)) {
                await sessions.get(sessionId).handleRequest(req, res);
                return;
            }
            res.writeHead(400);
            res.end("No valid session");
            return;
        }
        // DELETE /mcp — close session
        if (req.method === "DELETE") {
            const sessionId = req.headers["mcp-session-id"];
            if (sessionId && sessions.has(sessionId)) {
                await sessions.get(sessionId).handleRequest(req, res);
                sessions.delete(sessionId);
                return;
            }
            res.writeHead(400);
            res.end("No valid session");
            return;
        }
        res.writeHead(405);
        res.end("Method not allowed");
    });
    http.listen(PORT, () => console.log(`MCP Streamable HTTP listening on :${PORT}`));
}
else {
    await buildServer().connect(new StdioServerTransport());
}
