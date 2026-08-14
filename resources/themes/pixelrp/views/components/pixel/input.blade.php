@props([
    'name',
    'label' => '',
    'type' => 'text',
    'value' => null,
    'placeholder' => '',
    'hint' => '',
    'autocomplete' => null,
    'autofocus' => false,
    'required' => true,
])

@php
    // Fortify throws its registration and login failures into the default error
    // bag, so that is the only bag these screens ever need to read.
    $hasError = $errors->has($name);
@endphp

<label class="pixel-field" for="{{ $name }}">
    @if ($label !== '')
        <span class="pixel-field__label">{{ $label }}</span>
    @endif

    <input {{ $attributes->class('pixel-field__input') }} id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
        value="{{ $value ?? old($name) }}" placeholder="{{ $placeholder }}"
        autocomplete="{{ $autocomplete ?? $name }}" @if ($required) required @endif
        @if ($autofocus) autofocus @endif @if ($hasError) aria-invalid="true" @endif>

    @if ($hasError)
        <span class="pixel-field__error">{{ $errors->first($name) }}</span>
    @elseif ($hint !== '')
        <span class="pixel-field__hint">{{ $hint }}</span>
    @endif
</label>
