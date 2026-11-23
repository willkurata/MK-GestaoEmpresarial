<?php

namespace App\Actions\CyberCoffe;

use Illuminate\Support\Str;

/**
 * Builds a thermal-printer ticket as ESC/POS bytes plus a plain-text preview.
 * Text is transliterated to ASCII so it prints on any code page.
 */
class BuildPrintTicket
{
    private const string INITIALIZE = "\x1B\x40";

    private const string ALIGN_LEFT = "\x1B\x61\x00";

    private const string ALIGN_CENTER = "\x1B\x61\x01";

    private const string BOLD_ON = "\x1B\x45\x01";

    private const string BOLD_OFF = "\x1B\x45\x00";

    private const string FEED_AND_CUT = "\n\n\n\x1D\x56\x41\x03";

    /**
     * @param  list<array{0: string, 1: string}>  $rows  label/value pairs
     * @return array{content: string, payload: string}
     */
    public function handle(string $storeName, string $title, array $rows, int $width): array
    {
        $separator = str_repeat('-', $width);
        $header = [$this->fit(Str::upper($storeName), $width), $this->fit($title, $width)];
        $body = array_map(fn (array $row): string => $this->row($row[0], $row[1], $width), $rows);
        $footer = $this->fit('Emitido em '.now()->format('d/m/Y H:i'), $width);

        $content = implode("\n", [...$header, $separator, ...$body, $separator, $footer])."\n";

        $bytes = self::INITIALIZE
            .self::ALIGN_CENTER.self::BOLD_ON.$header[0]."\n".self::BOLD_OFF.$header[1]."\n"
            .self::ALIGN_LEFT.$separator."\n"
            .implode("\n", $body)."\n"
            .$separator."\n"
            .self::ALIGN_CENTER.$footer
            .self::FEED_AND_CUT;

        return ['content' => $content, 'payload' => base64_encode($bytes)];
    }

    private function row(string $label, string $value, int $width): string
    {
        $label = Str::ascii($label);
        $value = Str::ascii($value);
        $space = $width - strlen($label) - strlen($value);

        if ($space < 1) {
            return $this->fit($label, $width)."\n".str_pad($this->fit($value, $width), $width, ' ', STR_PAD_LEFT);
        }

        return $label.str_repeat(' ', $space).$value;
    }

    private function fit(string $text, int $width): string
    {
        return substr(Str::ascii($text), 0, $width);
    }
}
