@php
    $style = in_array($style, ['unified', 'split', 'fields'], true) ? $style : 'unified';
    $entries = $diff->entries();
    $typeClass = static function (string $type): string {
        return match ($type) {
            'added', 'removed', 'modified', 'truncated' => $type,
            default => 'modified',
        };
    };
    $typeLabel = static function (string $type): string {
        return match ($type) {
            'added' => 'Added',
            'removed' => 'Removed',
            'truncated' => 'Omitted',
            default => 'Modified',
        };
    };
@endphp

<div class="fat-diff-viewer fat-diff-viewer--{{ $style }}" aria-label="Structured audit changes">
    @if (! $authorized)
        <div class="fat-diff-redacted" role="note">
            [REDACTED]
        </div>
    @elseif ($diff->isEmpty())
        <div class="fat-diff-empty" role="status">
            No changed values.
        </div>
    @else
        <div class="fat-diff-toolbar">
            <span class="fat-diff-count">{{ $diff->changeCount() }} {{ str('change')->plural($diff->changeCount()) }}</span>
            @if ($diff->isTruncated())
                <span class="fat-diff-truncation-notice" role="note">Some changes are omitted by the configured display limits.</span>
            @endif
        </div>

        @if ($style === 'unified')
            <div class="fat-diff-list fat-diff-list--unified" role="list" aria-label="Unified diff">
                @foreach ($entries as $entry)
                    @php($entryType = $typeClass($entry->type))
                    <article class="fat-diff-entry fat-diff-entry--{{ $entryType }}" role="listitem">
                        <header class="fat-diff-entry__header">
                            <div class="fat-diff-entry__heading">
                                <span class="fat-diff-field-label">{{ $entry->label }}</span>
                                <code class="fat-diff-field-path">{{ $entry->path }}</code>
                            </div>
                            <span class="fat-diff-badge fat-diff-badge--{{ $entryType }}">
                                <span class="sr-only">Change type: </span>{{ $typeLabel($entry->type) }}
                            </span>
                        </header>

                        @if ($entry->type === 'truncated')
                            <div class="fat-diff-value-row fat-diff-value-row--neutral" role="note">
                                <span class="fat-diff-marker" aria-hidden="true">…</span>
                                <span class="fat-diff-value">{{ $entry->afterDisplay }}</span>
                            </div>
                        @else
                            @if ($entry->beforeExists)
                                <div class="fat-diff-value-row fat-diff-value-row--removed" aria-label="Removed value for {{ $entry->path }}">
                                    <span class="fat-diff-marker" aria-hidden="true">−</span>
                                    <span class="sr-only">Removed: </span>
                                    <span class="fat-diff-value">{{ $entry->beforeDisplay }}</span>
                                </div>
                            @endif
                            @if ($entry->afterExists)
                                <div class="fat-diff-value-row fat-diff-value-row--added" aria-label="Added value for {{ $entry->path }}">
                                    <span class="fat-diff-marker" aria-hidden="true">+</span>
                                    <span class="sr-only">Added: </span>
                                    <span class="fat-diff-value">{{ $entry->afterDisplay }}</span>
                                </div>
                            @endif
                        @endif
                    </article>
                @endforeach
            </div>
        @elseif ($style === 'split')
            <div class="fat-diff-list" role="list" aria-label="Split diff">
                @foreach ($entries as $entry)
                    @php($entryType = $typeClass($entry->type))
                    <article class="fat-diff-entry fat-diff-entry--{{ $entryType }}" role="listitem">
                        <header class="fat-diff-entry__header">
                            <div class="fat-diff-entry__heading">
                                <span class="fat-diff-field-label">{{ $entry->label }}</span>
                                <code class="fat-diff-field-path">{{ $entry->path }}</code>
                            </div>
                            <span class="fat-diff-badge fat-diff-badge--{{ $entryType }}">
                                <span class="sr-only">Change type: </span>{{ $typeLabel($entry->type) }}
                            </span>
                        </header>
                        <div class="fat-diff-split-grid">
                            <div class="fat-diff-split-cell">
                                <div class="fat-diff-value-caption">Before</div>
                                <div class="fat-diff-side-value {{ $entry->beforeExists ? '' : 'fat-diff-side-value--missing' }}">{{ $entry->beforeDisplay }}</div>
                            </div>
                            <div class="fat-diff-split-cell">
                                <div class="fat-diff-value-caption">After</div>
                                <div class="fat-diff-side-value {{ $entry->afterExists ? '' : 'fat-diff-side-value--missing' }}">{{ $entry->afterDisplay }}</div>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="fat-diff-fields-grid" role="list" aria-label="Field changes">
                @foreach ($entries as $entry)
                    @php($entryType = $typeClass($entry->type))
                    <article class="fat-diff-field-card fat-diff-field-card--{{ $entryType }}" role="listitem">
                        <header class="fat-diff-entry__header">
                            <div class="fat-diff-entry__heading">
                                <span class="fat-diff-field-label">{{ $entry->label }}</span>
                                <code class="fat-diff-field-path">{{ $entry->path }}</code>
                            </div>
                            <span class="fat-diff-badge fat-diff-badge--{{ $entryType }}">
                                <span class="sr-only">Change type: </span>{{ $typeLabel($entry->type) }}
                            </span>
                        </header>
                        <div class="fat-diff-field-card__body">
                            @if ($entry->type === 'truncated')
                                <div class="fat-diff-truncation-notice" role="note">{{ $entry->afterDisplay }}</div>
                            @else
                                <div class="fat-diff-fields-values">
                                    <div class="fat-diff-field-value">
                                        <div class="fat-diff-value-caption">Before</div>
                                        <div class="fat-diff-side-value {{ $entry->beforeExists ? '' : 'fat-diff-side-value--missing' }}">{{ $entry->beforeDisplay }}</div>
                                    </div>
                                    <div class="fat-diff-field-value fat-diff-field-value--after">
                                        <div class="fat-diff-value-caption">After</div>
                                        <div class="fat-diff-side-value {{ $entry->afterExists ? '' : 'fat-diff-side-value--missing' }}">{{ $entry->afterDisplay }}</div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    @endif
</div>
