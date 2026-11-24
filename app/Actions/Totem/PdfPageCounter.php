<?php

namespace App\Actions\Totem;

/**
 * Counts PDF pages without external tools.
 *
 * Reads the page tree "/Type /Pages ... /Count N" entries, also inside
 * Flate-compressed object streams (PDF 1.5+). The root page tree holds the
 * largest count. Encrypted or unusual PDFs return null and are rejected; a
 * dedicated tool (qpdf/Gotenberg) should replace this when added to the stack.
 */
class PdfPageCounter
{
    public function count(string $contents): ?int
    {
        if (! str_starts_with(ltrim($contents), '%PDF-') || str_contains($contents, '/Encrypt')) {
            return null;
        }

        $count = $this->largestPageTreeCount($contents);

        if ($count === null && preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $contents, $streams)) {
            foreach ($streams[1] as $stream) {
                $inflated = @gzuncompress($stream);

                if ($inflated !== false) {
                    $count = max($count ?? 0, $this->largestPageTreeCount($inflated) ?? 0) ?: null;
                }
            }
        }

        return $count;
    }

    private function largestPageTreeCount(string $contents): ?int
    {
        if (! preg_match_all('#/Type\s*/Pages\b[^>]*?/Count\s+(\d+)|/Count\s+(\d+)[^>]*?/Type\s*/Pages\b#s', $contents, $matches, PREG_SET_ORDER)) {
            return null;
        }

        $counts = array_map(fn (array $match): int => (int) (($match[1] ?? '') !== '' ? $match[1] : ($match[2] ?? 0)), $matches);

        return max($counts) ?: null;
    }
}
