@props([
    'type' => 'submit',
    'variant' => 'primary',
    'size' => 'md',
    'fullWidth' => false,
])

<button type="{{ $type }}"
    {{ $attributes->class([
        'pixel-button',
        'pixel-button--secondary' => $variant === 'secondary',
        'pixel-button--ghost' => $variant === 'ghost',
        'pixel-button--sm' => $size === 'sm',
        'pixel-button--lg' => $size === 'lg',
        'pixel-button--full' => $fullWidth,
    ]) }}>
    {{ $slot }}
</button>
