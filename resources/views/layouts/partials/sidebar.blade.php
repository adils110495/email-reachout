{{-- Start - Sidebar Navigation --}}
<div class="deznav">
    <div class="deznav-scroll">
        <ul class="metismenu" id="menu">

            @foreach ($navSidebar as $group)

                @if (! empty($group['title']))
                    <li class="menu-title">{{ Str::upper($group['title']) }}</li>
                @endif

                @foreach ($group['items'] as $item)
                    @include('layouts.partials.sidebar-item', ['item' => $item, 'depth' => 0])
                @endforeach

            @endforeach

        </ul>
    </div>
</div>
{{-- End - Sidebar Navigation --}}
