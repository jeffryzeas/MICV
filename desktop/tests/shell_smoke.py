import sys,threading,time,json
from http.server import ThreadingHTTPServer,BaseHTTPRequestHandler
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
from PySide6.QtWidgets import QApplication
from PySide6.QtCore import QEvent,QCoreApplication
from micv_desktop.desktop_shell import DesktopShell
requests=[]
class Handler(BaseHTTPRequestHandler):
 def log_message(self,*args):pass
 def do_POST(self):
  requests.append((self.command,self.path,self.headers.get('Authorization'),self.headers.get('X-MiCV-Desktop')))
  self.send_response(303);self.send_header('Location','/inicio');self.send_header('Set-Cookie','test=1; Path=/; HttpOnly');self.end_headers()
 def do_GET(self):
  requests.append((self.command,self.path,self.headers.get('Authorization'),self.headers.get('X-MiCV-Desktop')))
  self.send_response(200);self.send_header('Content-Type','text/html; charset=utf-8');self.end_headers()
  self.wfile.write(b'<html><head><title>Clientes</title></head><body><aside id="appSidebar"><nav id="jzMenu"><a href="/inicio">Inicio</a><a href="/clientes">Clientes</a><a href="/cv">CV</a></nav></aside><main id="appMain"><aside id="doc-impresion">Impresion</aside><h1>Expedientes</h1><button onclick="window.open(\'/documento\',\'_blank\')">Imprimir CV</button></main></body></html>')
server=ThreadingHTTPServer(('127.0.0.1',0),Handler);threading.Thread(target=server.serve_forever,daemon=True).start()
class Engine:
 connected=True
 origin='http://127.0.0.1:'+str(server.server_port)
 def request_headers(self):return {b'Authorization':b'Bearer test',b'X-MiCV-Desktop':b'1'}
 def configuration(self):pass
 def stop(self):pass
app=QApplication([])
from micv_desktop.gui import STYLE
app.setStyle('Fusion');app.setStyleSheet(STYLE)
window=DesktopShell(Engine());window.show()
def until(condition):
 end=time.monotonic()+12
 while not condition():
  app.processEvents();time.sleep(.01)
  if time.monotonic()>end:raise AssertionError('Timed out: '+str(requests))
until(lambda:window.modules.topLevelItemCount()==3)
state=[]
window.page.runJavaScript("JSON.stringify([getComputedStyle(document.querySelector('#appSidebar')).display,getComputedStyle(document.querySelector('#doc-impresion')).display])",lambda value:state.append(json.loads(value)))
until(lambda:bool(state))
assert state[0][0]=='none' and state[0][1]!='none',state
assert requests[0][:2]==('POST','/desktop/session'),requests
assert all(r[2:]==('Bearer test','1') for r in requests),requests
window.page.runJavaScript("document.querySelector('button').click()")
until(lambda:window.tabs.count()==2 and any(r[1]=='/documento' for r in requests))
window.close_tab(1);assert window.tabs.count()==1

window.close();window.deleteLater();QCoreApplication.sendPostedEvents(None,QEvent.Type.DeferredDelete);app.processEvents()
server.shutdown();print('PASS: authenticated POST, redirect, menu, popup tab, close tab, cleanup')
