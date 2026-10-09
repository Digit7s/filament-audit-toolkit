@php
    $defaultMode = in_array($defaultMode ?? 'tree', ['tree', 'json'], true) ? $defaultMode : 'tree';
    $authorized = (bool) ($authorized ?? false);
    $copyable = $authorized && (bool) ($copyable ?? false);
    $truncated = (bool) ($truncated ?? false);
@endphp

<div
    class="fat-json-viewer"
    x-data="{
        mode: @js($defaultMode),
        copied: false,
        async copyJson() {
            if (! @js($copyable)) return;
            try {
                await navigator.clipboard.writeText(@js($copyable ? $json : ''));
                this.copied = true;
                setTimeout(() => this.copied = false, 1800);
            } catch (error) {
                this.copied = false;
            }
        }
    }"
>
    <div class="fat-json-toolbar">
        <div class="fat-json-mode-group" role="group" aria-label="JSON presentation">
            <button
                type="button"
                class="fat-json-mode-button"
                :class="{ 'fat-json-mode-button--active': mode === 'tree' }"
                :aria-pressed="mode === 'tree'"
                x-on:click="mode = 'tree'"
            >
                Tree View
            </button>
            <button
                type="button"
                class="fat-json-mode-button"
                :class="{ 'fat-json-mode-button--active': mode === 'json' }"
                :aria-pressed="mode === 'json'"
                x-on:click="mode = 'json'"
            >
                JSON View
            </button>
        </div>

        @if ($copyable)
            <button
                type="button"
                class="fat-json-copy-button"
                x-on:click="copyJson()"
                x-bind:aria-label="copied ? 'JSON copied' : 'Copy JSON'"
            >
                <span x-show="! copied">Copy JSON</span>
                <span x-show="copied" x-cloak>Copied</span>
            </button>
        @endif
    </div>

    @if (! $authorized)
        <div class="fat-json-redacted" role="note">[REDACTED]</div>
    @else
        @if ($truncated)
            <div class="fat-json-truncation" role="note">
                Some values are omitted by the configured viewer limits.
            </div>
        @endif

        <div x-show="mode === 'tree'" class="fat-json-tree" role="tree" aria-label="Structured JSON tree">
            @include('filament-audit-toolkit::components.json-tree-node', ['node' => $root, 'root' => true])
        </div>

        <div x-show="mode === 'json'" x-cloak class="fat-json-json" aria-label="Formatted JSON">
            <pre class="fat-json-code" tabindex="0"><code>@foreach ($tokens as $token)<span class="fat-json-token fat-json-token--{{ $token['type'] }}">{{ $token['text'] }}</span>@endforeach</code></pre>
        </div>
    @endif
</div>
