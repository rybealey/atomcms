@props([
    'name',
    'checked' => false,
    'value' => '1',
])

<label class="pixel-checkbox" for="{{ $name }}">
    <input id="{{ $name }}" name="{{ $name }}" type="checkbox" value="{{ $value }}"
        @checked(old($name, $checked)) {{ $attributes }}>
    <span class="pixel-checkbox__box" aria-hidden="true"></span>
    <span>{{ $slot }}</span>
</label>
