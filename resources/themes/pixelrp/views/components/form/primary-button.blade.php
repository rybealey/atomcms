@props(['classes' => '', 'type' => 'submit'])

<button type="{{ $type }}"
    class="w-full rounded bg-[#d62a86]! text-white border-2 border-[#f8558c] transition! ease-in-out! duration-200! hover:bg-[#b81e74]! font-semibold px-6! py-2! {{ $classes }}">
    {{ $slot }}
</button>
