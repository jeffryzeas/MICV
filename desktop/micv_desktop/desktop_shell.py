"""Native shell hosting only a private, already provisioned Laravel engine."""
from pathlib import Path
from urllib.parse import parse_qs, urlsplit
import json
from .api import server_unavailable
from PySide6.QtCore import QByteArray, Qt, QTimer, QUrl, Signal, QThread, QEventLoop, QEvent, QCoreApplication
from PySide6.QtGui import QAction, QDesktopServices, QIcon, QPainter, QPixmap
from PySide6.QtSvg import QSvgRenderer
from PySide6.QtPrintSupport import QPrintDialog, QPrinter
from PySide6.QtWidgets import (QMainWindow,QDockWidget,QTreeWidget,QTreeWidgetItem,QFileDialog,
    QMessageBox,QToolBar,QLineEdit,QProgressBar,QApplication,QTabWidget)
from PySide6.QtWebEngineCore import QWebEngineProfile,QWebEnginePage,QWebEngineUrlRequestInterceptor,QWebEngineSettings,QWebEngineHttpRequest
from PySide6.QtWebEngineWidgets import QWebEngineView


def same_origin(url,origin):
    try:
        left,right=urlsplit(url),urlsplit(origin)
        if left.scheme not in ('http','https'):return False
        return (left.scheme,left.hostname,left.port or {'https':443,'http':80}.get(left.scheme))==(right.scheme,right.hostname,right.port or {'https':443,'http':80}.get(right.scheme)) and not left.username and not left.password
    except ValueError:return False

class BundledDocumentEditor:
    """The editor's packaged assets and the account's existing permission check."""

    origin = ''
    assets = ('index.html','tema.css','pdf-lib.min.js','documentos-core.js','documentos-editor.js')

    def __init__(self, directory, authorize):
        self.authorize = authorize
        self.paths = {(directory / name).resolve() for name in self.assets}
        if not all(path.is_file() and path.parent == directory.resolve() for path in self.paths):
            raise ValueError('Faltan archivos del editor local.')
        self.entry_url = QUrl.fromLocalFile(str((directory / 'index.html').resolve()))

    def allows_file(self, url):
        if not url.isLocalFile() or url.host():
            return False
        try:
            return Path(url.toLocalFile()).resolve() in self.paths
        except (OSError, ValueError):
            return False

    def configuration(self):
        return self.authorize()

    def stop(self):
        pass


class LocalRequests(QWebEngineUrlRequestInterceptor):
    def __init__(self,engine,parent):
        super().__init__(parent);self.engine=engine
    def interceptRequest(self,info):
        request_url = info.requestUrl()
        if isinstance(self.engine,BundledDocumentEditor) and self.engine.allows_file(request_url):
            return
        if same_origin(request_url.toString(),self.engine.origin):
            for name,value in self.engine.request_headers().items():info.setHttpHeader(name,value)
        elif info.requestUrl().scheme() not in ('data','blob','qrc','about','chrome','chrome-extension'):
            # No silent remote assets, cross-tenant requests, tracking, or direct
            # file:// access from a local page. Explicit external links use OS browser.
            info.block(True)

class LocalPage(QWebEnginePage):
    def __init__(self,profile,engine,parent):
        super().__init__(profile,parent);self.engine=engine
    def createWindow(self,kind):
        window = self.view_parent
        view = window.new_tab(activate=False)
        def show_preview(url):
            if not url.isEmpty() and url.toString() != 'about:blank':
                view.urlChanged.disconnect(show_preview)
                window.tabs.setCurrentWidget(view)
        view.urlChanged.connect(show_preview)
        return view.page()

    def acceptNavigationRequest(self,url,kind,main):
        if same_origin(url.toString(),self.engine.origin):
            if main and url.path().rstrip('/')=='/logout':
                QTimer.singleShot(0,self.view_parent.change_account);return False
            if main and kind == QWebEnginePage.NavigationType.NavigationTypeLinkClicked and self.view_parent.is_letter_link(url):
                QTimer.singleShot(0, lambda: self.view_parent.open_letter(url))
                return False
            return True
        if url.toString()=='about:blank':return True
        editor = isinstance(self.engine, BundledDocumentEditor)
        if editor and self.engine.allows_file(url):return True
        if url.scheme()=='blob':
            return editor or same_origin(url.toString()[5:],self.engine.origin)
        if not editor and main and kind==QWebEnginePage.NavigationType.NavigationTypeLinkClicked and url.scheme() in ('https','mailto','tel'):
            if QMessageBox.question(self.view_parent,'Abrir enlace externo','Este enlace necesita otra aplicación. ¿Desea abrirlo?')==QMessageBox.StandardButton.Yes:
                QDesktopServices.openUrl(url)
        return False

