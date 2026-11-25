<aside class="au-nav gestion-surface" aria-label="Menú de TikTok">
 <p class="au-nav-label">CENTRO DE AUDITORÍA</p>
 <a href="{{ route('admin.tiktok.index') }}"><x-icono name="users" class="size-4"/>Cuentas monitoreadas</a>
 @isset($cuenta)
 <p class="au-nav-account">{{ '@'.$cuenta->usuario }}</p>
 <a href="{{ route('admin.tiktok.cuenta',$cuenta) }}"><x-icono name="radio" class="size-4"/>Captura en vivo</a>
 @foreach(['mensajes'=>['messages-square','Mensajes'],'participantes'=>['users','Participantes'],'salas'=>['history','Salas y fechas']] as $key=>$item)
 <a href="{{ route('admin.tiktok.auditoria',[$cuenta]+array_merge($parametros??[],['vista'=>$key])) }}" class="{{ ($vista??'')===$key||(($vista??'')==='registro'&&$key==='mensajes')?'is-active':'' }}" @if(($vista??'')===$key) aria-current="page" @endif><x-icono :name="$item[0]" class="size-4"/>{{ $item[1] }}</a>
 @endforeach
 <a href="{{ route('admin.tiktok.cuenta',$cuenta) }}#tt-config-fuentes"><x-icono name="list-filter" class="size-4"/>Fuentes de chat</a>
 <a href="{{ route('admin.tiktok.cuenta',$cuenta) }}#tt-informes"><x-icono name="file-text" class="size-4"/>Informes temporales</a>
 @endisset
 <p class="au-nav-label">NOTIFICACIONES</p>
 <a href="{{ route('admin.tiktok.telegram.index') }}" class="{{ ($vista??'')==='telegram'?'is-active':'' }}"><x-icono name="send" class="size-4"/>Bot de Telegram</a>
 <p class="au-nav-foot"><x-icono name="shield-check" class="size-4"/>Consulta privada para administradores.</p>
</aside>
