@props(['classes' => ''])

<div
    class="text-white rounded bg-[#d62a86] hover:bg-[#b81e74] border-[2px] border-[#f8558c] transition ease-in-out text-center rounded py-1 px-2 text-sm text-white cursor-pointer {{ $classes }}">
    {{ $slot }}
</div>
