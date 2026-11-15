@props(['label', 'for' => null, 'hint' => null, 'error' => null])

<div {{ $attributes->class('min-w-0') }}>
    <label @if($for) for="{{ $for }}" @endif class="label-campo">{{ $label }}</label>
    {{ $slot }}
    @if($hint)
        <p @if($for) id="{{ $for }}-ayuda" @endif class="mt-1.5 text-xs text-gray-500">{{ $hint }}</p>
    @endif
    @if($error && $errors->has($error))
        <p @if($for) id="{{ $for }}-error" @endif class="mt-1.5 text-xs font-medium text-red-700 dark:text-red-300">{{ $errors->first($error) }}</p>
    @endif
</div>
