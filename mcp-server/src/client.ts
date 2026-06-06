const BASE  = process.env.KNXSTORE_API_BASE!;
const TOKEN = process.env.KNXSTORE_API_TOKEN!;

export async function api(
  method: "GET" | "PUT" | "PATCH" | "POST",
  path: string,
  bodyOrParams?: unknown,
): Promise<unknown> {
  // GET: bodyOrParams is params object → append as query string
  // PUT/PATCH/POST: bodyOrParams is request body → JSON stringify
  let url  = `${BASE}${path}`;
  let body: string | undefined;

  if (method === "GET" && bodyOrParams && typeof bodyOrParams === "object") {
    const qs = new URLSearchParams(
      Object.entries(bodyOrParams as Record<string, unknown>)
        .filter(([, v]) => v !== undefined && v !== null)
        .map(([k, v]) => [k, String(v)])
    ).toString();
    if (qs) url += `?${qs}`;
  } else if (bodyOrParams !== undefined) {
    body = JSON.stringify(bodyOrParams);
  }

  const res = await fetch(url, {
    method,
    headers: {
      "Authorization": `Bearer ${TOKEN}`,
      "Content-Type":  "application/json",
      "Accept":        "application/json",
    },
    body,
  });

  const json = await res.json() as Record<string, unknown>;

  if (!res.ok) {
    const msg  = (json?.message as string) ?? `HTTP ${res.status}`;
    const errs = json?.errors ? JSON.stringify(json.errors) : "";
    throw new Error(`${msg}${errs ? " — " + errs : ""}`);
  }

  return json;
}

/** Wrap any data into MCP tool response. Exported and used in every sprint. */
export function ok(data: unknown) {
  return { content: [{ type: "text" as const, text: JSON.stringify(data, null, 2) }] };
}
