<?php

namespace Digit7s\FilamentAuditToolkit\Json;

use JsonException;
use Throwable;

final class JsonViewerBuilder
{
    public function __construct(
        private readonly int $maxDepth = 5,
        private readonly int $maxEntries = 200,
        private readonly int $maxValueLength = 2_000,
    ) {}

    public function build(mixed $value): JsonViewerResult
    {
        $entries = 0;
        $truncated = false;
        $root = $this->node(null, $value, 0, $entries, $truncated);

        try {
            $jsonValue = $this->jsonValue($root);
            $json = json_encode(
                $jsonValue,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );
        } catch (Throwable) {
            $json = '[unable to render JSON]';
            $truncated = true;
        }

        return new JsonViewerResult(
            root: $root,
            json: $json,
            tokens: (new JsonSyntaxHighlighter)->tokenize($json),
            truncated: $truncated,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function node(mixed $key, mixed $value, int $depth, int &$entries, bool &$truncated): array
    {
        if ($entries >= max(1, $this->maxEntries)) {
            $truncated = true;

            return $this->omittedNode($key);
        }

        $entries++;
        $kind = $this->kind($value);
        $children = [];
        $isComposite = in_array($kind, ['object', 'array'], true);

        if ($isComposite && count($value) > 0) {
            if ($depth >= max(0, $this->maxDepth)) {
                $truncated = true;
            } else {
                foreach ($value as $childKey => $childValue) {
                    if ($entries >= max(1, $this->maxEntries)) {
                        $truncated = true;
                        $children[] = $this->omittedNode(null);

                        break;
                    }

                    $children[] = $this->node($childKey, $childValue, $depth + 1, $entries, $truncated);
                }
            }
        }

        return [
            'key' => $key,
            'kind' => $kind,
            'type_label' => $this->typeLabel($kind),
            'display' => $this->display(
                $value,
                $kind,
                ($depth >= max(0, $this->maxDepth)) && $isComposite && count($value) > 0,
                $truncated,
            ),
            'children' => $children,
            'empty' => $isComposite && count($value) === 0,
            'truncated' => ($depth >= max(0, $this->maxDepth)) && $isComposite && count($value) > 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function omittedNode(mixed $key): array
    {
        return [
            'key' => $key,
            'kind' => 'truncated',
            'type_label' => 'Omitted',
            'display' => '[additional values omitted]',
            'children' => [],
            'empty' => false,
            'truncated' => true,
        ];
    }

    private function kind(mixed $value): string
    {
        return match (true) {
            is_array($value) && array_is_list($value) => 'array',
            is_array($value) => 'object',
            is_string($value) => 'string',
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'number',
            $value === null => 'null',
            default => 'unsupported',
        };
    }

    private function typeLabel(string $kind): string
    {
        return match ($kind) {
            'object' => 'Object',
            'array' => 'Array',
            'string' => 'String',
            'boolean' => 'Boolean',
            'integer' => 'Integer',
            'number' => 'Number',
            'null' => 'Null',
            'truncated' => 'Omitted',
            default => 'Unsupported',
        };
    }

    private function display(mixed $value, string $kind, bool $nestedTruncated, bool &$truncated): string
    {
        if ($nestedTruncated) {
            return '[nested values omitted]';
        }

        if ($kind === 'truncated') {
            return '[additional values omitted]';
        }

        if ($kind === 'null') {
            return 'null';
        }

        if ($kind === 'boolean') {
            return $value ? 'true' : 'false';
        }

        if (in_array($kind, ['integer', 'number'], true)) {
            return (string) $value;
        }

        if ($kind === 'string') {
            try {
                $display = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return '[unserializable]';
            }

            if (mb_strlen($display) <= max(1, $this->maxValueLength)) {
                return $display;
            }

            $truncated = true;

            return mb_substr($display, 0, max(1, $this->maxValueLength)).'… [value truncated]"';
        }

        if ($kind === 'object') {
            return '{'.count($value).' '.str('entry')->plural(count($value)).'}';
        }

        if ($kind === 'array') {
            return '['.count($value).' '.str('item')->plural(count($value)).']';
        }

        return '[unsupported value]';
    }

    private function jsonValue(array $node): mixed
    {
        if ($node['truncated'] || $node['kind'] === 'truncated') {
            return $node['display'];
        }

        if (in_array($node['kind'], ['object', 'array'], true)) {
            $value = [];

            foreach ($node['children'] as $child) {
                if ($node['kind'] === 'array') {
                    $value[] = $this->jsonValue($child);
                } elseif ($child['kind'] !== 'truncated') {
                    $value[(string) $child['key']] = $this->jsonValue($child);
                } else {
                    $value['…'] = $child['display'];
                }
            }

            return $value;
        }

        return match ($node['kind']) {
            'null' => null,
            'boolean' => $node['display'] === 'true',
            'integer' => (int) $node['display'],
            'number' => (float) $node['display'],
            'string' => $this->decodeString($node['display']),
            default => $node['display'],
        };
    }

    private function decodeString(string $display): string
    {
        try {
            $value = json_decode($display, true, flags: JSON_THROW_ON_ERROR);

            return is_string($value) ? $value : $display;
        } catch (Throwable) {
            return $display;
        }
    }
}
