@php
    $items = [
        ['route' => 'settings.account.show', 'icon' => 'user', 'label' => __('Account')],
        ['route' => 'settings.password.show', 'icon' => 'lock', 'label' => __('Password')],
        ['route' => 'settings.two-factor', 'icon' => 'shield-check', 'label' => __('Two-factor')],
        ['route' => 'settings.session-logs', 'icon' => 'monitor', 'label' => __('Sessions')],
    ];
@endphp

<div class="snav-wrap">
    <nav class="snav" aria-label="{{ __('Settings') }}">
        <ul>
            @foreach ($items as $item)
                <li>
                    <a href="{{ route($item['route']) }}" @if (request()->routeIs($item['route'])) aria-current="page" @endif>
                        <x-prp.icon :name="$item['icon']" class="ico" />{{ $item['label'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
</div>
