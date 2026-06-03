<x-filament-panels::page>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:1.5rem;align-items:start;">

    {{-- ── Developer Card ───────────────────────────────────────────────── --}}
    <div style="background:var(--fi-bg);border:1px solid color-mix(in srgb,currentColor 15%,transparent);border-radius:0.75rem;padding:1.5rem;box-shadow:0 1px 3px rgba(0,0,0,.08);">

        {{-- Avatar + Name --}}
        <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem;">
            <div style="width:60px;height:60px;border-radius:9999px;background:linear-gradient(135deg,#6366f1,#a855f7);display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:700;color:#fff;flex-shrink:0;box-shadow:0 2px 8px rgba(99,102,241,.4);">
                V
            </div>
            <div>
                <p style="font-size:1.05rem;font-weight:700;margin:0;">Tạ Minh Vũ</p>
                <p style="font-size:0.8rem;color:#6b7280;margin:2px 0 0;">Web Dev</p>
            </div>
        </div>

        <hr style="border:none;border-top:1px solid color-mix(in srgb,currentColor 10%,transparent);margin:1rem 0;">

        {{-- Contact --}}
        <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:0.6rem;font-size:0.85rem;">
            <li style="display:flex;align-items:center;gap:0.5rem;">
                <span style="color:#6366f1;">📱</span>
                <span>0337 741 184</span>
            </li>
            <li style="display:flex;align-items:center;gap:0.5rem;">
                <span style="color:#06b6d4;">💬</span>
                <a href="https://zalo.me/0337741184" target="_blank" style="color:#06b6d4;text-decoration:none;">Zalo: 0337 741 184</a>
            </li>
            <li style="display:flex;align-items:center;gap:0.5rem;">
                <svg viewBox="0 0 16 16" width="16" height="16" fill="currentColor" style="flex-shrink:0;"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/></svg>
                <a href="https://github.com/spaceCadetVUX" target="_blank" style="color:#6366f1;text-decoration:none;">github.com/spaceCadetVUX</a>
            </li>
            <li style="display:flex;align-items:center;gap:0.5rem;">
                <span>🌐</span>
                <span>knxstore.vn</span>
            </li>
            <li style="display:flex;align-items:center;gap:0.5rem;">
                <span>🏢</span>
                <span style="font-size:0.78rem;color:#6b7280;">Công ty CP Tích hợp Hệ thống Liên Minh</span>
            </li>
        </ul>

        <hr style="border:none;border-top:1px solid color-mix(in srgb,currentColor 10%,transparent);margin:1rem 0;">

        {{-- Stack tags --}}
        <p style="font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;margin:0 0 0.6rem;">Stack</p>
        <div style="display:flex;flex-wrap:wrap;gap:0.4rem;">
            @foreach (['Laravel', 'Nuxt 3', 'PHP 8.5', 'PostgreSQL', 'Filament', 'TypeScript', 'Redis', 'Meilisearch', 'Claude API', 'Blade'] as $tag)
                <span style="background:rgba(99,102,241,.1);color:#6366f1;border-radius:9999px;padding:2px 10px;font-size:0.72rem;font-weight:500;">{{ $tag }}</span>
            @endforeach
        </div>
    </div>

    {{-- ── Right column ──────────────────────────────────────────────────── --}}
    <div style="display:flex;flex-direction:column;gap:1.5rem;">

        {{-- Tech Stack --}}
        <div style="background:var(--fi-bg);border:1px solid color-mix(in srgb,currentColor 15%,transparent);border-radius:0.75rem;padding:1.5rem;box-shadow:0 1px 3px rgba(0,0,0,.08);">
            <h2 style="font-size:0.95rem;font-weight:600;margin:0 0 1rem;display:flex;align-items:center;gap:0.5rem;">
                ⚙️ Tech Stack
            </h2>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
                @foreach ($this->getStack() as $label => $value)
                    <div style="background:color-mix(in srgb,currentColor 4%,transparent);border-radius:0.5rem;padding:0.65rem 0.9rem;">
                        <div style="font-size:0.68rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;">{{ $label }}</div>
                        <div style="font-size:0.82rem;font-weight:500;margin-top:2px;">{{ $value }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- System Info --}}
        <div style="background:var(--fi-bg);border:1px solid color-mix(in srgb,currentColor 15%,transparent);border-radius:0.75rem;padding:1.5rem;box-shadow:0 1px 3px rgba(0,0,0,.08);">
            <h2 style="font-size:0.95rem;font-weight:600;margin:0 0 1rem;display:flex;align-items:center;gap:0.5rem;">
                🖥️ System Info
                <span style="margin-left:auto;background:rgba(16,185,129,.15);color:#10b981;border-radius:9999px;padding:2px 10px;font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;">
                    {{ config('app.env') }}
                </span>
            </h2>
            <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
                @foreach ($this->getSystemInfo() as $label => $value)
                    <tr style="border-top:1px solid color-mix(in srgb,currentColor 8%,transparent);">
                        <td style="padding:0.55rem 0;color:#6b7280;font-weight:500;width:35%;">{{ $label }}</td>
                        <td style="padding:0.55rem 0;font-family:monospace;font-size:0.8rem;">{{ $value }}</td>
                    </tr>
                @endforeach
            </table>
        </div>

    </div>
</div>

</x-filament-panels::page>
