<div class="pl-3" style="image-rendering: pixelated">
    @php($avatarUrl = $column->getAvatarUrl())
    @if ($avatarUrl)
        <img loading="lazy" src="{{ $avatarUrl }}" alt="{{ $column->getRecord()->name }}" />
    @endif
</div>