class SessionRefresh(QThread):
    result=Signal(bool,str,bool)
    def __init__(self,engine,parent):super().__init__(parent);self.engine=engine
    def run(self):
        try:self.engine.refresh();self.result.emit(True,'Conectado al sistema · Cambios guardados en el servidor',False)
        except Exception as exc:self.result.emit(False,str(exc),server_unavailable(exc))

class DesktopShell(QMainWindow):
    closed=Signal()
    def __init__(self,engine,local_action=None,account_action=None,sync_action=None,offline_action=None,diagnostics=None,diagnostic_event=None,letter_editor=None,chat_drafts=None):
        super().__init__();self.engine=engine;self.account_action=account_action;self.switch_user=False;self.setWindowTitle('Mi CV · Escritorio')
        self.offline_action = offline_action
        self.diagnostic_event = diagnostic_event
        self.letter_editor = letter_editor
        self.failover_shown = False
        self.resize(1180,760);self.setMinimumSize(760,480)
        self.editor_only = isinstance(engine,BundledDocumentEditor)
        self.entry_url = engine.entry_url if self.editor_only else QUrl(engine.origin+'/inicio')
        # Off-the-record cookies/cache: logout is guaranteed when the process exits.
        self.profile=QWebEngineProfile(self)
        self.interceptor=LocalRequests(engine,self.profile);self.profile.setUrlRequestInterceptor(self.interceptor)
        self.tabs=QTabWidget(self);self.tabs.setDocumentMode(True);self.tabs.setTabsClosable(True)
        self.setCentralWidget(self.tabs);self.tabs.tabCloseRequested.connect(self.close_tab)
        self.new_tab()
        self.profile.downloadRequested.connect(self.download)
        self.dock=QDockWidget('Módulos',self);self.dock.setFeatures(QDockWidget.DockWidgetFeature.DockWidgetClosable)
        container=QTreeWidget(self.dock);container.setHeaderHidden(True);self.modules=container
        self.dock.setWidget(container);self.addDockWidget(Qt.DockWidgetArea.LeftDockWidgetArea,self.dock)
        self.modules.itemActivated.connect(self.navigate_module)
        self.modules.itemClicked.connect(self.navigate_module)
        bar=QToolBar('Acciones',self);bar.setMovable(False);self.addToolBar(bar)
        def action(label,callback,shortcut=None):
            a=QAction(label,self);a.triggered.connect(callback)
            if shortcut:a.setShortcut(shortcut)
            bar.addAction(a);return a
        action('Atrás',lambda:self.view.back(),'Alt+Left');action('Adelante',lambda:self.view.forward(),'Alt+Right')
        action('Inicio',lambda:self.view.setUrl(self.entry_url),'Alt+Home')
        action('Actualizar',lambda:self.view.reload(),'Ctrl+R');action('Imprimir',self.print_page,'Ctrl+P')
        if not self.editor_only:action('Módulos',lambda:self.dock.setVisible(not self.dock.isVisible()),'Ctrl+M')
        self.search=QLineEdit(self);self.search.setPlaceholderText('Buscar módulo…');self.search.setMaximumWidth(250)
        self.search.textChanged.connect(self.filter_modules);bar.addWidget(self.search)
        self.progress=QProgressBar(self);self.progress.setMaximumWidth(160)
        self.statusBar().addPermanentWidget(self.progress)
        self.statusBar().showMessage('Conectado al sistema · Los cambios se guardan en el servidor' if getattr(engine,'connected',False) else 'Espacio local · Sus cambios se guardan en este equipo')
        if self.editor_only:
            self.dock.hide()
            self.search.hide()
            self.statusBar().showMessage('Editor de documentos local')
        else:
            action('Cambiar cuenta',self.change_account,'Ctrl+Shift+L')
        if local_action:action('Trabajo local',local_action,'Ctrl+Shift+O')
        if sync_action:action('Sincronizar',sync_action,'F5')
        if diagnostics:action('Diagnóstico',lambda:diagnostics(self))
        if self.letter_editor:action('Cartas',lambda:self.open_letter())
        if chat_drafts:action('Borradores de chat',lambda:chat_drafts(self))
        self.timer=QTimer(self);self.timer.timeout.connect(self.check_access);self.timer.start(15000)
        self.refresh_worker=None
        self.refresh_timer=QTimer(self);self.refresh_timer.timeout.connect(self.renew_session)
        if getattr(engine,'connected',False) and hasattr(engine,'refresh'):
            self.refresh_timer.start(60000);QTimer.singleShot(0,self.renew_session)
        if getattr(engine,'connected',False):
            request=QWebEngineHttpRequest(QUrl(engine.origin+'/desktop/session'))
            request.setMethod(QWebEngineHttpRequest.Method.Post)
            request.setPostData(QByteArray())
            for name,value in engine.request_headers().items():request.setHeader(name,value)
            self.view.load(request)
        else:self.view.setUrl(self.entry_url)

    @property
    def view(self):return self.tabs.currentWidget()

    @property
    def page(self):return self.view.page()

    def new_tab(self, activate=True):
        view=QWebEngineView(self.tabs);page=LocalPage(self.profile,self.engine,view)
        page.view_parent=self;view.setPage(page)
        settings=page.settings()
        for name,value in [('PluginsEnabled',True),('PdfViewerEnabled',True),('LocalContentCanAccessFileUrls',self.editor_only),('LocalContentCanAccessRemoteUrls',False),('JavascriptCanAccessClipboard',False)]:
            settings.setAttribute(getattr(QWebEngineSettings.WebAttribute,name),value)
        index=self.tabs.addTab(view,'Nueva ventana')
        if activate:self.tabs.setCurrentIndex(index)
        view.loadFinished.connect(lambda ok,v=view:self.loaded(ok,v))
        view.titleChanged.connect(lambda title,v=view:self.tab_title(v,title))
        view.printRequested.connect(lambda v=view:self.print_page(v))
        view.loadProgress.connect(lambda value,v=view:self.progress.setValue(value) if hasattr(self,'progress') and self.view==v else None)
        view.loadStarted.connect(lambda:self.progress.show() if hasattr(self,'progress') else None)
        return view

    def tab_title(self,view,title):
        self.tabs.setTabText(self.tabs.indexOf(view),title[:45] or 'Documento')
        if self.view==view:self.setWindowTitle(title+' · Escritorio')

    def close_tab(self,index):
        if self.tabs.count()==1:return
        view=self.tabs.widget(index);view.stop();self.tabs.removeTab(index);view.deleteLater()

    def change_account(self):
        if self.account_action and not self.account_action():return
        self.switch_user=True
        if not self.close():self.switch_user=False

    def renew_session(self):
        if self.refresh_worker and self.refresh_worker.isRunning():return
        self.refresh_worker=SessionRefresh(self.engine,self)
        self.refresh_worker.result.connect(self.session_refreshed)
        self.refresh_worker.start()

    def session_refreshed(self,ok,message,unavailable):
        self.refresh_worker.wait()
        self.statusBar().showMessage(message if ok else 'Conexión no disponible: '+message+' · Use Trabajo local para operaciones sin conexión.')
        if not ok and self.diagnostic_event:
            self.diagnostic_event('server' if unavailable else 'session')
        if ok:
            self.failover_shown = False
        elif unavailable and self.offline_action and not self.failover_shown:
            self.failover_shown = self.offline_action()
        self.check_access()

    def check_access(self):
        try:
            check = getattr(self.engine, 'refresh_authorization', self.engine.configuration)
            check()
        except Exception as error:
            if self.diagnostic_event:
                self.diagnostic_event('access')
            self.timer.stop();self.view.stop();self.engine.stop()
            QMessageBox.warning(self,'Acceso al espacio de trabajo',str(error));self.close()

    def loaded(self,success,view):
        self.progress.hide()
        if not success:
            if self.diagnostic_event:
                self.diagnostic_event('page')
            self.statusBar().showMessage('No se pudo cargar la pantalla. Sus datos se conservan.');return
        # Build native navigation from Laravel's role-filtered, server-rendered
        # menu, rather than maintaining a second permission/module list.
        js="""JSON.stringify(Array.from(document.querySelectorAll('#jzMenu a[href]')).map(a=>({
            label:a.textContent.trim().replace(/\\s+/g,' '),url:a.href,svg:a.querySelector('svg')?.outerHTML||''
        })))"""
        view.page().runJavaScript(js,lambda result,v=view:self.set_modules(result,v))

    def set_modules(self,result,view):
        try:items=json.loads(result or '[]')
        except (ValueError,TypeError):return
        if not items:return
        self.modules.clear()
        seen=set()
        for data in items:
            if not isinstance(data,dict) or not same_origin(data.get('url',''),self.engine.origin):continue
            url=data['url'];label=str(data.get('label','')).strip()
            if not label or url in seen:continue
            seen.add(url);item=QTreeWidgetItem([label]);item.setData(0,Qt.ItemDataRole.UserRole,url)
            svg=str(data.get('svg','')).replace('currentColor','#24486b')
            if svg and len(svg)<50000:
                renderer=QSvgRenderer(QByteArray(svg.encode()))
                if renderer.isValid():
                    pixmap=QPixmap(22,22);pixmap.fill(Qt.GlobalColor.transparent)
                    painter=QPainter(pixmap);renderer.render(painter);painter.end();item.setIcon(0,QIcon(pixmap))
            self.modules.addTopLevelItem(item)
        self.filter_modules(self.search.text())
        if self.modules.topLevelItemCount():
            view.page().runJavaScript("""(()=>{if(!document.getElementById('native-desktop-style')){
            const s=document.createElement('style');s.id='native-desktop-style';s.textContent=`
            @media screen {
              #appSidebar,#sidebarOverlay {display:none!important}
              body:has(#appSidebar) {font-family:'Segoe UI',Arial,sans-serif;font-size:13px!important}
              #appMain {padding:16px!important}
              #appMain input,#appMain select,#appMain textarea {font-size:13px!important}
              #appMain .rounded-2xl,#appMain .rounded-3xl {border-radius:6px!important}
              .app-topbar {padding:8px 16px!important}
              .app-topbar button[onclick="toggleSidebar()"] {display:none!important}
            }
            `;document.head.append(s)}})()""")

    def is_letter_link(self, url):
        if not self.letter_editor or not same_origin(url.toString(), self.engine.origin):
            return False
        parts = url.path().strip('/').split('/')
        return parts[0] == 'cartas' and len(parts) <= 2 and parts[-1] != 'buscar-clientes'

    def open_letter(self, url=None):
        if not self.letter_editor:
            return
        kind, client_id = None, None
        if url:
            parts = url.path().strip('/').split('/')
            kind = parts[1] if len(parts) == 2 else None
            identifier = parse_qs(url.query()).get('cliente', [''])[0]
            if identifier.isdecimal() and len(identifier) <= 18:
                client_id = int(identifier)
        self.letter_editor(self, kind, client_id)

    def navigate_module(self,item,column):
        url=QUrl(item.data(0,Qt.ItemDataRole.UserRole))
        if self.is_letter_link(url):
            self.open_letter(url)
        elif self.view.url()!=url:
            self.view.setUrl(url)

    def filter_modules(self,text):
        for index in range(self.modules.topLevelItemCount()):
            item=self.modules.topLevelItem(index);item.setHidden(text.casefold() not in item.text(0).casefold())

    def download(self,request):
        name=Path(request.downloadFileName().replace('\\','/')).name or 'documento'
        path,_=QFileDialog.getSaveFileName(self,'Guardar archivo',name)
        if not path:request.cancel();return
        destination=Path(path)
        request.setDownloadDirectory(str(destination.parent));request.setDownloadFileName(destination.name)
        def download_state(state):
            if state == request.DownloadState.DownloadCompleted:
                self.statusBar().showMessage('Archivo guardado: ' + str(destination))
            elif state == request.DownloadState.DownloadInterrupted:
                self.statusBar().showMessage('Se interrumpió la descarga.')
                if self.diagnostic_event:
                    self.diagnostic_event('download')
        request.stateChanged.connect(download_state)
        request.accept()

    def print_page(self,view=None):
        if not isinstance(view,QWebEngineView):view=self.view
        if hasattr(self,'printer'):return
        printer=QPrinter(QPrinter.PrinterMode.HighResolution)
        if QPrintDialog(printer,self).exec():
            self.printer=printer
            self.print_view=view
            view.printFinished.connect(self.print_done)
            view.print(printer)
    def print_done(self,ok):
        self.print_view.printFinished.disconnect(self.print_done)
        self.print_view=None
        self.printer=None;del self.printer
        if not ok:
            if self.diagnostic_event:
                self.diagnostic_event('print')
            QMessageBox.warning(self, 'Impresión', 'No se pudo completar la impresión.')
    def closeEvent(self,event):
        if hasattr(self,'printer'):
            QMessageBox.information(self,'Impresión','Espere a que termine la impresión.');event.ignore();return
        if self.refresh_worker and self.refresh_worker.isRunning():
            QMessageBox.information(self,'Conexión','Espere a que termine la comprobación de acceso.');event.ignore();return
        self.timer.stop();self.refresh_timer.stop()
        for index in range(self.tabs.count()):
            view=self.tabs.widget(index);view.stop();view.deleteLater()
        self.engine.stop()
        # The profile is owned by the window and outlives all of its pages.
        self.closed.emit();event.accept()


def smoke_renderer():
    """Exercise the packaged Chromium process, not just Python imports."""
    view=QWebEngineView();loop=QEventLoop();result=[]
    view.loadFinished.connect(lambda ok:(result.append(ok),loop.quit()))
    timer=QTimer();timer.setSingleShot(True);timer.timeout.connect(loop.quit);timer.start(15000)
    view.setHtml('<!doctype html><title>Mi CV</title><p>Motor de escritorio preparado</p>')
    loop.exec();timer.stop();view.stop();view.close();view.deleteLater()
    QCoreApplication.sendPostedEvents(None,QEvent.Type.DeferredDelete)
    return bool(result and result[0])
