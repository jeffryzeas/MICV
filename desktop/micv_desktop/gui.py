from __future__ import annotations
import html, json, os, sys, time, uuid
from pathlib import Path
from datetime import date, datetime
from PySide6.QtCore import Qt, QThread, Signal, QTimer, QUrl, QLockFile
from PySide6.QtGui import QDesktopServices, QIcon, QTextDocument, QPageSize, QAction
from PySide6.QtPrintSupport import QPrinter
from PySide6.QtSvg import QSvgRenderer  # Incluye el soporte SVG al congelar la aplicación.
from PySide6.QtWidgets import (QApplication, QMainWindow, QWidget, QDialog, QVBoxLayout, QHBoxLayout, QFormLayout,
    QLabel, QPushButton, QLineEdit, QComboBox, QDoubleSpinBox, QTableWidget, QTableWidgetItem, QHeaderView,
    QAbstractItemView, QListWidget, QListWidgetItem, QStackedWidget, QMessageBox, QFileDialog, QDialogButtonBox, QInputDialog, QScrollArea, QGroupBox, QProgressBar, QToolBar, QSplitter)
from .storage import Store
from .service import Service
from .sync import Sync, DEFAULT_SERVER_URL
from .security import AccessError

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
        layout=QVBoxLayout(self);layout.setContentsMargins(24,20,24,20);layout.setSpacing(12)
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
        self.status=label('La primera conexión requiere internet.','subtitle');layout.addWidget(self.status)
        self.progress=QProgressBar();self.progress.setRange(0,0);self.progress.hide();layout.addWidget(self.progress)
        self.go=button('Iniciar sesión',self.activate,primary=True,icon='key-round');self.go.setDefault(True);layout.addWidget(self.go)
        self.elapsed=QTimer(self);self.elapsed.timeout.connect(self.show_progress);self.inputs['user'].setFocus()
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

class ClientForm(QDialog):
    def __init__(self,store,row=None,parent=None):
        super().__init__(parent);self.setWindowTitle('Ficha del cliente');self.resize(620,740);self.fields={};data=(row or {}).get('data',{});catalog=store.meta('catalogs',{})
        layout=QVBoxLayout(self);scroll=QScrollArea();scroll.setWidgetResizable(True);content=QWidget();form=QFormLayout(content)
        countries=catalog.get('countries',{});country_list=[(p['nombre'],k) for k,p in countries.items()]
        self.fields['pais']=combo(country_list,data.get('pais'));self.fields['tipo_documento']=QComboBox()
        form.addRow('País emisor del documento',self.fields['pais']);form.addRow('Tipo de documento',self.fields['tipo_documento'])
        def types():
            self.fields['tipo_documento'].clear();self.fields['tipo_documento'].addItem('Seleccione…',None)
            for key,text in countries.get(self.fields['pais'].currentData(),{}).get('documentos',{}).items():self.fields['tipo_documento'].addItem(text,key)
        self.fields['pais'].currentIndexChanged.connect(types);types()
        idx=self.fields['tipo_documento'].findData(data.get('tipo_documento'));self.fields['tipo_documento'].setCurrentIndex(max(0,idx))
        for key,title in [('numero_documento','Número de documento'),('primer_nombre','Primer nombre'),('segundo_nombre','Segundo nombre'),('primer_apellido','Primer apellido'),('segundo_apellido','Segundo apellido'),('fecha_nacimiento','Nacimiento (AAAA-MM-DD)'),('municipio_nacimiento','Ciudad / municipio de nacimiento'),('departamento_nacimiento','Departamento / provincia de nacimiento')]:
            inp=QLineEdit(str(data.get(key) or ''));inp.setMaxLength(100);self.fields[key]=inp;form.addRow(title,inp)
        nationals=[]
        for n in catalog.get('nationalities',[]):
            for field in ('m','f'):
                if (n[field],n[field]) not in nationals:nationals.append((n[field],n[field]))
        self.fields['sexo']=combo([('Masculino','M'),('Femenino','F'),('Otro','O')],data.get('sexo'))
        self.fields['nacionalidad']=combo(nationals,data.get('nacionalidad'))
        self.fields['pais_residencia']=combo([(text,code) for text,code in country_list if code!='OT'],data.get('pais_residencia'))
        for key,title in [('sexo','Sexo'),('nacionalidad','Nacionalidad'),('pais_residencia','País de residencia actual')]:form.addRow(title,self.fields[key])
        for key,title in [('autorizacion_laboral','Situación laboral en país de residencia'),('profesion','Profesión u oficio'),('telefono_codigo_pais','Código de teléfono'),('telefono','Teléfono'),('correo','Correo'),('direccion','Dirección')]:
            inp=QLineEdit(str(data.get(key) or ''));inp.setMaxLength(200);self.fields[key]=inp;form.addRow(title,inp)
        scroll.setWidget(content);layout.addWidget(scroll)
        controls=QDialogButtonBox(QDialogButtonBox.Save|QDialogButtonBox.Cancel);controls.accepted.connect(self.accept);controls.rejected.connect(self.reject);layout.addWidget(controls)
    def data(self):return {k:(v.currentData() if isinstance(v,QComboBox) else v.text().strip()) for k,v in self.fields.items()}

