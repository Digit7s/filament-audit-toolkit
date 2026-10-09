@php
    $children = $node['children'] ?? [];
    $hasChildren = count($children) > 0;
    $key = $node['key'] ?? null;
    $keyLabel = $key === null ? 'Root' : (is_int($key) ? '['.$key.']' : (string) $key);
    $summary = $key === null
        ? $node['type_label']
        : $keyLabel;
@endphp

@if ($hasChildren)
    <details class="fat-json-node" role="treeitem" @if ($root ?? false) open @endif>
        <summary class="fat-json-node-summary">
            <span class="fat-json-key">{{ $summary }}</span>
            <span class="fat-json-type">{{ $node['type_label'] }} · {{ count($children) }} {{ str('item')->plural(count($children)) }}</span>
        </summary>
        <div class="fat-json-children" role="group">
            @foreach ($children as $child)
                @include('filament-audit-toolkit::components.json-tree-node', ['node' => $child, 'root' => false])
            @endforeach
        </div>
    </details>
@else
    <div class="fat-json-leaf {{ ($node['truncated'] ?? false) ? 'fat-json-leaf--truncated' : '' }}" role="treeitem">
        <span class="fat-json-key">{{ $summary }}</span>
        <span class="fat-json-type">{{ $node['type_label'] }}</span>
        <span class="fat-json-value fat-json-value--{{ $node['kind'] }}">{{ $node['display'] }}</span>
    </div>
@endif
