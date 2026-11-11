from __future__ import annotations
import html, json, os, sys, time, uuid
from pathlib import Path
from datetime import date, datetime
from PySide6.QtCore import Qt, QThread, Signal, QTimer, QUrl, QLockFile
from PySide6.QtGui import QDesktopServices, QIcon, QTextDocument, QPageSize, QAction, QImageReader
from PySide6.QtPrintSupport import QPrinter
from PySide6.QtSvg import QSvgRenderer  # Incluye el soporte SVG al congelar la aplicación.
from PySide6.QtWidgets import (QApplication, QMainWindow, QWidget, QDialog, QVBoxLayout, QHBoxLayout, QFormLayout,
    QLabel, QPushButton, QLineEdit, QComboBox, QDoubleSpinBox, QTableWidget, QTableWidgetItem, QHeaderView,
    QAbstractItemView, QListWidget, QListWidgetItem, QStackedWidget, QMessageBox, QFileDialog, QDialogButtonBox, QInputDialog, QScrollArea, QGroupBox, QProgressBar, QToolBar, QSplitter, QPlainTextEdit)
from . import __version__
from .diagnostics import record_event, report as diagnostic_report
from .storage import Store
from .service import Service
from .sync import Sync, DEFAULT_SERVER_URL
from .security import AccessError
from .forms import ClientForm,PaymentForm,LedgerForm,fit_dialog
from .reports import excel_report,pdf_report

ASSETS = Path(__file__).resolve().parent.parent / 'assets'
STYLE = '''
QWidget{font-family:Segoe UI,Arial;font-size:13px;color:#203247;background:#edf0f4;}
QLabel{background:transparent;} QLabel#title{font-size:21px;font-weight:600;color:#143f70;}
QLabel#subtitle{color:#566778;} QLabel#card{padding:16px;background:white;border:1px solid #cdd5df;border-left:4px solid #245d96;border-radius:3px;font-size:17px;font-weight:600;}
QPushButton{background:#fafbfc;color:#183b60;border:1px solid #aebdcd;border-radius:3px;padding:6px 12px;min-height:22px;}
QPushButton:hover{background:#dceafb;border-color:#6389b0;} QPushButton:disabled{color:#8b98a7;background:#e7ebef;}
QPushButton#primary{background:#17518a;color:white;border-color:#164574;font-weight:600;} QPushButton#primary:hover{background:#2366a8;}
QLineEdit,QComboBox,QDoubleSpinBox{background:white;border:1px solid #aebdcd;border-radius:3px;padding:6px;min-height:22px;}
QLineEdit:focus,QComboBox:focus{border:1px solid #1a66ad;} QTableWidget{background:white;alternate-background-color:#f4f7fb;border:1px solid #c4ced9;gridline-color:#e1e6ed;}
QHeaderView::section{background:#e5ebf2;color:#203a56;padding:8px;border:1px solid #cbd4df;font-weight:600;} QTableWidget::item{padding:5px;} QTableWidget::item:selected{background:#d3e5f8;color:#102f50;}
QListWidget#navigation{background:#e4eaf1;border:none;border-right:1px solid #bdc9d7;padding:6px;}
QListWidget#navigation::item{padding:12px 8px;margin:2px 0;border-radius:3px;}
QListWidget#navigation::item:selected{background:#17518a;color:white;}
QGroupBox{font-weight:600;border:1px solid #c4ced9;border-radius:3px;margin-top:12px;padding:14px 10px 10px;}
QGroupBox::title{subcontrol-origin:margin;left:10px;padding:0 4px;}
QMenuBar,QMenu,QToolBar,QStatusBar{background:#f7f9fc;} QMenuBar::item:selected,QMenu::item:selected{background:#dceafb;color:#133f70;}
QToolBar{border-bottom:1px solid #c5cfda;spacing:6px;padding:4px;} QToolButton{padding:6px;}
QProgressBar{border:1px solid #c4ced9;border-radius:2px;background:white;max-height:6px;} QProgressBar::chunk{background:#276ba8;}
QScrollArea{border:none;} QSplitter::handle{background:#c4ced9;width:5px;}
'''

def label(text, name=None):
    widget = QLabel(str(text)); widget.setTextFormat(Qt.PlainText)
    if name: widget.setObjectName(name)
    widget.setWordWrap(True)
    return widget

def button(text, callback, *, primary=False, icon=''):
    widget = QPushButton(text)
    if primary: widget.setObjectName('primary')
    icon_name=icon+'-white' if primary else icon
    if icon and (ASSETS/'icons'/f'{icon_name}.svg').is_file(): widget.setIcon(QIcon(str(ASSETS/'icons'/f'{icon_name}.svg')))
    widget.clicked.connect(callback)
    return widget

def table(headers):
    widget = QTableWidget(0,len(headers)); widget.setHorizontalHeaderLabels(headers)
    widget.setSelectionBehavior(QAbstractItemView.SelectRows); widget.setSelectionMode(QAbstractItemView.SingleSelection)
    widget.setEditTriggers(QAbstractItemView.NoEditTriggers); widget.setAlternatingRowColors(True)
    widget.horizontalHeader().setSectionResizeMode(QHeaderView.Stretch); widget.verticalHeader().hide()
    return widget

def fill(widget, rows):
    widget.setRowCount(len(rows))
    for i,row in enumerate(rows):
        for j,value in enumerate(row):
            item=QTableWidgetItem('' if value is None else str(value)); item.setToolTip(item.text()); widget.setItem(i,j,item)
        widget.setRowHeight(i,44)

def combo(items, selected=None, blank=True):
    widget=QComboBox()
    if blank: widget.addItem('Seleccione…',None)
    for text,value in items: widget.addItem(str(text),value)
    if selected is not None:
        index=widget.findData(selected)
        if index>=0: widget.setCurrentIndex(index)
    return widget

class Worker(QThread):
    result=Signal(str,bool)
    def __init__(self, work, parent=None): super().__init__(parent); self.work=work
    def run(self):
        try: self.result.emit(str(self.work() or 'Listo.'),True)
        except Exception as exc: self.result.emit(str(exc),False)

