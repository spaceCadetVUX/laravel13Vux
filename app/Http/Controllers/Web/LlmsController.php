<?php

namespace App\Http\Controllers\Web;

use App\Enums\LlmsScope;
use App\Http\Controllers\Controller;
use App\Models\Seo\LlmsDocument;
use App\Services\Seo\LlmsGeneratorService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class LlmsController extends Controller
{
    public function __construct(
        private readonly LlmsGeneratorService $llmsService,
    ) {}

    /**
     * Serve the root llms.txt (scope=index document named 'root').
     * GET /llms.txt
     */
    public function index(): Response
    {
        /** @var LlmsDocument|null $document */
        $document = LlmsDocument::where('name', 'root')
            ->where('scope', LlmsScope::Index)
            ->where('is_active', true)
            ->first();

        if (! $document) {
            // No root document seeded yet — return a minimal stub.
            return $this->textResponse('# LLMs Index' . PHP_EOL . PHP_EOL . '_No documents available yet._' . PHP_EOL);
        }

        return $this->resolveOrGenerate($document);
    }

    /**
     * Serve the concatenated full-scope llms-full.txt.
     * GET /llms-full.txt
     */
    public function full(): Response
    {
        $documents = LlmsDocument::where('scope', LlmsScope::Full)
            ->where('is_active', true)
            ->orderBy('slug')
            ->get();

        if ($documents->isEmpty()) {
            return $this->textResponse('_No full documents available yet._' . PHP_EOL);
        }

        $content = $documents->map(function (LlmsDocument $document): string {
            $path = 'llms/' . $document->slug . '.txt';

            if (! Storage::disk('public')->exists($path)) {
                $this->llmsService->generateDocument($document);
            }

            return (string) Storage::disk('public')->get($path);
        })->implode(PHP_EOL . PHP_EOL . '---' . PHP_EOL . PHP_EOL);

        return $this->textResponse($content);
    }

    /**
     * Serve a specific scoped llms-{slug}.txt.
     * GET /llms-{slug}.txt
     */
    public function scoped(string $slug): Response
    {
        /** @var LlmsDocument|null $document */
        $document = LlmsDocument::where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (! $document) {
            abort(404);
        }

        return $this->resolveOrGenerate($document);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Try to read the pre-generated file from disk.
     * If missing, generate it on-the-fly, then read it back.
     */
    private function resolveOrGenerate(LlmsDocument $document): Response
    {
        $path = 'llms/' . $document->slug . '.txt';

        if (! Storage::disk('public')->exists($path)) {
            $this->llmsService->generateDocument($document);
        }

        $content = (string) Storage::disk('public')->get($path);

        return $this->textResponse($content);
    }

    /**
     * Build a plain-text HTTP response.
     */
    private function textResponse(string $content): Response
    {
        return response($content, 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
