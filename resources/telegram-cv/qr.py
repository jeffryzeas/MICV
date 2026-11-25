#!/usr/bin/env python3
"""Vinculación QR persistente. JSONL privado por pipes; nunca usa un generador QR externo."""
import asyncio
import datetime
import json
import signal
import sys
from telethon import TelegramClient, errors
from telethon.sessions import StringSession
from cliente import Fallo, clasificar, diagnostico, nombre, numero

def emitir(datos):
    sys.stdout.write(json.dumps({'v':1,**datos},ensure_ascii=True)+'\n')
    sys.stdout.flush()

async def vincular(d, reader, emit=emitir, factory=TelegramClient):
    client=None;aceptado=False;cancelar=asyncio.Event();ack=asyncio.Event();espera=None
    async def comandos():
        try:
            while True:
                raw=await reader.readline()
                if not raw:
                    if not ack.is_set():cancelar.set()
                    return
                if len(raw)>1024:cancelar.set();return
                msg=json.loads(raw)
                if msg.get('accion')=='aceptar':ack.set();return
                if msg.get('accion')=='cancelar':cancelar.set();return
        except Exception:cancelar.set()
    control=asyncio.create_task(comandos())
    loop=asyncio.get_running_loop()
    for sig in (signal.SIGTERM,signal.SIGINT):
        try:loop.add_signal_handler(sig,cancelar.set)
        except (NotImplementedError,RuntimeError):pass
    async def correr(awaitable,timeout):
        tarea=asyncio.ensure_future(awaitable);stop=asyncio.create_task(cancelar.wait())
        try:
            done,_=await asyncio.wait([tarea,stop],timeout=max(.01,timeout),return_when=asyncio.FIRST_COMPLETED)
            if cancelar.is_set():raise Fallo('qr_cancelado')
            if tarea not in done:raise asyncio.TimeoutError()
            return tarea.result()
        finally:
            stop.cancel()
            if not tarea.done():tarea.cancel()
            await asyncio.gather(tarea,stop,return_exceptions=True)
    try:
        if d.get('v')!=1 or not isinstance(d.get('api_id'),int) or not 0<d['api_id']<=2147483647:raise Fallo('credenciales')
        import re
        if not re.fullmatch('[a-fA-F0-9]{32}',str(d.get('api_hash',''))):raise Fallo('credenciales')
        limite=min(float(d['expira_ms'])/1000,datetime.datetime.now(datetime.timezone.utc).timestamp()+300)
        def restante():return limite-datetime.datetime.now(datetime.timezone.utc).timestamp()
        if restante()<=0:raise Fallo('qr_caducado')
        client=factory(StringSession(),d['api_id'],d['api_hash'],device_model='Mi CV · Telegram del sistema',system_version='Linux',app_version='1.1',lang_code='es',system_lang_code='es',timeout=12,connection_retries=1,request_retries=2,raise_last_call_error=True,auto_reconnect=False,flood_sleep_threshold=0,receive_updates=True)
        await correr(client.connect(),min(40,restante()))
        qr=await correr(client.qr_login(),min(30,restante()))
        while restante()>0:
            # Registrar el listener antes de entregar el token a la interfaz.
            espera=asyncio.create_task(qr.wait(timeout=max(.01,min(restante(),(qr.expires-datetime.datetime.now(datetime.timezone.utc)).total_seconds()))))
            await asyncio.sleep(0)
            emit({'tipo':'qr','url':qr.url,'expira_ms':int(qr.expires.timestamp()*1000)})
            try:
                user=await correr(espera,restante())
            except errors.SessionPasswordNeededError:
                emit({'tipo':'clave','sesion':client.session.save()})
                await correr(ack.wait(),min(10,max(.01,restante())))
                aceptado=True
                return
            except asyncio.TimeoutError:
                if restante()<=0:break
                qr=await correr(client.qr_login(),min(30,restante()))
                continue
            me=await correr(client.get_me(),min(20,restante()))
            if not me or getattr(me,'bot',False) or getattr(me,'deleted',False):raise Fallo('cuenta')
            phone=numero('+'+str(getattr(me,'phone','')).lstrip('+'))
            emit({'tipo':'conectada','sesion':client.session.save(),'id':str(me.id),'nombre':nombre(me),'usuario':getattr(me,'username','') or '', 'telefono':phone})
            await correr(ack.wait(),min(10,max(.01,restante())))
            aceptado=True
            return
        raise Fallo('qr_caducado')
    except Exception as e:
        codigo,seg=clasificar(e)
        emit({'tipo':'error','error':codigo,'espera':seg,'diagnostico':diagnostico(e)})
    finally:
        control.cancel()
        if espera and not espera.done():espera.cancel()
        await asyncio.gather(control,*([espera] if espera else []),return_exceptions=True)
        if client:
            # Si Laravel no pudo guardar/aceptar el resultado, no dejar una sesión huérfana.
            if not aceptado:
                try:
                    if await asyncio.wait_for(client.is_user_authorized(),3):await asyncio.wait_for(client.log_out(),4)
                except Exception:pass
            try:await asyncio.wait_for(client.disconnect(),3)
            except Exception:pass

async def principal():
    reader=asyncio.StreamReader(limit=65536)
    await asyncio.get_running_loop().connect_read_pipe(lambda:asyncio.StreamReaderProtocol(reader),sys.stdin)
    raw=await reader.readline()
    if len(raw)>16384:raise ValueError()
    d=json.loads(raw)
    await vincular(d,reader)

if __name__=='__main__':
    try:asyncio.run(principal())
    except Exception:emitir({'tipo':'error','error':'servicio','diagnostico':'ADAPTADOR_INTERNO'})