class PaymentForm(QDialog):
    def __init__(self,store,total,parent=None):
        super().__init__(parent);self.setWindowTitle('Cobrar venta');self.resize(560,420);self.payments=[];layout=QVBoxLayout(self)
        layout.addWidget(label(f'Total: {store.meta("business")["simbolo"]} {total}','title'))
        methods=store.meta('business')['metodos'];self.method=combo([(m['nombre'],m['clave']) for m in methods],blank=False)
        self.amount=QDoubleSpinBox();self.amount.setDecimals(2);self.amount.setRange(.01,999999999);self.amount.setValue(float(total))
        form=QFormLayout();form.addRow('Método',self.method);form.addRow('Importe',self.amount);layout.addLayout(form)
        layout.addWidget(button('Añadir pago',self.add,icon='plus'));self.list=table(['Método','Importe']);layout.addWidget(self.list)
        layout.addWidget(button('Quitar pago seleccionado',self.remove,icon='trash-2'))
        controls=QDialogButtonBox(QDialogButtonBox.Save|QDialogButtonBox.Cancel);controls.accepted.connect(self.accept);controls.rejected.connect(self.reject);layout.addWidget(controls)
    def add(self):
        self.payments.append({'metodo':self.method.currentData(),'monto':f'{self.amount.value():.2f}'});self.render()
    def remove(self):
        i=self.list.currentRow()
        if i>=0:self.payments.pop(i);self.render()
    def render(self):fill(self.list,[(p['metodo'],p['monto']) for p in self.payments])
    def accept(self):
        if not self.payments:self.add()
        super().accept()

