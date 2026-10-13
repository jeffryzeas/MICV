{{-- Trampa para robots: no lo vea ni lo llene una persona. --}}
<div aria-hidden="true" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden">
    <label>No llene este campo <input type="text" name="{{ \App\Support\AntiBot::CAMPO }}" tabindex="-1" autocomplete="off" value=""></label>
</div>
<input type="hidden" name="_t" value="{{ \App\Support\AntiBot::marca() }}">
