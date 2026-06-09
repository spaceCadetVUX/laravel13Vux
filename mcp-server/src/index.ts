import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { registerSprint0Tools } from "./tools/sprint0.js";
import { registerSprint1Tools } from "./tools/sprint1.js";
import { registerSprint2Tools } from "./tools/sprint2.js";
import { registerSprint3Tools } from "./tools/sprint3.js";
import { registerSprint4Tools } from "./tools/sprint4.js";
import { registerSprint5Tools } from "./tools/sprint5.js";
import { registerSprint6Tools } from "./tools/sprint6.js";

const server = new McpServer({
  name:    "knxstore-mcp",
  version: "1.0.0",
});

registerSprint0Tools(server);
registerSprint1Tools(server);
registerSprint2Tools(server);
registerSprint3Tools(server);
registerSprint4Tools(server);
registerSprint5Tools(server);
registerSprint6Tools(server);

const transport = new StdioServerTransport();
await server.connect(transport);
