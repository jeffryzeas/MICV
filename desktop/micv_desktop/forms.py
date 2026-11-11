from pathlib import Path
from datetime import date
from decimal import Decimal
from PySide6.QtCore import Qt,QDate
from PySide6.QtGui import QIcon
from PySide6.QtWidgets import (QApplication,QDialog,QVBoxLayout,QHBoxLayout,QFormLayout,QWidget,QLabel,QPushButton,QScrollArea,QDialogButtonBox,QTabWidget,QLineEdit,QComboBox,QDoubleSpinBox,QDateEdit,QTableWidget,QTableWidgetItem,QHeaderView,QAbstractItemView)
ASSETS=Path(__file__).resolve().parent.parent/'assets'

def fit_dialog(dialog,width=720,height=580):
    screen=dialog.screen() or QApplication.primaryScreen()
    if screen:
        area=screen.availableGeometry();dialog.resize(min(width,max(320,area.width()-32)),min(height,max(260,area.height()-60)))
        dialog.move(area.center()-dialog.rect().center())

class AdaptiveDialog(QDialog):
    def __init__(self,title,parent=None,save_text='Guardar'):
        super().__init__(parent);self.setWindowTitle(title);self.setMinimumSize(320,260)
        outer=QVBoxLayout(self);outer.setContentsMargins(16,12,16,12)
        heading=QLabel(title);heading.setObjectName('title');outer.addWidget(heading)
        self.body=QVBoxLayout();outer.addLayout(self.body,1)
        self.error=QLabel();self.error.setTextFormat(Qt.PlainText);self.error.setWordWrap(True);self.error.setStyleSheet('color:#b42318;');self.error.hide();outer.addWidget(self.error)
        self.controls=QDialogButtonBox(QDialogButtonBox.Save|QDialogButtonBox.Cancel)
        self.save_button=self.controls.button(QDialogButtonBox.Save);self.save_button.setText(save_text);self.save_button.setObjectName('primary');self.save_button.setIcon(QIcon(str(ASSETS/'icons/save-white.svg')));self.save_button.setMinimumWidth(120)
        self.controls.button(QDialogButtonBox.Cancel).setText('Cancelar');self.controls.accepted.connect(self.accept);self.controls.rejected.connect(self.reject);outer.addWidget(self.controls)
        fit_dialog(self)
    def scroll_form(self):
        scroll=QScrollArea();scroll.setWidgetResizable(True);content=QWidget();form=QFormLayout(content);form.setRowWrapPolicy(QFormLayout.WrapLongRows);form.setSpacing(10);form.setFieldGrowthPolicy(QFormLayout.AllNonFixedFieldsGrow);scroll.setWidget(content);self.body.addWidget(scroll);return form
    def fail(self,exc):self.error.setText(str(exc));self.error.show()
    def showEvent(self,event):
        super().showEvent(event);fit_dialog(self,min(self.width(),720),min(self.height(),580))

def choices(items,value=None,blank=True):
    w=QComboBox()
    if blank:w.addItem('Seleccione…',None)
    for text,key in items:w.addItem(str(text),key)
    index=w.findData(value)
    if index>=0:w.setCurrentIndex(index)
    return w

class ClientForm(AdaptiveDialog):
    def __init__(self,store,row=None,parent=None,on_save=None):
        super().__init__('Editar cliente' if row else 'Registrar cliente',parent);self.on_save=on_save;self.fields={};self.field_tabs={};data=(row or {}).get('data',{});catalog=store.meta('catalogs',{})
        self.tabs=QTabWidget();self.body.addWidget(self.tabs);forms=[]
        for title in ['1. Identificación','2. Nacionalidad y residencia','3. Contacto']:
            scroll=QScrollArea();scroll.setWidgetResizable(True);content=QWidget();form=QFormLayout(content);form.setSpacing(10);form.setRowWrapPolicy(QFormLayout.WrapLongRows);form.setFieldGrowthPolicy(QFormLayout.AllNonFixedFieldsGrow);scroll.setWidget(content);self.tabs.addTab(scroll,title);forms.append(form)
        def add(key,title,tab,widget=None):
            w=widget or QLineEdit(str(data.get(key) or ''))
            if isinstance(w,QLineEdit):w.setMaxLength(200)
            self.fields[key]=w;self.field_tabs[key]=tab;forms[tab].addRow(title,w);return w
        countries=catalog.get('countries',{});country_items=[(p['nombre'],k) for k,p in countries.items()]
        add('pais','País emisor *',0,choices(country_items,data.get('pais')));add('tipo_documento','Tipo de documento *',0,QComboBox())
        def types():
            w=self.fields['tipo_documento'];w.clear();w.addItem('Seleccione…',None)
            for key,text in countries.get(self.fields['pais'].currentData(),{}).get('documentos',{}).items():w.addItem(text,key)
        self.fields['pais'].currentIndexChanged.connect(types);types();idx=self.fields['tipo_documento'].findData(data.get('tipo_documento'));self.fields['tipo_documento'].setCurrentIndex(max(0,idx))
        for key,title in [('numero_documento','Documento *'),('primer_nombre','Primer nombre *'),('segundo_nombre','Segundo nombre'),('primer_apellido','Primer apellido *'),('segundo_apellido','Segundo apellido'),('fecha_nacimiento','Fecha de nacimiento')]:
            w=add(key,title,0)
            if key=='fecha_nacimiento':w.setPlaceholderText('AAAA-MM-DD · opcional')
        nationals=[]
        for n in catalog.get('nationalities',[]):
            for field in ('m','f'):
                if (n[field],n[field]) not in nationals:nationals.append((n[field],n[field]))
        add('sexo','Sexo',1,choices([('Masculino','M'),('Femenino','F'),('Otro','O')],data.get('sexo')))
        add('nacionalidad','Nacionalidad *',1,choices(nationals,data.get('nacionalidad')))
        add('pais_residencia','País de residencia *',1,choices([(t,k) for t,k in country_items if k!='OT'],data.get('pais_residencia')))
        for key,title in [('municipio_nacimiento','Ciudad de nacimiento'),('departamento_nacimiento','Departamento de nacimiento'),('autorizacion_laboral','Situación laboral en residencia'),('profesion','Profesión u oficio')]:add(key,title,1)
        for key,title in [('telefono_codigo_pais','Código de teléfono'),('telefono','Teléfono'),('correo','Correo electrónico'),('direccion','Dirección')]:add(key,title,2)
    def data(self):return {k:(w.currentData() if isinstance(w,QComboBox) else w.text().strip()) for k,w in self.fields.items()}
    def accept(self):
        data=self.data()
        for key in ['pais','tipo_documento','numero_documento','primer_nombre','primer_apellido','nacionalidad','pais_residencia']:
            if not data.get(key):self.tabs.setCurrentIndex(self.field_tabs[key]);self.fields[key].setFocus();self.fail('Complete los campos marcados con *.');return
        try:
            if self.on_save:self.on_save(data)
        except Exception as exc:self.fail(exc);return
        super().accept()

