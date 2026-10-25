from __future__ import annotations
import html, json, os, sys, time
from pathlib import Path
from datetime import date, datetime
from PySide6.QtCore import Qt, QThread, Signal, QTimer, QUrl, QLockFile
from PySide6.QtGui import QDesktopServices, QIcon, QTextDocument, QPageSize
from PySide6.QtPrintSupport import QPrinter
from PySide6.QtSvg import QSvgRenderer  # Incluye el soporte SVG al congelar la aplicación.
from PySide6.QtWidgets import (QApplication, QMainWindow, QWidget, QDialog, QVBoxLayout, QHBoxLayout, QFormLayout,
    QLabel, QPushButton, QLineEdit, QComboBox, QDoubleSpinBox, QTableWidget, QTableWidgetItem, QHeaderView,
    QAbstractItemView, QListWidget, QListWidgetItem, QStackedWidget, QMessageBox, QFileDialog, QDialogButtonBox, QInputDialog, QScrollArea)
from .storage import Store
from .service import Service
from .sync import Sync
from .security import AccessError

ASSETS = Path(__file__).resolve().parent.parent / 'assets'
STYLE = '''
QWidget{font-family:Segoe UI,Arial;font-size:14px;color:#182c42;background:#f3f5f8;}
QMainWindow{background:#f3f5f8;} QLabel{background:transparent;} QLabel#title{font-size:24px;font-weight:700;color:#0b3c74;}
QLabel#subtitle{color:#64748b;} QLabel#card{padding:22px;background:white;border:1px solid #e0e6ed;border-radius:12px;font-size:18px;font-weight:600;}
QPushButton{background:white;color:#0b3c74;border:1px solid #d0d9e3;border-radius:7px;padding:10px 16px;font-weight:600;min-height:22px;}
QPushButton:hover{background:#eaf1f8;} QPushButton:disabled{color:#9aa8b7;background:#edf0f4;}
QPushButton#primary{background:#0b3c74;color:white;border-color:#0b3c74;} QPushButton#primary:hover{background:#15558d;}
QLineEdit,QComboBox,QDoubleSpinBox{background:white;border:1px solid #cbd5e1;border-radius:6px;padding:9px;min-height:22px;}
QLineEdit:focus,QComboBox:focus{border:2px solid #0b3c74;} QTableWidget{background:white;alternate-background-color:#f7f9fc;border:1px solid #dce3eb;border-radius:8px;gridline-color:#eef1f5;}
QHeaderView::section{background:#eaf0f7;color:#0b3c74;padding:12px;border:none;font-weight:600;} QTableWidget::item{padding:9px;} QTableWidget::item:selected{background:#dceafd;color:#0b3c74;}
QListWidget#navigation{background:#071e38;color:white;border:none;padding:12px;font-weight:600;} QListWidget#navigation::item{padding:15px 12px;border-radius:7px;}
QListWidget#navigation::item:selected{background:#314233;color:#f2d577;} QScrollArea{border:none;}
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
    def __init__(self,store,parent=None):
        super().__init__(parent); self.store=store; self.profile=store.meta('profile'); self.setWindowTitle('Activar Mi CV Escritorio'); self.resize(540,540)
        layout=QVBoxLayout(self);layout.addWidget(label('Conectar este equipo','title'))
        layout.addWidget(label('Importe el archivo de conexión descargado desde Licencias de escritorio en Laravel. Luego identifíquese con su usuario y licencia.'))
        self.file_text=label(self.profile['server_url'] if self.profile else 'Sin archivo de conexión')
        layout.addWidget(self.file_text);layout.addWidget(button('Importar configuración',self.load,icon='folder-open'))
        form=QFormLayout();self.inputs={}
        for key,title in [('user','Usuario o correo'),('password','Contraseña del sistema'),('key','Clave de licencia'),('pin','Nuevo PIN local (mín. 6)'),('repeat','Repita el PIN local')]:
            inp=QLineEdit();self.inputs[key]=inp
            if key in ('password','key','pin','repeat'):inp.setEchoMode(QLineEdit.Password)
            form.addRow(title,inp)
        layout.addLayout(form);self.status=label('Necesita internet para activar el equipo. El PIN local permite abrirlo después sin internet.');layout.addWidget(self.status)
        self.go=button('Activar equipo',self.activate,primary=True,icon='key-round');layout.addWidget(self.go)
    def load(self):
        path,_=QFileDialog.getOpenFileName(self,'Configuración de conexión','','JSON (*.json)')
        if path:
            try:
                from .security import check_profile
                self.profile=check_profile(json.loads(Path(path).read_text(encoding='utf-8')))
                self.file_text.setText(self.profile['server_url'])
            except Exception as exc:QMessageBox.warning(self,'Configuración',str(exc))
    def activate(self):
        data={key:inp.text() for key,inp in self.inputs.items()}
        if not self.profile or data['pin']!=data['repeat']:
            QMessageBox.warning(self,'Revisar','Importe la configuración y confirme que los PIN coinciden.');return
        self.go.setEnabled(False);self.status.setText('Validando acceso…')
        self.worker=Worker(lambda:Sync(self.store).activate(self.profile,data['user'],data['password'],data['key'],data['pin']),self)
        self.worker.result.connect(self.finished);self.worker.start()
    def finished(self,text,ok):
        self.worker.wait()
        self.go.setEnabled(True);self.status.setText(text)
        self.inputs['password'].clear();self.inputs['key'].clear()
        if ok:self.accept()
    def reject(self):
        if hasattr(self,'worker') and self.worker.isRunning():return
        super().reject()

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
        super().__init__();self.store=store;self.service=Service(store);self.cart=[];self.last_sale=None;self.worker=None
        self.setWindowTitle('Mi CV · Equipo JZ · Escritorio');self.resize(1320,850);self.setMinimumSize(980,680)
        root=QWidget();self.setCentralWidget(root);outer=QHBoxLayout(root);outer.setContentsMargins(0,0,0,0)
        self.nav=QListWidget();self.nav.setObjectName('navigation');self.nav.setFixedWidth(230)
        for text,icon in [('Panel','layout-dashboard'),('Clientes','users'),('Punto de venta','shopping-cart'),('Contabilidad','book-open'),('Sincronización','refresh-cw'),('Licencia y respaldo','shield-check')]:
            item=QListWidgetItem(text)
            if (ASSETS/'icons'/f'{icon}.svg').is_file():item.setIcon(QIcon(str(ASSETS/'icons'/f'{icon}-white.svg')))
            self.nav.addItem(item)
        outer.addWidget(self.nav);area=QWidget();vertical=QVBoxLayout(area);vertical.setContentsMargins(24,20,24,20)
        header=QHBoxLayout();self.heading=label('Mi CV Escritorio','title');header.addWidget(self.heading,1);self.sync_button=button('Sincronizar ahora',self.synchronize,primary=True,icon='refresh-cw');header.addWidget(self.sync_button);vertical.addLayout(header)
        self.connection=label('','subtitle');vertical.addWidget(self.connection);self.pages=QStackedWidget();vertical.addWidget(self.pages,1);outer.addWidget(area,1)
        self.build_dashboard();self.build_clients();self.build_pos();self.build_ledger();self.build_sync();self.build_license()
        self.nav.currentRowChanged.connect(self.pages.setCurrentIndex);self.nav.setCurrentRow(0);self.refresh()
        self.timer=QTimer(self);self.timer.timeout.connect(self.synchronize)
        if start_timer:self.timer.start(60000);QTimer.singleShot(300,self.synchronize)
    def page(self):
        widget=QWidget();layout=QVBoxLayout(widget);layout.setContentsMargins(0,16,0,0);self.pages.addWidget(widget);return layout
    def run_action(self,callback):
        try:callback();self.refresh()
        except Exception as exc:QMessageBox.warning(self,'Revise la operación',str(exc))
    def build_dashboard(self):
        layout=self.page();layout.addWidget(label('Su negocio, con o sin conexión','title'));self.cards=QHBoxLayout();self.card_labels=[]
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
        layout=self.page();bar=QHBoxLayout();self.cash_label=label('Caja');bar.addWidget(self.cash_label,1)
        self.open_button=button('Abrir caja',lambda:self.cash_action(False),icon='wallet');self.close_button=button('Cerrar caja',lambda:self.cash_action(True),icon='lock-keyhole');bar.addWidget(self.open_button);bar.addWidget(self.close_button);layout.addLayout(bar)
        self.product_search=QLineEdit();self.product_search.setPlaceholderText('Buscar producto por nombre o código');self.product_search.textChanged.connect(self.render_products);layout.addWidget(self.product_search)
        self.products_table=table(['Código','Producto','Precio','Existencia']);self.products_table.cellDoubleClicked.connect(lambda *_:self.add_cart());layout.addWidget(self.products_table,1)
        add=QHBoxLayout();self.quantity=QDoubleSpinBox();self.quantity.setRange(.01,999999);self.quantity.setDecimals(2);self.quantity.setValue(1);add.addWidget(label('Cantidad'));add.addWidget(self.quantity);add.addWidget(button('Añadir al carrito',self.add_cart,icon='plus'));add.addStretch();layout.addLayout(add)
        self.cart_table=table(['Producto','Cantidad','Precio','Total']);layout.addWidget(self.cart_table,1)
        foot=QHBoxLayout();foot.addWidget(button('Quitar producto',self.remove_cart,icon='trash-2'));self.sale_client=QComboBox();foot.addWidget(self.sale_client,1);self.total_label=label('Total: 0','title');foot.addWidget(self.total_label)
        self.pay_button=button('Cobrar',self.pay,primary=True,icon='banknote');foot.addWidget(self.pay_button);layout.addLayout(foot)
        layout.addWidget(button('Guardar último comprobante local en PDF',self.receipt,icon='file-down'))
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
        layout=self.page();layout.addWidget(label('Licencia y datos locales','title'));self.license_text=label('');layout.addWidget(self.license_text)
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
        self.worker=Worker(lambda:Sync(self.store).run(),self);self.worker.result.connect(self.sync_finished);self.worker.start()
    def sync_finished(self,text,ok):
        self.sync_button.setEnabled(True);self.refresh();self.connection.setText(text)
    def refresh(self):
        try:claims=self.service.claims();scopes=claims['scopes'];expiry=datetime.fromtimestamp(claims['valid_until']).strftime('%d/%m/%Y %H:%M');self.heading.setText(self.store.meta('business',{}).get('nombre','Mi CV Escritorio'));self.license_text.setText(f"Usuario: {claims['nombre']}\nEquipo: {self.store.meta('device_uuid')}\nAutorización sin conexión hasta: {expiry}\nServidor: {self.store.meta('profile')['server_url']}")
        except AccessError:scopes=[];self.license_text.setText('Equipo sin activar.')
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
    def closeEvent(self,event):
        if self.worker and self.worker.isRunning():
            QMessageBox.information(self,'Sincronizando','Espere a que termine la conexión antes de cerrar. Sus datos locales están guardados.');event.ignore();return
        event.accept()

def main():
    app=QApplication(sys.argv);app.setApplicationName('Mi CV Escritorio');app.setOrganizationName('Equipo JZ');app.setStyleSheet(STYLE)
    folder=Path(os.environ.get('MICV_DESKTOP_HOME') or (Path(os.environ.get('LOCALAPPDATA',str(Path.home()/'.local/share')))/'EquipoJZ'/'MiCVDesktop'))
    try:store=Store(folder)
    except Exception as exc:QMessageBox.critical(None,'No se pudieron abrir los datos',str(exc));return 1
    lock=QLockFile(str(folder/'running.lock'))
    if not lock.tryLock(100):QMessageBox.information(None,'Mi CV','El programa ya está abierto en este perfil.');return 1
    if '--smoke-test' in sys.argv:
        window=MainWindow(store,start_timer=False);window.show();app.processEvents();window.close();return 0
    if not store.meta('token'):
        if Activate(store).exec()!=QDialog.Accepted:return 0
    else:
        while True:
            pin,ok=QInputDialog.getText(None,'Abrir Mi CV','PIN local',QLineEdit.Password)
            if not ok:return 0
            try:store.unlock(pin);break
            except AccessError as exc:QMessageBox.warning(None,'Acceso local',str(exc))
    window=MainWindow(store);window.show();return app.exec()