class Activate(QDialog):
    def __init__(self,store,parent=None,resolve_store=None):
        super().__init__(parent);self.store=store;self.resolve_store=resolve_store;self.account=None;self.setWindowTitle('Mi CV Escritorio · Iniciar sesión');self.resize(540,440)
        outer=QVBoxLayout(self);body=QWidget();layout=QVBoxLayout(body);layout.setContentsMargins(16,12,16,12);layout.setSpacing(10);scroll=QScrollArea();scroll.setWidgetResizable(True);scroll.setWidget(body);outer.addWidget(scroll,1)
        self.heading=label('Inicie sesión en Mi CV','title');layout.addWidget(self.heading)
        layout.addWidget(label('Ingrese con su cuenta. La licencia se solicita únicamente a las empresas.','subtitle'))
        self.inputs={};self.account_group=QGroupBox('Usuario y contraseña');form=QFormLayout(self.account_group)
        for key,caption in [('user','Usuario o correo'),('password','Contraseña')]:
            inp=QLineEdit();inp.setMaxLength(255)
            if key=='password':inp.setEchoMode(QLineEdit.Password)
            inp.returnPressed.connect(self.activate);self.inputs[key]=inp;form.addRow(caption,inp)
        layout.addWidget(self.account_group)
        self.license_group=QGroupBox('Licencia de la empresa');form=QFormLayout(self.license_group);inp=QLineEdit();inp.setEchoMode(QLineEdit.Password);inp.setMaxLength(100);self.inputs['key']=inp;form.addRow('Clave de licencia',inp);layout.addWidget(self.license_group);self.license_group.hide()
        self.business_group=QGroupBox('Área de trabajo');form=QFormLayout(self.business_group);self.business=QComboBox();form.addRow('Negocio',self.business);layout.addWidget(self.business_group);self.business_group.hide()
        self.pin_group=QGroupBox('Acceso local sin internet');form=QFormLayout(self.pin_group)
        for key,caption in [('pin','Cree su PIN (mín. 6)'),('repeat','Confirme su PIN')]:
            inp=QLineEdit();inp.setEchoMode(QLineEdit.Password);inp.setMaxLength(255);self.inputs[key]=inp;inp.returnPressed.connect(self.activate);form.addRow(caption,inp)
        layout.addWidget(self.pin_group);self.pin_group.hide()
        self.status=label('La primera conexión requiere internet.','subtitle');outer.addWidget(self.status)
        self.progress=QProgressBar();self.progress.setRange(0,0);self.progress.hide();outer.addWidget(self.progress)
        self.go=button('Iniciar sesión',self.activate,primary=True,icon='key-round');self.go.setDefault(True);outer.addWidget(self.go)
        self.elapsed=QTimer(self);self.elapsed.timeout.connect(self.show_progress);self.inputs['user'].setFocus();fit_dialog(self,560,520)
    def show_progress(self):
        self.status.setText(f'Conectando con su sistema… {int(time.monotonic()-self.started)} s.')
    def start_work(self,work,callback):
        self.go.setEnabled(False)
        for inp in self.inputs.values():inp.setEnabled(False)
        self.business.setEnabled(False);self.started=time.monotonic();self.show_progress();self.elapsed.start(1000);self.progress.show()
        self.worker=Worker(work,self);self.worker.result.connect(callback);self.worker.start()
    def stop_work(self):
        self.worker.wait();self.elapsed.stop();self.progress.hide();self.go.setEnabled(True)
        for inp in self.inputs.values():inp.setEnabled(True)
        self.business.setEnabled(True)
    def activate(self):
        if hasattr(self,'worker') and self.worker.isRunning():return
        data={k:w.text() for k,w in self.inputs.items()}
        if not self.account:
            if not data['user'].strip() or not data['password']:
                QMessageBox.warning(self,'Datos de acceso','Ingrese su usuario y contraseña.');return
            self.credentials=(data['user'].strip(),data['password'])
            self.start_work(lambda:json.dumps(Sync(self.store).access_direct(*self.credentials)),self.access_finished);return
        if len(data['pin'])<6 or data['pin']!=data['repeat']:
            QMessageBox.warning(self,'Revisar PIN','Use al menos 6 caracteres y repita el mismo PIN.');return
        if self.account['role']=='cyber' and not data['key'].strip():
            QMessageBox.warning(self,'Licencia de empresa','Ingrese la clave de licencia de su empresa.');return
        context=self.business.currentData() if self.account['role']=='admin' else None
        if self.resolve_store:self.store=self.resolve_store(self.account,context,self.store)
        self.start_work(lambda:Sync(self.store).activate_direct(*self.credentials,data['key'] if self.account['role']=='cyber' else '',data['pin'],context),self.activation_finished)
    def access_finished(self,text,ok):
        self.stop_work()
        if not ok:self.status.setText(text);self.inputs['password'].clear();self.credentials=None;return
        self.account=json.loads(text);role=self.account['role'];self.account_group.hide();self.inputs['password'].clear();self.pin_group.show()
        self.heading.setText('Bienvenido, '+self.account['name'])
        self.license_group.setVisible(role=='cyber');self.business_group.setVisible(role=='admin')
        self.business.clear();self.business.addItem('Administración general',0)
        for business in self.account.get('businesses',[]):self.business.addItem(business['nombre'],business['id'])
        current=self.business.findData(self.store.meta('business',{}).get('id',0));self.business.setCurrentIndex(max(0,current))
        self.status.setText('Su empresa necesita una licencia para habilitar sus módulos.' if role=='cyber' else 'Su cuenta no requiere licencia. Configure su PIN local para continuar.')
        self.go.setText('Activar empresa' if role=='cyber' else 'Entrar al programa');self.inputs['pin'].setFocus()
    def activation_finished(self,text,ok):
        self.stop_work();self.status.setText('Acceso preparado.' if ok else text);self.inputs['key'].clear()
        if ok:self.credentials=None;self.accept()
    def reject(self):
        if hasattr(self,'worker') and self.worker.isRunning():return
        self.credentials=None;super().reject()

