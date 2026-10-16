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
    'readonly' => false,
    'id' => null,
    'bag' => 'default',
])

@php
    $id = $id ?? $name;
    $errorBag = $errors->getBag($bag);
    $hasError = $errorBag->has($name);
    $describedBy = trim(($hasError ? "{$id}-error" : '').' '.($hint !== '' ? "{$id}-hint" : ''));
@endphp

<div class="prp-field">
    @if ($label !== '' || isset($labelEnd))
        <span class="row between">
            <label class="prp-field-label" for="{{ $id }}">{{ $label }}</label>
            {{ $labelEnd ?? '' }}
        </span>
    @endif

    <input {{ $attributes->class('prp-input') }} id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
        value="{{ $type === 'password' ? '' : ($value ?? old($name)) }}"
        @if ($placeholder !== '') placeholder="{{ $placeholder }}" @endif
        @if ($autocomplete !== null) autocomplete="{{ $autocomplete }}" @endif
        @if ($required) required aria-required="true" @endif
        @if ($readonly) readonly @endif
        @if ($autofocus) autofocus @endif
        @if ($hasError) aria-invalid="true" @endif
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif>

    @if ($hasError)
        <span class="prp-field-error" id="{{ $id }}-error">{{ $errorBag->first($name) }}</span>
    @endif

    @if ($hint !== '')
        <span class="prp-field-hint" id="{{ $id }}-hint">{{ $hint }}</span>
    @endif

    {{ $slot }}
</div>
