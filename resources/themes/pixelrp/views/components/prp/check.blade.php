@props(['name', 'checked' => false, 'value' => '1', 'required' => false, 'top' => false])

@php
    $isChecked = old($name) !== null ? (bool) old($name) : $checked;
@endphp

<label {{ $attributes->class(['prp-check', 'row-start' => $top]) }}>
    <input type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked($isChecked)
        @if ($required) required aria-required="true" @endif>
    <span class="prp-check-box" @if ($top) style="margin-top: 3px" @endif>
        <x-prp.icon name="check" stroke="3" />
    </span>
    <span>{{ $slot }}</span>
</label>
@error($name)
    <span class="prp-field-error">{{ $message }}</span>
@enderror