class MainWindow(QMainWindow):
    def __init__(self,store,*,start_timer=True):
        super().__init__();self.store=store;self.service=Service(store);self.cart=[];self.last_sale=None;self.worker=None;self.switch_user=False;self.preparing_local=False;self.backing_up=False
        self.setWindowTitle('Mi CV · Equipo JZ · Escritorio');self.resize(1320,850);self.setMinimumSize(760,440)
        root=QWidget();self.setCentralWidget(root);outer=QHBoxLayout(root);outer.setContentsMargins(0,0,0,0)
        self.nav=QListWidget();self.nav.setObjectName('navigation');self.nav.setFixedWidth(195)
        for text,icon in [('Panel','layout-dashboard'),('Clientes','users'),('Punto de venta','shopping-cart'),('Contabilidad','book-open'),('Sincronización','refresh-cw'),('Licencia y respaldo','shield-check'),('Ventas del equipo','banknote')]:
            item=QListWidgetItem(text)
            if (ASSETS/'icons'/f'{icon}.svg').is_file():
                nav_icon=QIcon(str(ASSETS/'icons'/f'{icon}.svg'));nav_icon.addFile(str(ASSETS/'icons'/f'{icon}-white.svg'),mode=QIcon.Selected);item.setIcon(nav_icon)
            self.nav.addItem(item)
        outer.addWidget(self.nav);area=QWidget();vertical=QVBoxLayout(area);vertical.setContentsMargins(16,12,16,12)
        header=QHBoxLayout();self.heading=label('Mi CV Escritorio','title');header.addWidget(self.heading,1);self.sync_button=button('Sincronizar ahora',self.synchronize,primary=True,icon='refresh-cw');header.addWidget(self.sync_button);vertical.addLayout(header)
        self.connection=label('','subtitle');vertical.addWidget(self.connection);self.pages=QStackedWidget();vertical.addWidget(self.pages,1);outer.addWidget(area,1)
        self.build_dashboard();self.build_clients();self.build_pos();self.build_ledger();self.build_sync();self.build_license();self.build_sales()
        self.nav.currentRowChanged.connect(self.pages.setCurrentIndex);self.nav.setCurrentRow(0);self.build_desktop_controls();self.refresh();fit_dialog(self,1280,800)
        self.timer=QTimer(self);self.timer.timeout.connect(self.synchronize)
        if start_timer:
            self.start_sync_timer()

    def start_sync_timer(self):
        self.timer.start(60000)
        QTimer.singleShot(300, self.synchronize)

    def build_desktop_controls(self):
        self.statusBar().showMessage('Datos guardados en este equipo')
        self.status_info=label('');self.status_info.setWordWrap(False);self.statusBar().addPermanentWidget(self.status_info)
        toolbar=QToolBar('Acceso rápido',self);toolbar.setMovable(False);toolbar.setToolButtonStyle(Qt.ToolButtonTextBesideIcon);self.addToolBar(toolbar)
        file_menu=self.menuBar().addMenu('&Archivo');module_menu=self.menuBar().addMenu('&Módulos');tools=self.menuBar().addMenu('&Herramientas');help_menu=self.menuBar().addMenu('A&yuda')
        def action(menu,text,callback,shortcut='',icon='',quick=False):
            a=QAction(text,self)
            if icon:a.setIcon(QIcon(str(ASSETS/'icons'/f'{icon}.svg')))
            if shortcut:a.setShortcut(shortcut)
            a.triggered.connect(callback);menu.addAction(a)
            if quick:toolbar.addAction(a)
            return a
        action(file_menu,'Copia de &seguridad…',self.backup,'Ctrl+Shift+B','shield-check')
        action(file_menu,'Cambiar de cuenta',self.change_account,'Ctrl+Shift+L')
        action(file_menu,'&Salir',self.close,'Alt+F4')
        for i,(name,shortcut,icon) in enumerate([('Inicio','Ctrl+1','layout-dashboard'),('Clientes','Ctrl+2','users'),('Punto de venta','Ctrl+3','shopping-cart'),('Contabilidad','Ctrl+4','book-open'),('Sincronización','Ctrl+5','refresh-cw'),('Cuenta y respaldo','Ctrl+6','shield-check'),('Ventas del equipo','Ctrl+7','banknote')]):
            entry=action(module_menu,name,lambda checked=False,index=i:self.nav.setCurrentRow(index),shortcut,icon,quick=i in (1,2,3))
            if i in (2,3,6):entry.setVisible(self.service.allowed({2:'pos.vender',3:'contabilidad.ver',6:'pos.ventas'}[i]))
        toolbar.addSeparator();action(tools,'Sincronizar ahora',self.synchronize,'F5','refresh-cw',True)
        action(tools,'Todos los módulos conectados',self.open_complete,icon='globe',quick=True);action(tools,'Sistema web',self.open_web,icon='globe');action(tools,'Editor de documentos',self.open_editor,icon='scan-line')
        action(tools,'Cartas',lambda:self.show_letter_editor(self),icon='file-down')
        action(tools,'Borradores de chat',self.show_chat_drafts,icon='save')
        action(tools,'Preparar datos locales',self.prepare_local,icon='refresh-cw')
        action(tools,'Abrir espacio local',self.open_local,icon='monitor',quick=True)
        action(help_menu,'Diagnóstico y errores',self.show_diagnostics,icon='shield-check')
        action(help_menu,'Acerca de Mi CV',lambda:QMessageBox.information(self,'Mi CV Escritorio',f'Mi CV Escritorio {__version__}\nEquipo JZ · jz.com.ni\nTrabajo local y sincronización con Laravel.'))
        self.nav.currentRowChanged.connect(lambda i:self.statusBar().showMessage(self.nav.item(i).text()))

    def page(self):
        widget=QWidget();layout=QVBoxLayout(widget);layout.setContentsMargins(0,16,0,0);self.pages.addWidget(widget);return layout
    def run_action(self,callback):
        try:callback();self.refresh()
        except Exception as exc:
            record_event(self.store, 'action')
            QMessageBox.warning(self, 'Revise la operación', str(exc))

    def show_chat_drafts(self, parent=None):
        from .chat_drafts import ChatDrafts, ChatDraftsDialog
        owner = parent if isinstance(parent, QWidget) else self
        try:
            ChatDraftsDialog(ChatDrafts(self.store), owner).exec()
        except Exception as error:
            QMessageBox.warning(owner, 'Borradores de chat', str(error))

    def show_letter_editor(self, parent, kind=None, client_id=None):
        from .letter_editor import LetterEditor
        from .workspace_access import authorize_workspace
        try:
            claims = authorize_workspace(self.store)
            if claims.get('role') not in ('admin', 'cliente') and 'cartas' not in claims.get('permissions', []):
                raise AccessError('Su cuenta no tiene permiso para usar Cartas.')
            shell = getattr(self, 'local_shell', None)
            engine = getattr(parent, 'engine', None)
            if engine is None and shell is not None and shell.isVisible():
                engine = shell.engine
            editor = LetterEditor(engine, self.store, parent, kind=kind, client_id=client_id)
            try:
                editor.exec()
            finally:
                editor.close_session()
        except Exception as error:
            record_event(self.store, 'action')
            QMessageBox.warning(parent, 'Editor de cartas', str(error))

    def show_diagnostics(self, parent=None):
        dialog = QDialog(parent if isinstance(parent, QWidget) else self)
        dialog.setWindowTitle('Diagnóstico y errores')
        dialog.resize(720, 510)
        layout = QVBoxLayout(dialog)
        summary = QPlainTextEdit(dialog)
        summary.setReadOnly(True)
        layout.addWidget(summary, 1)
        actions = QDialogButtonBox(QDialogButtonBox.StandardButton.Close, dialog)
        refresh = actions.addButton('Actualizar', QDialogButtonBox.ButtonRole.ActionRole)
        copy = actions.addButton('Copiar resumen', QDialogButtonBox.ButtonRole.ActionRole)
        actions.rejected.connect(dialog.reject)
        layout.addWidget(actions)

        def update():
            try:
                summary.setPlainText(diagnostic_report(self.store))
            except Exception:
                summary.setPlainText('No se pudo leer el diagnóstico de esta cuenta.')

        refresh.clicked.connect(update)
        copy.clicked.connect(lambda: QApplication.clipboard().setText(summary.toPlainText()))
        update()
        dialog.exec()

    def build_dashboard(self):
        layout=self.page();layout.addWidget(label('Su cuenta, con o sin conexión','title'));self.cards=QHBoxLayout();self.card_labels=[]
        for _ in range(3):w=label('','card');self.card_labels.append(w);self.cards.addWidget(w)
        layout.addLayout(self.cards);layout.addWidget(label('Las operaciones locales se envían cuando vuelve la conexión. Los conflictos se conservan para revisión y no se sobrescriben automáticamente.'))
        layout.addWidget(button('Abrir sistema web completo',self.open_web,icon='globe'))
        self.editor_button=button('Editor de documentos sin conexión',self.open_editor,icon='scan-line');layout.addWidget(self.editor_button)
        layout.addWidget(label('Prepare los datos del negocio para abrir el espacio local con sus módulos disponibles.','subtitle'));layout.addStretch()
    def build_clients(self):
        layout=self.page();bar=QHBoxLayout();self.client_search=QLineEdit();self.client_search.setPlaceholderText('Buscar por nombre, documento, correo o teléfono');self.client_search.textChanged.connect(self.render_clients);bar.addWidget(self.client_search,1)
        self.new_client=button('Registrar cliente',lambda:self.edit_client(False),primary=True,icon='user-plus');bar.addWidget(self.new_client)
        self.edit_client_button=button('Editar seleccionado',lambda:self.edit_client(True),icon='user-round-pen');bar.addWidget(self.edit_client_button);layout.addLayout(bar)
        self.clients_table=table(['Nombre','Documento','Nacionalidad','Residencia','Contacto','Estado']);layout.addWidget(self.clients_table)
        exports=QHBoxLayout();exports.addWidget(button('Exportar Excel',lambda:self.export_table(self.clients_table,'Clientes','xlsx'),icon='file-spreadsheet'));exports.addWidget(button('Exportar PDF',lambda:self.export_table(self.clients_table,'Clientes','pdf'),icon='file-down'));exports.addStretch();layout.addLayout(exports)
    def render_clients(self):
        if not hasattr(self,'clients_table'):return
        query=self.client_search.text().casefold();rows=self.service.effective('clients') if self.service.allowed('clientes.ver') else []
        self.client_rows=[r for r in rows if query in ' '.join(str(v or '') for v in r['data'].values()).casefold()]
        fill(self.clients_table,[(f"{r['data'].get('primer_nombre','')} {r['data'].get('primer_apellido','')}",r['data'].get('numero_documento'),r['data'].get('nacionalidad'),r['data'].get('pais_residencia'),r['data'].get('correo') or r['data'].get('telefono'), 'Pendiente' if r.get('local') else 'Sincronizado') for r in self.client_rows])
    def edit_client(self,editing):
        def work():
            self.service.guard('clientes.editar');row=None
            if editing:
                i=self.clients_table.currentRow()
                if i<0:raise AccessError('Seleccione un cliente.')
                row=self.client_rows[i]
            dialog=ClientForm(self.store,row,self,on_save=lambda data:self.service.client(data,row))
            if dialog.exec()==QDialog.Accepted:self.statusBar().showMessage('Cliente guardado en este equipo. Pendiente de sincronización.')
        self.run_action(work)
    def build_pos(self):
        layout=self.page();bar=QHBoxLayout();bar.addWidget(label('Punto de venta','title'));self.cash_label=label('Caja');bar.addWidget(self.cash_label,1)
        self.open_button=button('Abrir caja',lambda:self.cash_action(False),icon='wallet');self.close_button=button('Cerrar caja',lambda:self.cash_action(True),icon='lock-keyhole');bar.addWidget(self.open_button);bar.addWidget(self.close_button);layout.addLayout(bar)
        splitter=QSplitter(Qt.Horizontal);layout.addWidget(splitter,1)
        products=QWidget();left=QVBoxLayout(products);left.setContentsMargins(0,6,8,0);left.addWidget(label('Productos y servicios','subtitle'))
        self.product_search=QLineEdit();self.product_search.setPlaceholderText('Buscar nombre o código');self.product_search.textChanged.connect(self.render_products);self.product_search.returnPressed.connect(self.add_cart);left.addWidget(self.product_search)
        self.products_table=table(['Código','Producto','Precio','Existencia']);self.products_table.cellDoubleClicked.connect(lambda *_:self.add_cart());left.addWidget(self.products_table,1)
        add=QHBoxLayout();self.quantity=QDoubleSpinBox();self.quantity.setRange(.01,999999);self.quantity.setDecimals(2);self.quantity.setValue(1);add.addWidget(label('Cantidad'));add.addWidget(self.quantity);add.addWidget(button('Añadir',self.add_cart,icon='plus'));left.addLayout(add);exports=QHBoxLayout();exports.addWidget(button('PDF',lambda:self.export_table(self.products_table,'Productos','pdf'),icon='file-down'));exports.addWidget(button('Excel',lambda:self.export_table(self.products_table,'Productos','xlsx'),icon='file-spreadsheet'));left.addLayout(exports);splitter.addWidget(products)
        sale=QWidget();right=QVBoxLayout(sale);right.setContentsMargins(8,6,0,0);right.addWidget(label('Venta actual','subtitle'));self.sale_client=QComboBox();right.addWidget(self.sale_client)
        self.cart_table=table(['Producto','Cantidad','Precio','Total']);right.addWidget(self.cart_table,1);right.addWidget(button('Quitar producto seleccionado',self.remove_cart,icon='trash-2'))
        self.total_label=label('Total: 0','title');self.total_label.setAlignment(Qt.AlignRight);right.addWidget(self.total_label)
        self.pay_button=button('Cobrar venta · F8',self.pay,primary=True,icon='banknote');right.addWidget(self.pay_button)
        right.addWidget(button('Guardar último comprobante en PDF',self.receipt,icon='file-down'));splitter.addWidget(sale);splitter.setSizes([550,500])
        charge=QAction(self);charge.setShortcut('F8');charge.triggered.connect(lambda:self.pay() if self.pages.currentIndex()==2 and self.pay_button.isEnabled() else None);self.addAction(charge)
    def render_products(self):
        if not hasattr(self,'products_table'):return
        q=self.product_search.text().casefold();rows=self.service.effective('products') if self.service.allowed('pos.vender') else []
        self.product_rows=[r for r in rows if q in (str(r['data'].get('codigo') or '')+' '+r['data']['nombre']).casefold()]
        fill(self.products_table,[(r['data'].get('codigo'),r['data']['nombre'],r['data']['precio'],r['data']['stock'] if r['data'].get('controla_stock') else 'Servicio') for r in self.product_rows])
    def add_cart(self):
        def work():
            self.service.guard('pos.vender');i=self.products_table.currentRow()
            if i<0:raise AccessError('Seleccione un producto.')
            row=self.product_rows[i];cart=self.cart+[{'producto_id':row['id'],'cantidad':str(self.quantity.value())}]
            self.service.quote(cart);self.cart=cart
        self.run_action(work)
    def remove_cart(self):
        i=self.cart_table.currentRow()
        if i>=0:
            # La tabla muestra líneas combinadas por producto.
            key=self.cart_table.item(i,0).data(Qt.UserRole);self.cart=[r for r in self.cart if int(r['producto_id'])!=key];self.refresh()
    def cash_action(self,closing):
        amount,ok=QInputDialog.getDouble(self,'Cerrar caja' if closing else 'Abrir caja','Efectivo contado' if closing else 'Monto inicial',0,0,999999999,2)
        if ok:self.run_action(lambda:self.service.close_cash(str(amount)) if closing else self.service.open_cash(str(amount)))
    def pay(self):
        def work():
            self.service.guard('pos.vender');_,total=self.service.quote(self.cart)
            def save(payments):self.last_sale=self.service.sale(self.cart,payments,self.sale_client.currentData())
            dialog=PaymentForm(self.store,total,self,on_save=save)
            if dialog.exec()==QDialog.Accepted:self.cart=[];self.statusBar().showMessage('Venta guardada. Puede continuar con la siguiente venta.')
        self.run_action(work)
    def build_ledger(self):
        layout=self.page();bar=QHBoxLayout();bar.addWidget(label('Ingresos y gastos','title'),1);self.ledger_button=button('Registrar movimiento',self.ledger_new,primary=True,icon='plus');bar.addWidget(self.ledger_button);layout.addLayout(bar)
        self.balance=label('');layout.addWidget(self.balance);self.ledger_table=table(['Fecha','Tipo','Categoría','Concepto','Monto','Moneda','Estado']);layout.addWidget(self.ledger_table)
        bar=QHBoxLayout();bar.addWidget(button('Exportar Excel',lambda:self.export_table(self.ledger_table,'Contabilidad','xlsx'),icon='file-spreadsheet'));bar.addWidget(button('Exportar PDF',lambda:self.export_table(self.ledger_table,'Contabilidad','pdf'),icon='file-down'));bar.addStretch();layout.addLayout(bar)
    def ledger_new(self):
        def work():
            self.service.guard('contabilidad.registrar');dialog=LedgerForm(self,on_save=self.service.ledger)
            if dialog.exec()==QDialog.Accepted:self.statusBar().showMessage('Movimiento guardado en este equipo.')
        self.run_action(work)
    def build_sync(self):
        layout=self.page();layout.addWidget(label('Estado de cada operación','title'));layout.addWidget(label('Pendiente: guardada en este equipo. Sincronizada: confirmada por Laravel. Revisar: el servidor detectó un conflicto; la operación permanece guardada.'))
        self.operations_table=table(['Fecha local','Tipo','Referencia','Estado','Detalle']);layout.addWidget(self.operations_table)
        layout.addWidget(button('Reintentar operación seleccionada',self.retry,icon='refresh-cw'));exports=QHBoxLayout();exports.addWidget(button('Exportar PDF',lambda:self.export_table(self.operations_table,'Sincronización','pdf'),icon='file-down'));exports.addWidget(button('Exportar Excel',lambda:self.export_table(self.operations_table,'Sincronización','xlsx'),icon='file-spreadsheet'));layout.addLayout(exports)
        layout.addWidget(label('Si un cliente cambió en la web, no se sobrescribe automáticamente. Revise ambas versiones con el encargado. Las operaciones financieras originales no se editan silenciosamente.','subtitle'))
    def retry(self):
        i=self.operations_table.currentRow()
        if i>=0:self.store.retry(self.operation_rows[i]['uuid']);self.refresh();self.synchronize()
    def build_license(self):
        layout=self.page();layout.addWidget(label('Cuenta y datos locales','title'));self.license_text=label('');layout.addWidget(self.license_text)
        layout.addWidget(button('Reactivar / renovar acceso con mi cuenta',self.reactivate,icon='key-round'));layout.addWidget(button('Crear copia de seguridad cifrada',self.backup,icon='shield-check'));layout.addWidget(button('Abrir sistema web',self.open_web,icon='globe'));layout.addWidget(button('Configurar logo de reportes',self.report_logo,icon='folder-open'))
        layout.addWidget(label('La copia de seguridad contiene la base local cifrada y su clave protegida. En Windows debe restaurarse con el mismo perfil de usuario. No desinstale ni borre los datos mientras haya operaciones pendientes.','subtitle'));layout.addStretch()
    def build_sales(self):
        layout=self.page();layout.addWidget(label('Ventas de este equipo','title'));layout.addWidget(label('Historial local conservado entre sesiones. El estado indica su envío al servidor.','subtitle'))
        self.sales_table=table(['Fecha','Referencia','Total','Moneda','Estado']);layout.addWidget(self.sales_table,1)
        exports=QHBoxLayout();exports.addWidget(button('Exportar PDF',lambda:self.export_table(self.sales_table,'Ventas','pdf'),icon='file-down'));exports.addWidget(button('Exportar Excel',lambda:self.export_table(self.sales_table,'Ventas','xlsx'),icon='file-spreadsheet'));exports.addWidget(button('Comprobante seleccionado',self.selected_receipt,icon='file-down'));layout.addLayout(exports)
    def selected_receipt(self):
        index=self.sales_table.currentRow()
        if index<0:QMessageBox.information(self,'Ventas','Seleccione una venta del historial.');return
        if not self.service.allowed('pos.ventas'):return
        self.receipt(self.sales_rows[index])
    def report_logo(self):
        file,_=QFileDialog.getOpenFileName(self,'Logo para los reportes de esta cuenta','','Imágenes (*.png *.jpg *.jpeg)')
        if not file:return
        def save():
            if Path(file).stat().st_size>10*1024*1024:raise AccessError('Seleccione una imagen menor de 10 MB.')
            reader=QImageReader(file);size=reader.size()
            if not size.isValid() or size.width()*size.height()>20000000:raise AccessError('La imagen no es válida o es demasiado grande.')
            image=reader.read()
            if image.isNull():raise AccessError('No se pudo abrir la imagen.')
            image=image.scaled(512,512,Qt.KeepAspectRatio,Qt.SmoothTransformation)
            if not image.save(str(self.store.folder/'report-logo.png'),'PNG'):raise AccessError('No se pudo guardar el logo.')
            self.statusBar().showMessage('Logo guardado para los reportes de esta cuenta.')
        self.run_action(save)
    def reactivate(self):
        if self.worker and self.worker.isRunning():return
        if Activate(self.store,self).exec()==QDialog.Accepted:self.refresh();self.synchronize()
    def backup(self):
        if self.worker and self.worker.isRunning():
            return
        if getattr(self, 'local_shell', None) and self.local_shell.isVisible():
            QMessageBox.information(self, 'Copia de seguridad', 'Guarde sus cambios y cierre el espacio local para respaldar la base y sus archivos juntos.')
            return
        file, _ = QFileDialog.getSaveFileName(self, 'Copia de seguridad', f'MiCV-respaldo-{date.today()}.zip', 'ZIP (*.zip)')
        if not file or (self.worker and self.worker.isRunning()):
            return
        if getattr(self, 'local_shell', None) and self.local_shell.isVisible():
            QMessageBox.information(self, 'Copia de seguridad', 'Cierre el espacio local para continuar con el respaldo.')
            return
        self.backing_up = True
        self.sync_button.setEnabled(False)
        self.statusBar().showMessage('Creando respaldo…')

        def save():
            self.store.backup(Path(file))
            return 'Respaldo guardado: ' + file

        self.worker = Worker(save, self)
        self.worker.result.connect(self.backup_finished)
        self.worker.start()

    def backup_finished(self, message, ok):
        self.worker.wait()
        self.backing_up = False
        self.sync_button.setEnabled(True)
        self.statusBar().showMessage(message)
        if not ok:
            record_event(self.store, 'backup')
            QMessageBox.warning(self, 'Copia de seguridad', message)
    def open_complete(self):
        def work():
            if self.store.meta('capabilities',{}).get('desktop_session')!=1:
                raise AccessError('Sincronice primero. El servidor debe incluir la conexión de escritorio completa.')
            from .connected_engine import ConnectedEngine
            from .desktop_shell import DesktopShell
            if getattr(self,'complete_window',None) and self.complete_window.isVisible():
                self.complete_window.raise_();return
            self.complete_window=DesktopShell(ConnectedEngine(self.store),local_action=self.show_local_work,account_action=self.change_account,
                                              offline_action=self.use_local_during_outage, diagnostics=self.show_diagnostics,
                                              letter_editor=self.show_letter_editor, chat_drafts=self.show_chat_drafts,
                                              diagnostic_event=lambda code: record_event(self.store, code))
            self.complete_window.show()
        self.run_action(work)

    def runtime_bundle(self):
        return Path(__file__).resolve().parent.parent / 'runtime-bundle'

    def prepare_local(self):
        if self.worker and self.worker.isRunning():
            return
        if getattr(self, 'local_shell', None) and self.local_shell.isVisible():
            QMessageBox.information(self, 'Espacio local', 'Cierre el espacio local antes de preparar una copia actualizada.')
            return
        bundle = self.runtime_bundle()
        php = bundle / 'php' / ('php.exe' if os.name == 'nt' else 'php')
        if not php.is_file():
            QMessageBox.warning(self, 'Espacio local', 'Esta compilación necesita el paquete del motor local.')
            return
        def prepare():
            path = Sync(self.store).provision_workspace(bundle)
            self.store.set_meta('workspace_active', path.name)
            return 'Datos locales preparados.'
        self.sync_button.setEnabled(False)
        self.preparing_local = True
        self.worker = Worker(prepare, self)
        self.worker.result.connect(self.local_prepared)
        self.worker.start()

    def local_prepared(self, message, ok):
        self.worker.wait()
        self.preparing_local = False
        self.sync_button.setEnabled(True)
        self.statusBar().showMessage(message)
        if ok:
            self.open_local()
            if not self.timer.isActive():
                self.start_sync_timer()
        else:
            record_event(self.store, 'prepare')
            QMessageBox.warning(self, 'Preparación local', message)

    def show_local_work(self):
        if self.store.meta('workspace_active'):
            self.open_local()
        else:
            self.show()

    def use_local_during_outage(self):
        if not self.store.meta('workspace_active'):
            self.show()
            self.connection.setText('Servidor no disponible. Prepare el espacio local cuando vuelva la conexión.')
            return True
        return self.open_local(automatic=True)

    def open_local(self, *, automatic=False):
        if self.preparing_local or self.backing_up:
            return False
        try:
            if getattr(self, 'local_shell', None) and self.local_shell.isVisible():
                self.local_shell.raise_()
                self.local_shell.activateWindow()
                return True
            from .workspace_access import open_workspace
            from .desktop_shell import DesktopShell
            engine = open_workspace(self.store, self.runtime_bundle())
            try:
                self.local_shell = DesktopShell(engine, account_action=self.change_account, sync_action=self.synchronize,
                                                letter_editor=self.show_letter_editor, chat_drafts=self.show_chat_drafts,
                                                diagnostics=self.show_diagnostics,
                                                diagnostic_event=lambda code: record_event(self.store, code))
                self.local_shell.show()
            except Exception:
                engine.stop()
                raise
        except Exception as error:
            record_event(self.store, 'local_start')
            if automatic:
                self.show()
                self.connection.setText(str(error))
                return True
            QMessageBox.warning(self, 'Espacio local', str(error))
            return False
        return True

    def open_web(self):
        profile=self.store.meta('profile')
        if profile:QDesktopServices.openUrl(QUrl(profile['server_url']))
    def open_editor(self):
        def work():
            self.service.guard('documentos')
            if getattr(self, 'editor_window', None) and self.editor_window.isVisible():
                self.editor_window.raise_()
                self.editor_window.activateWindow()
                return
            from .desktop_shell import BundledDocumentEditor, DesktopShell
            editor = BundledDocumentEditor(ASSETS/'editor', lambda: self.service.guard('documentos'))
            self.editor_window = DesktopShell(editor, diagnostics=self.show_diagnostics,
                                              diagnostic_event=lambda code: record_event(self.store, code))
            self.editor_window.show()
        self.run_action(work)
    def synchronize(self):
        if not self.store.meta('profile') or (self.worker and self.worker.isRunning()):return
        self.sync_button.setEnabled(False);self.connection.setText('Sincronizando… Puede continuar trabajando con sus datos locales.')
        self.statusBar().showMessage('Sincronizando con el servidor…');self.worker=Worker(lambda:Sync(self.store).run(),self);self.worker.result.connect(self.sync_finished);self.worker.start()
    def sync_finished(self,text,ok):
        if not ok:
            record_event(self.store, 'sync')
        self.worker.wait();self.sync_button.setEnabled(True);self.refresh();self.connection.setText(text);self.statusBar().showMessage(text)
        if getattr(self, 'local_shell', None) and self.local_shell.isVisible():
            self.local_shell.statusBar().showMessage(text)
            self.local_shell.check_access()
    def refresh(self):
        try:claims=self.service.claims();scopes=claims['scopes'];expiry=datetime.fromtimestamp(claims['valid_until']).strftime('%d/%m/%Y %H:%M');self.heading.setText(self.store.meta('business',{}).get('nombre','Mi CV Escritorio'));self.license_text.setText(f"Usuario: {claims['nombre']}\nEquipo: {self.store.meta('device_uuid')}\nAutorización sin conexión hasta: {expiry}\nServidor: {self.store.meta('profile')['server_url']}")
        except AccessError:scopes=[];claims={};self.license_text.setText('Equipo sin activar.')
        role=claims.get('role','cyber');personal=role=='cliente'
        self.nav.item(1).setText('Mi ficha' if personal else 'Clientes')
        self.nav.item(2).setHidden('pos.vender' not in scopes);self.nav.item(3).setHidden('contabilidad.ver' not in scopes);self.nav.item(6).setHidden('pos.ventas' not in scopes)
        self.nav.item(5).setText('Cuenta y respaldo' if role!='cyber' else 'Licencia y respaldo')
        self.new_client.setVisible(not personal);self.edit_client_button.setVisible(not personal)
        self.license_text.setText(self.license_text.text()+('\nLicencia empresarial requerida.' if role=='cyber' else '\nEsta cuenta no requiere licencia.'))
        writable=scopes
        try:
            self.service.claims(valid=True)
            if self.store.meta('blocked') or time.time()+300<self.store.meta('last_clock',0):writable=[]
        except AccessError:writable=[]
        if scopes and not writable:self.license_text.setText(self.license_text.text()+'\nSolo consulta: conecte el equipo para revisar la autorización.')
        self.new_client.setEnabled('clientes.editar' in writable);self.edit_client_button.setEnabled('clientes.editar' in writable);self.pay_button.setEnabled('pos.vender' in writable)
        self.open_button.setEnabled('pos.caja' in writable);self.close_button.setEnabled('pos.caja.cerrar' in writable);self.ledger_button.setEnabled('contabilidad.registrar' in writable);self.editor_button.setEnabled('documentos' in writable)
        self.render_clients();self.render_products();self.cash_label.setText('Caja abierta' if self.service.cash() else 'Caja cerrada')
        selected=self.sale_client.currentData();self.sale_client.clear();self.sale_client.addItem('Consumidor final',None)
        if 'clientes.ver' in scopes:
            for row in self.service.effective('clients'):self.sale_client.addItem(row['data']['primer_nombre']+' '+row['data']['primer_apellido'],row['id'])
        idx=self.sale_client.findData(selected)
        if idx>=0:self.sale_client.setCurrentIndex(idx)
        try:
            if self.cart:lines,total=self.service.quote(self.cart)
            else:lines,total=[],0
            fill(self.cart_table,[(l['nombre'],l['cantidad'],l['precio'],l['total']) for l in lines]);
            for i,line in enumerate(lines):self.cart_table.item(i,0).setData(Qt.UserRole,line['producto_id'])
            self.total_label.setText(f"Total: {self.store.meta('business',{}).get('simbolo','')} {total}")
        except AccessError as exc:self.total_label.setText(str(exc))
        ledger=self.service.effective('ledger') if 'contabilidad.ver' in scopes else []
        fill(self.ledger_table,[(r['data'].get('fecha'),r['data']['tipo'],r['data'].get('categoria'),r['data']['concepto'],r['data']['monto'],r['data'].get('moneda'), 'Pendiente' if r.get('local') else r['data'].get('estado')) for r in ledger])
        from decimal import Decimal
        balance=sum((Decimal(str(r['data']['monto']))*(1 if r['data']['tipo']=='ingreso' else -1) for r in ledger if r['data'].get('estado','activo')=='activo'),Decimal(0));self.balance.setText('Saldo de movimientos descargados y manuales pendientes: '+str(balance))
        self.operation_rows=list(reversed(self.store.operations(include_accepted=True)));states={'pending':'Pendiente','accepted':'Sincronizada','conflict':'Revisar'}
        fill(self.operations_table,[(r['occurred_at'],r['type'],r['uuid'],states[r['status']],r['error'] or ('Servidor #'+str(r['remote_id']) if r['remote_id'] else '')) for r in self.operation_rows])
        pending=sum(r['status']=='pending' for r in self.operation_rows);conflicts=sum(r['status']=='conflict' for r in self.operation_rows)
        self.sales_rows=[r for r in self.operation_rows if r['type']=='sale.create'] if 'pos.ventas' in scopes else []
        fill(self.sales_table,[(r['occurred_at'],r['uuid'],r['data']['total'],r['data']['moneda'],states[r['status']]) for r in self.sales_rows])
        self.status_info.setText(f'{pending} pendientes · {conflicts} por revisar · Datos locales')
        for w,text in zip(self.card_labels,[f'{len(self.client_rows)} clientes locales',f'{pending} operaciones pendientes',f'{conflicts} por revisar']):w.setText(text)
    def export_table(self,widget,name,format):
        permissions={'Contabilidad':'contabilidad.exportar','Clientes':'clientes.ver','Productos':'pos.vender','Ventas':'pos.ventas'}
        permission=permissions.get(name)
        if permission and not self.service.allowed(permission):QMessageBox.warning(self,'Exportación','Su cuenta no tiene permiso para exportar este listado.');return
        orientation='auto'
        if format=='pdf':
            value,ok=QInputDialog.getItem(self,'Orientación del PDF','Formato de página',['Automática','Vertical','Horizontal'],0,False)
            if not ok:return
            orientation={'Automática':'auto','Vertical':'portrait','Horizontal':'landscape'}[value]
        file,_=QFileDialog.getSaveFileName(self,'Exportar '+name,name+'.'+format,f'{format.upper()} (*.{format})')
        if not file:return
        if not file.lower().endswith('.'+format):file+='.'+format
        rows=[[widget.item(i,j).text() if widget.item(i,j) else '' for j in range(widget.columnCount())] for i in range(widget.rowCount())];headers=[widget.horizontalHeaderItem(i).text() for i in range(widget.columnCount())]
        def work():
            business=self.store.meta('business',{}).get('nombre','Mi CV');logo=self.store.folder/'report-logo.png'
            if format=='xlsx':excel_report(file,name,business,headers,rows,logo=logo)
            else:pdf_report(file,name,business,headers,rows,orientation,logo=logo)
            self.statusBar().showMessage('Reporte guardado: '+file)
        self.run_action(work)
    def pdf_html(self,body,file,landscape=False):
        printer=QPrinter(QPrinter.HighResolution);printer.setOutputFormat(QPrinter.PdfFormat);printer.setOutputFileName(file);printer.setPageSize(QPageSize(QPageSize.A4))
        if landscape:
            from PySide6.QtGui import QPageLayout
            printer.setPageOrientation(QPageLayout.Landscape)
        document=QTextDocument();document.setDefaultStyleSheet('body{font-family:Arial;color:#19334b}h1{color:#0b3c74}th{background:#0b3c74;color:white}td{font-size:10pt}');document.setHtml(body);document.print_(printer)
    def receipt(self,op=None):
        if not isinstance(op,dict):op=self.last_sale
        if not op:QMessageBox.information(self,'Comprobante','Seleccione una venta en Ventas del equipo o registre una nueva.');return
        file,_=QFileDialog.getSaveFileName(self,'Guardar comprobante','Comprobante-'+op['uuid'][:8]+'.pdf','PDF (*.pdf)')
        if not file:return
        if not file.lower().endswith('.pdf'):file+='.pdf'
        def work():
            rows=[(l['nombre'],l['cantidad'],l['precio'],l['total']) for l in op['data']['items']]
            pdf_report(file,'Comprobante local',self.store.meta('business')['nombre']+' · '+str(op['occurred_at']),['Producto','Cantidad','Precio','Total'],rows,'portrait',logo=self.store.folder/'report-logo.png',note='Referencia: '+op['uuid'],summary='Total: '+op['data']['moneda']+' '+op['data']['total'])
            self.statusBar().showMessage('Comprobante guardado: '+file)
        self.run_action(work)
    def resizeEvent(self,event):
        super().resizeEvent(event)
        if hasattr(self,'nav'):self.nav.setVisible(self.width()>=1100)
    def change_account(self):
        self.switch_user=True
        if not self.close():self.switch_user=False
        return self.switch_user

    def closeEvent(self,event):
        if self.worker and self.worker.isRunning():
            QMessageBox.information(self,'Operación en curso','Espere a que termine la operación antes de cerrar.');event.ignore();return
        if getattr(self, 'editor_window', None) and not self.editor_window.close():
            event.ignore()
            return
        if getattr(self, 'local_shell', None):self.local_shell.close()
        if getattr(self,'complete_window',None):self.complete_window.close()
        event.accept()