class LedgerForm(AdaptiveDialog):
    def __init__(self,parent=None,on_save=None):
        super().__init__('Registrar ingreso o gasto',parent);self.on_save=on_save;form=self.scroll_form();self.kind=choices([('Ingreso','ingreso'),('Gasto','gasto')],blank=False);form.addRow('Tipo',self.kind)
        self.day=QDateEdit(QDate.currentDate());self.day.setCalendarPopup(True);self.day.setDisplayFormat('dd/MM/yyyy');form.addRow('Fecha',self.day);self.inputs={}
        for key,title in [('categoria','Categoría *'),('concepto','Concepto *'),('referencia','Referencia')]:
            w=QLineEdit();w.setMaxLength(200);self.inputs[key]=w;form.addRow(title,w)
        self.amount=QDoubleSpinBox();self.amount.setDecimals(2);self.amount.setRange(0,999999999);form.addRow('Importe *',self.amount)
    def accept(self):
        data={'tipo':self.kind.currentData(),'fecha':self.day.date().toString('yyyy-MM-dd'),'monto':f'{self.amount.value():.2f}',**{k:w.text().strip() for k,w in self.inputs.items()}}
        if not data['categoria'] or not data['concepto'] or self.amount.value()<=0:self.fail('Complete categoría, concepto y un importe mayor de cero.');return
        try:
            if self.on_save:self.on_save(data)
        except Exception as exc:self.fail(exc);return
        super().accept()

class PaymentForm(AdaptiveDialog):
    def __init__(self,store,total,parent=None,on_save=None):
        super().__init__('Cobrar venta',parent,'Cobrar y guardar');self.on_save=on_save;self.total=Decimal(str(total));self.payments=[];self.methods=store.meta('business')['metodos']
        self.body.addWidget(QLabel(f"Total: {store.meta('business')['simbolo']} {total}"));bar=QHBoxLayout();self.method=choices([(m['nombre'],m['clave']) for m in self.methods],blank=False);bar.addWidget(self.method,1);self.amount=QDoubleSpinBox();self.amount.setDecimals(2);self.amount.setRange(.01,999999999);self.amount.setValue(float(total));bar.addWidget(self.amount);add=QPushButton('Añadir pago');add.clicked.connect(self.add);bar.addWidget(add);self.body.addLayout(bar)
        self.list=QTableWidget(0,2);self.list.setHorizontalHeaderLabels(['Método','Importe']);self.list.horizontalHeader().setSectionResizeMode(QHeaderView.Stretch);self.list.setEditTriggers(QAbstractItemView.NoEditTriggers);self.list.setSelectionBehavior(QAbstractItemView.SelectRows);self.body.addWidget(self.list,1)
        remove=QPushButton('Quitar pago seleccionado');remove.clicked.connect(self.remove);self.body.addWidget(remove);self.balance=QLabel('');self.body.addWidget(self.balance);self.render()
    def add(self):
        if self.method.currentData() is None:self.fail('No hay un método de pago disponible. Sincronice la configuración.');return
        self.payments.append({'metodo':self.method.currentData(),'monto':f'{self.amount.value():.2f}'});self.render()
    def remove(self):
        i=self.list.currentRow()
        if i>=0:self.payments.pop(i);self.render()
    def render(self):
        self.list.setRowCount(len(self.payments));names={m['clave']:m['nombre'] for m in self.methods}
        for i,p in enumerate(self.payments):
            self.list.setItem(i,0,QTableWidgetItem(names.get(p['metodo'],p['metodo'])));self.list.setItem(i,1,QTableWidgetItem(p['monto']))
        paid=sum((Decimal(p['monto']) for p in self.payments),Decimal(0));self.balance.setText(f'Pagado: {paid:.2f} · Pendiente: {max(0,self.total-paid):.2f} · Cambio: {max(0,paid-self.total):.2f}')
    def accept(self):
        if not self.payments:self.add()
        paid=sum((Decimal(p['monto']) for p in self.payments),Decimal(0));other=sum((Decimal(p['monto']) for p in self.payments if p['metodo']!='efectivo'),Decimal(0))
        if paid<self.total or other>self.total:self.fail('Los pagos deben cubrir el total. Solo el efectivo puede generar cambio.');return
        try:
            if self.on_save:self.on_save(self.payments)
        except Exception as exc:self.fail(exc);return
        super().accept()