class MainWindow(QMainWindow):
    def __init__(self,store,*,start_timer=True):
        super().__init__();self.store=store;self.service=Service(store);self.cart=[];self.last_sale=None;self.worker=None;self.switch_user=False
        self.setWindowTitle('Mi CV · Equipo JZ · Escritorio');self.resize(1320,850);self.setMinimumSize(1000,700)
        root=QWidget();self.setCentralWidget(root);outer=QHBoxLayout(root);outer.setContentsMargins(0,0,0,0)
        self.nav=QListWidget();self.nav.setObjectName('navigation');self.nav.setFixedWidth(195)
        for text,icon in [('Panel','layout-dashboard'),('Clientes','users'),('Punto de venta','shopping-cart'),('Contabilidad','book-open'),('Sincronización','refresh-cw'),('Licencia y respaldo','shield-check')]:
            item=QListWidgetItem(text)
            if (ASSETS/'icons'/f'{icon}.svg').is_file():
                nav_icon=QIcon(str(ASSETS/'icons'/f'{icon}.svg'));nav_icon.addFile(str(ASSETS/'icons'/f'{icon}-white.svg'),mode=QIcon.Selected);item.setIcon(nav_icon)
            self.nav.addItem(item)
        outer.addWidget(self.nav);area=QWidget();vertical=QVBoxLayout(area);vertical.setContentsMargins(16,12,16,12)
        header=QHBoxLayout();self.heading=label('Mi CV Escritorio','title');header.addWidget(self.heading,1);self.sync_button=button('Sincronizar ahora',self.synchronize,primary=True,icon='refresh-cw');header.addWidget(self.sync_button);vertical.addLayout(header)
        self.connection=label('','subtitle');vertical.addWidget(self.connection);self.pages=QStackedWidget();vertical.addWidget(self.pages,1);outer.addWidget(area,1)
        self.build_dashboard();self.build_clients();self.build_pos();self.build_ledger();self.build_sync();self.build_license()
        self.nav.currentRowChanged.connect(self.pages.setCurrentIndex);self.nav.setCurrentRow(0);self.build_desktop_controls();self.refresh()
        self.timer=QTimer(self);self.timer.timeout.connect(self.synchronize)
        if start_timer:self.timer.start(60000);QTimer.singleShot(300,self.synchronize)
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
        for i,(name,shortcut,icon) in enumerate([('Inicio','Ctrl+1','layout-dashboard'),('Clientes','Ctrl+2','users'),('Punto de venta','Ctrl+3','shopping-cart'),('Contabilidad','Ctrl+4','book-open'),('Sincronización','Ctrl+5','refresh-cw'),('Licencia y respaldo','Ctrl+6','shield-check')]):
            entry=action(module_menu,name,lambda checked=False,index=i:self.nav.setCurrentRow(index),shortcut,icon,quick=i in (1,2,3))
            if i in (2,3):entry.setVisible(self.service.allowed('pos.vender' if i==2 else 'contabilidad.ver'))
        toolbar.addSeparator();action(tools,'Sincronizar ahora',self.synchronize,'F5','refresh-cw',True)
        action(tools,'Sistema web',self.open_web,icon='globe');action(tools,'Editor de documentos',self.open_editor,icon='scan-line')
        action(help_menu,'Acerca de Mi CV',lambda:QMessageBox.information(self,'Mi CV Escritorio','Mi CV Escritorio 1.2.0\nEquipo JZ · jz.com.ni\nClientes, ventas, caja y contabilidad locales.\nActivación directa y sincronización con Laravel.'))
        self.nav.currentRowChanged.connect(lambda i:self.statusBar().showMessage(self.nav.item(i).text()))

    def page(self):
        widget=QWidget();layout=QVBoxLayout(widget);layout.setContentsMargins(0,16,0,0);self.pages.addWidget(widget);return layout
    def run_action(self,callback):
        try:callback();self.refresh()
        except Exception as exc:QMessageBox.warning(self,'Revise la operación',str(exc))
    def build_dashboard(self):
        layout=self.page();layout.addWidget(label('Su cuenta, con o sin conexión','title'));self.cards=QHBoxLayout();self.card_labels=[]
        for _ in range(3):w=label('','card');self.card_labels.append(w);self.cards.addWidget(w)
        layout.addLayout(self.cards);layout.addWidget(label('Las operaciones locales se envían cuando vuelve la conexión. Los conflictos se conservan para revisión y no se sobrescriben automáticamente.'))
        layout.addWidget(button('Abrir sistema web completo',self.open_web,icon='globe'))
        self.editor_button=button('Editor de documentos sin conexión',self.open_editor,icon='scan-line');layout.addWidget(self.editor_button)
        layout.addWidget(label('En esta edición: clientes, ventas, caja y contabilidad locales. CV, tarjetas, chat y demás módulos se usan desde el sistema web.','subtitle'));layout.addStretch()
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
            dialog=ClientForm(self.store,row,self)
            if dialog.exec()==QDialog.Accepted:self.service.client(dialog.data(),row)
        self.run_action(work)
    def build_pos(self):
        layout=self.page();bar=QHBoxLayout();bar.addWidget(label('Punto de venta','title'));self.cash_label=label('Caja');bar.addWidget(self.cash_label,1)
        self.open_button=button('Abrir caja',lambda:self.cash_action(False),icon='wallet');self.close_button=button('Cerrar caja',lambda:self.cash_action(True),icon='lock-keyhole');bar.addWidget(self.open_button);bar.addWidget(self.close_button);layout.addLayout(bar)
        splitter=QSplitter(Qt.Horizontal);layout.addWidget(splitter,1)
        products=QWidget();left=QVBoxLayout(products);left.setContentsMargins(0,6,8,0);left.addWidget(label('Productos y servicios','subtitle'))
        self.product_search=QLineEdit();self.product_search.setPlaceholderText('Buscar nombre o código');self.product_search.textChanged.connect(self.render_products);self.product_search.returnPressed.connect(self.add_cart);left.addWidget(self.product_search)
        self.products_table=table(['Código','Producto','Precio','Existencia']);self.products_table.cellDoubleClicked.connect(lambda *_:self.add_cart());left.addWidget(self.products_table,1)
        add=QHBoxLayout();self.quantity=QDoubleSpinBox();self.quantity.setRange(.01,999999);self.quantity.setDecimals(2);self.quantity.setValue(1);add.addWidget(label('Cantidad'));add.addWidget(self.quantity);add.addWidget(button('Añadir',self.add_cart,icon='plus'));left.addLayout(add);splitter.addWidget(products)
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
            self.service.guard('pos.vender');_,total=self.service.quote(self.cart);dialog=PaymentForm(self.store,total,self)
            if dialog.exec()==QDialog.Accepted:
                self.last_sale=self.service.sale(self.cart,dialog.payments,self.sale_client.currentData());self.cart=[]
                QMessageBox.information(self,'Venta guardada','La venta se guardó localmente y quedó en la cola de sincronización.')
        self.run_action(work)
    def build_ledger(self):
        layout=self.page();bar=QHBoxLayout();bar.addWidget(label('Ingresos y gastos','title'),1);self.ledger_button=button('Registrar movimiento',self.ledger_new,primary=True,icon='plus');bar.addWidget(self.ledger_button);layout.addLayout(bar)
        self.balance=label('');layout.addWidget(self.balance);self.ledger_table=table(['Fecha','Tipo','Categoría','Concepto','Monto','Moneda','Estado']);layout.addWidget(self.ledger_table)
        bar=QHBoxLayout();bar.addWidget(button('Exportar Excel',lambda:self.export_table(self.ledger_table,'Contabilidad','xlsx'),icon='file-spreadsheet'));bar.addWidget(button('Exportar PDF',lambda:self.export_table(self.ledger_table,'Contabilidad','pdf'),icon='file-down'));bar.addStretch();layout.addLayout(bar)
    def ledger_new(self):
        def work():
            self.service.guard('contabilidad.registrar');dialog=QDialog(self);dialog.setWindowTitle('Registrar ingreso o gasto');form=QFormLayout(dialog)
            kind=combo([('Ingreso','ingreso'),('Gasto','gasto')],blank=False);form.addRow('Tipo',kind);inputs={}
            for key,title,default in [('fecha','Fecha (AAAA-MM-DD)',date.today().isoformat()),('categoria','Categoría',''),('concepto','Concepto',''),('monto','Monto',''),('referencia','Referencia','')]:
                inp=QLineEdit(default);inp.setMaxLength(200);inputs[key]=inp;form.addRow(title,inp)
            controls=QDialogButtonBox(QDialogButtonBox.Save|QDialogButtonBox.Cancel);controls.accepted.connect(dialog.accept);controls.rejected.connect(dialog.reject);form.addRow(controls)
            if dialog.exec()==QDialog.Accepted:self.service.ledger({'tipo':kind.currentData(),**{k:v.text().strip() for k,v in inputs.items()}})
        self.run_action(work)
    def build_sync(self):
        layout=self.page();layout.addWidget(label('Estado de cada operación','title'));layout.addWidget(label('Pendiente: guardada en este equipo. Sincronizada: confirmada por Laravel. Revisar: el servidor detectó un conflicto; la operación permanece guardada.'))
        self.operations_table=table(['Fecha local','Tipo','Referencia','Estado','Detalle']);layout.addWidget(self.operations_table)
        layout.addWidget(button('Reintentar operación seleccionada',self.retry,icon='refresh-cw'))
        layout.addWidget(label('Si un cliente cambió en la web, no se sobrescribe automáticamente. Revise ambas versiones con el encargado. Las operaciones financieras originales no se editan silenciosamente.','subtitle'))
    def retry(self):
        i=self.operations_table.currentRow()
        if i>=0:self.store.retry(self.operation_rows[i]['uuid']);self.refresh();self.synchronize()
    def build_license(self):
        layout=self.page();layout.addWidget(label('Cuenta y datos locales','title'));self.license_text=label('');layout.addWidget(self.license_text)
        layout.addWidget(button('Reactivar / renovar acceso con mi cuenta',self.reactivate,icon='key-round'));layout.addWidget(button('Crear copia de seguridad cifrada',self.backup,icon='shield-check'));layout.addWidget(button('Abrir sistema web',self.open_web,icon='globe'))
        layout.addWidget(label('La copia de seguridad contiene la base local cifrada y su clave protegida. En Windows debe restaurarse con el mismo perfil de usuario. No desinstale ni borre los datos mientras haya operaciones pendientes.','subtitle'));layout.addStretch()
    def reactivate(self):
        if self.worker and self.worker.isRunning():return
        if Activate(self.store,self).exec()==QDialog.Accepted:self.refresh();self.synchronize()
    def backup(self):
        file,_=QFileDialog.getSaveFileName(self,'Copia de seguridad',f'MiCV-respaldo-{date.today()}.zip','ZIP (*.zip)')
        if file:self.run_action(lambda:self.store.backup(Path(file)))
    def open_web(self):
        profile=self.store.meta('profile')
        if profile:QDesktopServices.openUrl(QUrl(profile['server_url']))
    def open_editor(self):
        def work():
            self.service.guard('documentos');path=ASSETS/'editor'/'index.html'
            if not path.is_file():raise AccessError('El editor local no está incluido en esta compilación.')
            QDesktopServices.openUrl(QUrl.fromLocalFile(str(path)))
        self.run_action(work)
    def synchronize(self):
        if not self.store.meta('profile') or (self.worker and self.worker.isRunning()):return
        self.sync_button.setEnabled(False);self.connection.setText('Sincronizando… Puede continuar trabajando con sus datos locales.')
        self.statusBar().showMessage('Sincronizando con el servidor…');self.worker=Worker(lambda:Sync(self.store).run(),self);self.worker.result.connect(self.sync_finished);self.worker.start()
    def sync_finished(self,text,ok):
        self.worker.wait();self.sync_button.setEnabled(True);self.refresh();self.connection.setText(text);self.statusBar().showMessage(text)
    def refresh(self):
        try:claims=self.service.claims();scopes=claims['scopes'];expiry=datetime.fromtimestamp(claims['valid_until']).strftime('%d/%m/%Y %H:%M');self.heading.setText(self.store.meta('business',{}).get('nombre','Mi CV Escritorio'));self.license_text.setText(f"Usuario: {claims['nombre']}\nEquipo: {self.store.meta('device_uuid')}\nAutorización sin conexión hasta: {expiry}\nServidor: {self.store.meta('profile')['server_url']}")
        except AccessError:scopes=[];claims={};self.license_text.setText('Equipo sin activar.')
        role=claims.get('role','cyber');personal=role=='cliente'
        self.nav.item(1).setText('Mi ficha' if personal else 'Clientes')
        self.nav.item(2).setHidden('pos.vender' not in scopes);self.nav.item(3).setHidden('contabilidad.ver' not in scopes)
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
        self.status_info.setText(f'{pending} pendientes · {conflicts} por revisar · Datos locales')
        for w,text in zip(self.card_labels,[f'{len(self.client_rows)} clientes locales',f'{pending} operaciones pendientes',f'{conflicts} por revisar']):w.setText(text)
    def export_table(self,widget,name,format):
        rows=[[widget.item(i,j).text() if widget.item(i,j) else '' for j in range(widget.columnCount())] for i in range(widget.rowCount())];headers=[widget.horizontalHeaderItem(i).text() for i in range(widget.columnCount())]
        file,_=QFileDialog.getSaveFileName(self,'Exportar '+name,name+'.'+format,f'{format.upper()} (*.{format})')
        if not file:return
        def work():
            permission='contabilidad.exportar' if name=='Contabilidad' else 'clientes.ver'
            if not self.service.allowed(permission):raise AccessError('Su usuario no tiene permiso para exportar este listado.')
            if format=='xlsx':
                from openpyxl import Workbook
                from openpyxl.styles import Font,PatternFill,Alignment
                book=Workbook();sheet=book.active;sheet.title=name;sheet.append([name]);sheet.append(headers)
                for row in rows:sheet.append(row)
                for row in sheet:
                    for cell in row:cell.data_type='s';cell.alignment=Alignment(wrap_text=True,vertical='center')
                for cell in sheet[2]:cell.font=Font(color='FFFFFF',bold=True);cell.fill=PatternFill('solid',fgColor='0B3C74')
                for column in sheet.columns:sheet.column_dimensions[column[0].column_letter].width=26
                sheet.freeze_panes='A3';sheet.auto_filter.ref=f'A2:{sheet.cell(sheet.max_row,sheet.max_column).coordinate}';sheet.print_options.horizontalCentered=True;sheet.sheet_properties.pageSetUpPr.fitToPage=True;sheet.page_setup.orientation='landscape';sheet.page_setup.fitToWidth=1;book.save(file)
            else:
                body='<h1>'+html.escape(name)+'</h1><table border="1" cellpadding="6"><tr>'+''.join('<th>'+html.escape(h)+'</th>' for h in headers)+'</tr>'+''.join('<tr>'+''.join('<td>'+html.escape(v)+'</td>' for v in row)+'</tr>' for row in rows)+'</table>'
                self.pdf_html(body,file,landscape=True)
        self.run_action(work)
    def pdf_html(self,body,file,landscape=False):
        printer=QPrinter(QPrinter.HighResolution);printer.setOutputFormat(QPrinter.PdfFormat);printer.setOutputFileName(file);printer.setPageSize(QPageSize(QPageSize.A4))
        if landscape:
            from PySide6.QtGui import QPageLayout
            printer.setPageOrientation(QPageLayout.Landscape)
        document=QTextDocument();document.setDefaultStyleSheet('body{font-family:Arial;color:#19334b}h1{color:#0b3c74}th{background:#0b3c74;color:white}td{font-size:10pt}');document.setHtml(body);document.print_(printer)
    def receipt(self):
        if not self.last_sale:QMessageBox.information(self,'Comprobante','Registre una venta en esta sesión para guardar su comprobante local.');return
        op=self.last_sale;file,_=QFileDialog.getSaveFileName(self,'Guardar comprobante','Comprobante-local.pdf','PDF (*.pdf)')
        if file:
            lines=''.join('<tr><td>'+html.escape(l['nombre'])+'</td><td>'+html.escape(l['cantidad'])+'</td><td>'+html.escape(l['total'])+'</td></tr>' for l in op['data']['items'])
            self.run_action(lambda:self.pdf_html('<h1>Comprobante local</h1><p>'+html.escape(self.store.meta('business')['nombre'])+'</p><p>Referencia: '+op['uuid']+'</p><p>Fecha local: '+html.escape(op['occurred_at'])+'</p><table cellpadding="8">'+lines+'</table><h2>Total: '+html.escape(op['data']['moneda']+' '+op['data']['total'])+'</h2><p>Consulte el estado de sincronización para confirmar su registro en Laravel.</p>',file))
    def change_account(self):
        self.switch_user=True
        if not self.close():self.switch_user=False

    def closeEvent(self,event):
        if self.worker and self.worker.isRunning():
            QMessageBox.information(self,'Sincronizando','Espere a que termine la conexión antes de cerrar. Sus datos locales están guardados.');event.ignore();return
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
    app=QApplication(sys.argv);app.setApplicationName('Mi CV Escritorio');app.setOrganizationName('Equipo JZ');app.setApplicationVersion('1.2.0');app.setStyle('Fusion');app.setStyleSheet(STYLE)
    folder=Path(os.environ.get('MICV_DESKTOP_HOME') or (Path(os.environ.get('LOCALAPPDATA',str(Path.home()/'.local/share')))/'EquipoJZ'/'MiCVDesktop'));folder.mkdir(parents=True,exist_ok=True)
    lock=QLockFile(str(folder/'running.lock'))
    if not lock.tryLock(100):QMessageBox.information(None,'Mi CV','El programa ya está abierto en este perfil.');return 1
    if '--smoke-test' in sys.argv:
        window=MainWindow(Store(folder),start_timer=False);window.show();app.processEvents();window.close();return 0
    while True:
        records=local_accounts(folder);store=None
        if records:
            choices=[f"{i+1}. {c['nombre']} · {c.get('role','empresa')} · {c.get('negocio','')}" for i,(_,c) in enumerate(records)]+['Entrar con otra cuenta…']
            selected,ok=QInputDialog.getItem(None,'Mi CV · Cuentas de este equipo','Seleccione su cuenta',choices,0,False)
            if not ok:return 0
            index=choices.index(selected)
            if index<len(records):
                store=records[index][0]
                pin,ok=QInputDialog.getText(None,'Abrir cuenta','PIN local de esta cuenta',QLineEdit.Password)
                if not ok:continue
                try:store.unlock(pin)
                except AccessError as exc:QMessageBox.warning(None,'Acceso local',str(exc));continue
        if store is None:
            fresh=Store(folder/'accounts'/str(uuid.uuid4()))
            dialog=Activate(fresh,resolve_store=lambda a,c,f:resolve_local_account(records,a,c,f))
            if dialog.exec()!=QDialog.Accepted:
                if records:continue
                return 0
            store=dialog.store
        window=MainWindow(store);window.show();code=app.exec()
        if not window.switch_user:return code
