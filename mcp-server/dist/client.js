const BASE = process.env.KNXSTORE_API_BASE;
const TOKEN = process.env.KNXSTORE_API_TOKEN;
export async function api(method, path, bodyOrParams) {
    // GET: bodyOrParams is params object → append as query string
    // PUT/PATCH/POST: bodyOrParams is request body → JSON stringify
    let url = `${BASE}${path}`;
    let body;
    if (method === "GET" && bodyOrParams && typeof bodyOrParams === "object") {
        const qs = new URLSearchParams(Object.entries(bodyOrParams)
            .filter(([, v]) => v !== undefined && v !== null)
            .map(([k, v]) => [k, String(v)])).toString();
        if (qs)
            url += `?${qs}`;
    }
    else if (bodyOrParams !== undefined) {
        body = JSON.stringify(bodyOrParams);
    }
    const res = await fetch(url, {
        method,
        headers: {
            "Authorization": `Bearer ${TOKEN}`,
            "Content-Type": "application/json",
            "Accept": "application/json",
        },
        body,
    });
    const json = await res.json();
    if (!res.ok) {
        const msg = json?.message ?? `HTTP ${res.status}`;
        const errs = json?.errors ? JSON.stringify(json.errors) : "";
        throw new Error(`${msg}${errs ? " — " + errs : ""}`);
    }
    return json;
}
/** Wrap any data into MCP tool response. Exported and used in every sprint. */
export function ok(data) {
    return { content: [{ type: "text", text: JSON.stringify(data, null, 2) }] };
}
