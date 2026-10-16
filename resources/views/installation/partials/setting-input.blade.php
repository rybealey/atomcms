{{--
    One website setting as a text field. InstallationFlowTest renders this
    partial on its own and expects exactly one <input> per key, with the
    `required` attribute only when the setting is required during setup.
--}}
<div class="prp-field">
    <label class="prp-field-label" for="{{ $setting->key }}">
        {{ Str::replace('_', ' ', Str::ucfirst($setting->key)) }}
    </label>

    <input class="prp-input"
        id="{{ $setting->key }}"
        type="text"
        name="{{ $setting->key }}"
        value="{{ old($setting->key, $setting->value) }}"
        placeholder="{{ $setting->key }}"
        @if (filled($setting->comment)) aria-describedby="{{ $setting->key }}-hint" @endif
        @error($setting->key) aria-invalid="true" @enderror
        @required($setting->isRequiredDuringInstallation())>

    @error($setting->key)
        <span class="prp-field-error">{{ $message }}</span>
    @enderror

    @if (filled($setting->comment))
        <span class="prp-field-hint" id="{{ $setting->key }}-hint">{{ $setting->comment }}</span>
    @endif
</div>
