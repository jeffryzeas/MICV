<a href="{{ $item['onclick'] ?? null ? '#' : $item['url'] }}"
   @if(!empty($item['onclick'])) onclick="{{ $item['onclick'] }}; return false;" @endif
   data-menu-texto="{{ mb_strtolower(($grupo ?? '') . ' ' . $item['label']) }}"
   class="jz-item-menu flex items-center gap-3 px-3 {{ isset($grupo) ? 'py-2' : 'py-2.5' }} rounded-lg text-sm font-medium transition-colors
          {{ ($activo ?? '') === $item['id'] ? 'bg-oro-500/20 text-oro-300' : 'text-white/75 hover:bg-white/5 hover:text-white' }}">
    <i data-lucide="{{ $item['icon'] }}" class="size-[18px] shrink-0"></i>
    <span>{{ $item['label'] }}</span>
    @if(!empty($item['badge']))
        <span class="ml-auto bg-red-500 text-white text-[10px] font-bold rounded-full px-1.5 py-0.5">{{ $item['badge'] }}</span>
    @endif
</a>
