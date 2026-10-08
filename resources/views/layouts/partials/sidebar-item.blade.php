@php
    /**
     * Renders one metisMenu entry and recurses into its children, so a nav
     * item can nest to any depth. $depth 0 = top level (gets the icon).
     */
    $depth       = $depth ?? 0;
    $hasChildren = ! empty($item['children']);
    $isActive    = request()->routeIs($item['active'] ?? []);
    $href        = $hasChildren || empty($item['route'])
        ? 'javascript:void(0);'
        : route($item['route']);
@endphp

<li class="{{ $isActive ? 'mm-active' : '' }}">
    <a class="{{ $hasChildren ? 'has-arrow' : '' }} {{ $isActive && ! $hasChildren ? 'mm-active' : '' }}"
       href="{{ $href }}"
       aria-expanded="{{ $isActive && $hasChildren ? 'true' : 'false' }}">
        @if ($depth === 0)
            <div class="menu-icon">
                <i class="bi {{ $item['icon'] ?? 'bi-dot' }}"></i>
            </div>
            <span class="nav-text">{{ $item['label'] }}</span>
        @else
            {{ $item['label'] }}
        @endif
    </a>

    @if ($hasChildren)
        <ul class="{{ $isActive ? 'mm-show' : '' }}" aria-expanded="{{ $isActive ? 'true' : 'false' }}">
            @foreach ($item['children'] as $child)
                @include('layouts.partials.sidebar-item', ['item' => $child, 'depth' => $depth + 1])
            @endforeach
        </ul>
    @endif
</li>
