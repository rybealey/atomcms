@props(['corporation', 'size' => 'md'])

@php
    // The corporation's real badge from the badge album, on a square toned by
    // what the corporation does. '' falls back to the default corp badge
    // (NPH17), as the client does.
    $badge = $corporation->badge !== '' ? $corporation->badge : 'NPH17';
    $tone = match ($corporation->service_type) {
        'medical' => 'tone-medical',
        'police' => 'tone-police',
        'staff' => 'tone-staff',
        default => 'tone-neutral',
    };
@endphp

<span {{ $attributes->class(['badge', "badge-{$size}", $tone]) }} aria-hidden="true">
    <img src="{{ rtrim((string) setting('badges_path'), '/') }}/{{ $badge }}.gif" alt="" loading="lazy">
</span>