def local_accounts(folder):
    result=[]
    candidates=[folder]+list((folder/'accounts').glob('*')) if (folder/'accounts').exists() else [folder]
    for path in candidates:
        if not path.is_dir() or not (path/'micv.sqlite').is_file():continue
        if path!=folder:
            try:uuid.UUID(path.name)
            except ValueError:continue
        try:
            store=Store(path)
            if store.meta('token'):
                claims=Service(store).claims();result.append((store,claims))
        except Exception:continue
    return result

def resolve_local_account(records,account,context,fallback):
    origin=(fallback.meta('profile') or {}).get('server_url',DEFAULT_SERVER_URL)
    for store,claims in records:
        if (store.meta('profile') or {}).get('server_url',DEFAULT_SERVER_URL)!=origin:continue
        if claims['user_id']==account['user_id'] and claims.get('role','cyber')==account['role'] and (account['role']!='admin' or claims['tenant_id']==(context or 0)) and (account['role']!='cyber' or claims['tenant_id']==account.get('business_id')):
            return store
    return fallback

def main():
    QApplication.setAttribute(Qt.ApplicationAttribute.AA_ShareOpenGLContexts)
    app=QApplication(sys.argv);app.setApplicationName('Mi CV Escritorio');app.setOrganizationName('Equipo JZ');app.setApplicationVersion(__version__);app.setStyle('Fusion');app.setStyleSheet(STYLE);app.setWindowIcon(QIcon(str(ASSETS/'micv.ico')))
    folder=Path(os.environ.get('MICV_DESKTOP_HOME') or (Path(os.environ.get('LOCALAPPDATA',str(Path.home()/'.local/share')))/'EquipoJZ'/'MiCVDesktop'));folder.mkdir(parents=True,exist_ok=True)
    lock=QLockFile(str(folder/'running.lock'))
    if not lock.tryLock(100):QMessageBox.information(None,'Mi CV','El programa ya está abierto en este perfil.');return 1
    if '--smoke-test' in sys.argv:
        window=MainWindow(Store(folder),start_timer=False);window.show();app.processEvents();window.close()
        from .desktop_shell import smoke_renderer
        return 0 if smoke_renderer() else 1
    while True:
        records=local_accounts(folder);store=None
        choices = [f"{i+1}. {c['nombre']} · {c.get('role','empresa')} · {c.get('negocio','')}"
                   for i, (_, c) in enumerate(records)]
        choices += ['Entrar con otra cuenta…' if records else 'Iniciar sesión…', 'Recuperar un respaldo…']
        selected, ok = QInputDialog.getItem(None, 'Mi CV · Cuentas de este equipo', 'Seleccione su cuenta', choices, 0, False)
        if not ok:
            return 0
        index = choices.index(selected)
        if index == len(records) + 1:
            file, _ = QFileDialog.getOpenFileName(None, 'Recuperar respaldo', '', 'ZIP (*.zip)')
            if not file:
                continue
            pin, ok = QInputDialog.getText(None, 'Recuperar respaldo', 'PIN de la cuenta respaldada', QLineEdit.Password)
            if not ok:
                continue
            from .profile_backup import restore_workspace
            try:
                store = restore_workspace(Path(file), folder/'accounts', pin,
                                          existing_devices={c['device'] for _, c in records})
            except AccessError as error:
                QMessageBox.warning(None, 'Recuperar respaldo', str(error))
                continue
        elif index < len(records):
            store = records[index][0]
            pin, ok = QInputDialog.getText(None, 'Abrir cuenta', 'PIN local de esta cuenta', QLineEdit.Password)
            if not ok:
                continue
            try:
                store.unlock(pin)
            except AccessError as error:
                QMessageBox.warning(None, 'Acceso local', str(error))
                continue
        if store is None:
            fresh=Store(folder/'accounts'/str(uuid.uuid4()))
            dialog=Activate(fresh,resolve_store=lambda a,c,f:resolve_local_account(records,a,c,f))
            if dialog.exec()!=QDialog.Accepted:
                if records:continue
                return 0
            store=dialog.store
        if store.meta('workspace_active'):
            window = MainWindow(store, start_timer=False)
            window.show()
            window.open_local(automatic=True)
            window.start_sync_timer()
        elif store.meta('capabilities',{}).get('desktop_session')==1:
            from .connected_engine import ConnectedEngine
            from .desktop_shell import DesktopShell
            try:
                local_window=MainWindow(store,start_timer=False)
                window=DesktopShell(ConnectedEngine(store),local_action=local_window.show_local_work,
                                    offline_action=local_window.use_local_during_outage, diagnostics=local_window.show_diagnostics,
                                    letter_editor=local_window.show_letter_editor, chat_drafts=local_window.show_chat_drafts,
                                    diagnostic_event=lambda code: record_event(local_window.store, code))
                window.closed.connect(local_window.close)
            except AccessError:window=MainWindow(store)
        else:window=MainWindow(store)
        window.show();code=app.exec()
        if not window.switch_user:return code
