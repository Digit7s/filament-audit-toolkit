<?php

namespace Digit7s\FilamentAuditToolkit\Json;

final class JsonSyntaxHighlighter
{
    /**
     * Tokenize JSON without producing unescaped HTML.
     *
     * @return array<int, array{type: string, text: string}>
     */
    public function tokenize(string $json): array
    {
        $tokens = [];
        $length = strlen($json);
        $offset = 0;

        while ($offset < $length) {
            $character = $json[$offset];

            if ($character === '"') {
                $end = $this->stringEnd($json, $offset);
                $text = substr($json, $offset, $end - $offset + 1);
                $after = $end + 1;

                while (($json[$after] ?? '') !== '' && ctype_space($json[$after])) {
                    $after++;
                }

                $tokens[] = [
                    'type' => (($json[$after] ?? '') === ':') ? 'key' : 'string',
                    'text' => $text,
                ];
                $offset = $end + 1;

                continue;
            }

            if (preg_match('/-?(?:0|[1-9]\d*)(?:\.\d+)?(?:[eE][+-]?\d+)?/A', substr($json, $offset), $matches) === 1) {
                $tokens[] = ['type' => 'number', 'text' => $matches[0]];
                $offset += strlen($matches[0]);

                continue;
            }

            foreach (['true', 'false', 'null'] as $literal) {
                if (strncmp(substr($json, $offset), $literal, strlen($literal)) !== 0) {
                    continue;
                }

                $tokens[] = ['type' => $literal === 'null' ? 'null' : 'boolean', 'text' => $literal];
                $offset += strlen($literal);

                continue 2;
            }

            $start = $offset;
            $offset++;

            while ($offset < $length && $json[$offset] !== '"' && ! preg_match('/[-0-9tfn]/', $json[$offset])) {
                $offset++;
            }

            $tokens[] = ['type' => 'punctuation', 'text' => substr($json, $start, $offset - $start)];
        }

        return $tokens;
    }

    private function stringEnd(string $json, int $start): int
    {
        $length = strlen($json);

        for ($offset = $start + 1; $offset < $length; $offset++) {
            if ($json[$offset] !== '"') {
                continue;
            }

            $backslashes = 0;
            for ($index = $offset - 1; $index > $start && $json[$index] === '\\'; $index--) {
                $backslashes++;
            }

            if (($backslashes % 2) === 0) {
                return $offset;
            }
        }

        return $length - 1;
    }
}
