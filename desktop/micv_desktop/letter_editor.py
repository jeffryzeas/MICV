"""Native editor using Mi CV's authorized letter templates."""
from datetime import date
from pathlib import Path
import sqlite3
from uuid import uuid4

import httpx
from PySide6.QtCore import Qt
from PySide6.QtGui import QFont, QPageSize
from PySide6.QtPrintSupport import QPrintDialog, QPrinter
from PySide6.QtWidgets import (
    QComboBox, QDialog, QFileDialog, QFormLayout, QHBoxLayout, QInputDialog,
    QLabel, QLineEdit, QMessageBox, QPushButton, QScrollArea, QSplitter,
    QTextEdit, QVBoxLayout, QWidget,
)

from .security import AccessError


class LetterEditor(QDialog):
    def __init__(self, engine, store, parent=None, *, kind=None, client_id=None):
        super().__init__(parent)
        self.store = store
        self.draft_id = None
        self.setWindowTitle('Editor de cartas · Mi CV')
        self.resize(1100, 730)
        self.http = None
        self.catalog = None
        try:
            if engine is not None:
                self.catalog = self.load_catalog(engine)
            self.build(kind, client_id)
        except Exception:
            self.close_session()
            raise

    def load_catalog(self, engine):
        engine.configuration()
        self.http = httpx.Client(base_url=engine.origin, headers=engine.request_headers(),
                                 timeout=8, trust_env=False)
        try:
            if getattr(engine, 'connected', False):
                response = self.http.post('/desktop/session')
                if response.status_code in (401, 403):
                    raise AccessError('La sesión para Cartas fue denegada.')
                if response.status_code not in (302, 303):
                    self.close_session()
                    return None
            catalog = self.http.get('/cartas', headers={'Accept': 'application/json'})
            if catalog.status_code in (401, 403):
                raise AccessError('Su cuenta no tiene acceso al módulo Cartas.')
            if catalog.status_code == 200 and 'application/json' in catalog.headers.get('Content-Type', ''):
                return catalog.json()
        except (httpx.TransportError, ValueError):
            pass
        self.close_session()
        return None

    def close_session(self):
        if self.http is not None:
            self.http.close()
            self.http = None

    def build(self, kind, client_id):
        layout = QVBoxLayout(self)
        toolbar = QHBoxLayout()
        for caption, handler in [('Nueva carta', self.new_letter), ('Guardar borrador', self.save_draft), ('Abrir borrador', self.open_draft),
                                 ('Guardar PDF', self.export_pdf), ('Imprimir', self.print_letter)]:
            button = QPushButton(caption)
            button.setAutoDefault(False)
            button.clicked.connect(handler)
            toolbar.addWidget(button)
        toolbar.addStretch()
        layout.addLayout(toolbar)

        splitter = QSplitter()
        sidebar = QWidget()
        self.form = fields = QFormLayout(sidebar)
        self.kind = QComboBox()
        catalog = self.catalog or {'tipos': {'libre': 'Carta libre'}, 'modelos': 1, 'cliente': None}
        for key, title in catalog['tipos'].items():
            self.kind.addItem(title, key)
        if self.kind.findData(kind) >= 0:
            self.kind.setCurrentIndex(self.kind.findData(kind))
        fields.addRow('Tipo de carta', self.kind)
        self.model = QComboBox()
        for number in range(1, catalog['modelos'] + 1):
            self.model.addItem(f'Modelo {number}', number)
        fields.addRow('Redacción', self.model)
        self.client = QComboBox()
        own = catalog['cliente']
        if own:
            self.client.addItem(own['texto'], own['id'])
            fields.addRow('Persona', self.client)
        else:
            if client_id:
                self.client.addItem('Cliente de la ficha', client_id)
            self.query = QLineEdit()
            self.query.setPlaceholderText('Nombre o documento')
            self.query.returnPressed.connect(self.search)
            search = QPushButton('Buscar cliente')
            search.setAutoDefault(False)
            search.clicked.connect(self.search)
            fields.addRow('Buscar', self.query)
            fields.addRow('', search)
            fields.addRow('Persona', self.client)
        self.fields = {}
        for key, title in [
            ('fecha_carta','Fecha de carta'), ('lugar','Lugar'), ('empresa','Empresa'),
            ('encargado_nombre','Destinatario'), ('cargo','Cargo'), ('area','Área'),
            ('tiempo_laborado','Tiempo laborado'), ('fecha_renuncia','Fecha de retiro'),
            ('firmante_nombre','Nombre del firmante'), ('firmante_celular','Teléfono del firmante'),
            ('relacion','Relación con la persona'),
        ]:
            field = QLineEdit()
            if key.startswith('fecha'):
                field.setPlaceholderText('AAAA-MM-DD')
            if key == 'fecha_carta':
                field.setText(date.today().isoformat())
            self.fields[key] = field
            fields.addRow(title, field)
        self.signer_gender = QComboBox()
        for label, value in [('Sin especificar', ''), ('Masculino', 'M'), ('Femenino', 'F'), ('Otro', 'O')]:
            self.signer_gender.addItem(label, value)
        fields.addRow('Tratamiento del firmante', self.signer_gender)
        load = QPushButton('Cargar plantilla')
        load.setAutoDefault(False)
        load.clicked.connect(self.load_template)
        fields.addRow('', load)
        scroll = QScrollArea()
        scroll.setWidgetResizable(True)
        scroll.setWidget(sidebar)
        splitter.addWidget(scroll)

        paper = QWidget()
        paper_layout = QVBoxLayout(paper)
        formatting = QHBoxLayout()
        bold = QPushButton('Negrita')
        bold.setAutoDefault(False)
        bold.clicked.connect(self.toggle_bold)
        formatting.addWidget(bold)
        italic = QPushButton('Cursiva')
        italic.setAutoDefault(False)
        italic.clicked.connect(lambda: self.editor.setFontItalic(not self.editor.fontItalic()))
        formatting.addWidget(italic)
        for label, alignment in [('Izquierda', Qt.AlignmentFlag.AlignLeft),
                                 ('Centrar', Qt.AlignmentFlag.AlignHCenter),
                                 ('Derecha', Qt.AlignmentFlag.AlignRight)]:
            button = QPushButton(label)
            button.setAutoDefault(False)
            button.clicked.connect(lambda checked=False, value=alignment: self.editor.setAlignment(value))
            formatting.addWidget(button)
        formatting.addStretch()
        paper_layout.addLayout(formatting)
        self.editor = QTextEdit()
        self.editor.setAcceptRichText(False)
        self.editor.setPlaceholderText('Cargue una plantilla o escriba una carta desde cero.')
        self.editor.setFont(QFont('Arial', 12))
        self.editor.document().setDocumentMargin(24)
        self.editor.document().setModified(False)
        paper_layout.addWidget(self.editor, 1)
        splitter.addWidget(paper)
        splitter.setSizes([360, 720])
        scroll.setVisible(self.catalog is not None)
        layout.addWidget(splitter, 1)
        self.status = QLabel()
        self.status.setWordWrap(True)
        if self.catalog is None:
            self.status.setText('Las plantillas no están disponibles. Puede escribir una carta o abrir un borrador guardado.')
        layout.addWidget(self.status)
        self.kind.currentIndexChanged.connect(self.update_fields)
        self.update_fields()

    def update_fields(self):
        kind = self.kind.currentData() or ''
        recommendation = kind.startswith('recomendacion')
        for name, field in self.fields.items():
            visible = (
                name in ('fecha_carta', 'lugar')
                or (name == 'fecha_renuncia' and kind.startswith('renuncia'))
                or (name in ('firmante_nombre', 'firmante_celular', 'relacion') and recommendation)
                or (name in ('empresa', 'encargado_nombre', 'cargo', 'area', 'tiempo_laborado') and kind != 'recomendacion_personal')
            )
            self.form.setRowVisible(field, visible)
        self.form.setRowVisible(self.signer_gender, recommendation)

    def new_letter(self):
        if self.can_replace():
            self.editor.clear()
            self.editor.document().setModified(False)
            self.draft_id = None
            self.status.setText('Puede escribir desde cero o cargar una plantilla.')
            self.editor.setFocus()


    def toggle_bold(self):
        self.editor.setFontWeight(QFont.Weight.Normal if self.editor.fontWeight() >= QFont.Weight.Bold else QFont.Weight.Bold)

    def can_replace(self):
        return not self.editor.document().isModified() or QMessageBox.question(
            self, 'Cambios sin guardar', '¿Desea descartar los cambios sin guardar?'
        ) == QMessageBox.StandardButton.Yes

    def search(self):
        query = self.query.text().strip()
        if len(query) < 2:
            self.status.setText('Escriba al menos dos caracteres.')
            return
        try:
            response = self.http.get('/cartas/buscar-clientes', params={'q': query},
                                     headers={'Accept': 'application/json'})
            response.raise_for_status()
            self.client.clear()
            for person in response.json():
                self.client.addItem(person['texto'], person['id'])
            self.status.setText('Seleccione una persona.' if self.client.count() else 'No se encontraron coincidencias.')
        except (httpx.HTTPError, ValueError):
            self.status.setText('No se pudo consultar la lista de clientes.')

    def load_template(self):
        if self.http is None:
            return
        if not self.client.currentData():
            self.status.setText('Primero seleccione una persona.')
            return
        if not self.can_replace():
            return
        data = {name: field.text().strip() for name, field in self.fields.items()
                if not field.isHidden() and field.text().strip()}
        if not self.signer_gender.isHidden() and self.signer_gender.currentData():
            data['firmante_sexo'] = self.signer_gender.currentData()
        data.update(cliente_id=self.client.currentData(), modelo=self.model.currentData())
        try:
            response = self.http.post('/cartas/' + self.kind.currentData() + '/vista',
                                      json=data, headers={'Accept': 'application/json',
                                                          'X-CSRF-TOKEN': self.catalog['csrf']})
            if response.status_code == 422:
                self.status.setText(response.json().get('message', 'Revise los datos de la carta.'))
                return
            response.raise_for_status()
            self.editor.setHtml(response.json()['html'])
            self.editor.document().setModified(True)
            self.draft_id = None
            self.status.setText('Plantilla cargada. Puede editar todo el documento.')
        except (httpx.HTTPError, ValueError, KeyError):
            self.status.setText('No se pudo cargar la plantilla. Su carta sigue abierta.')

    def save_draft(self):
        if not self.editor.toPlainText().strip():
            self.status.setText('Escriba el contenido de la carta.')
            return
        drafts = self.store.meta('letter_drafts', {})
        current = drafts.get(self.draft_id, {})
        title, accepted = QInputDialog.getText(self, 'Guardar carta', 'Nombre del borrador',
                                               text=current.get('title', self.kind.currentText()))
        if not accepted or not title.strip():
            return
        identifier = self.draft_id or str(uuid4())
        drafts[identifier] = {'title': title.strip()[:160], 'html': self.editor.toHtml()}
        try:
            self.store.set_meta('letter_drafts', drafts)
        except (OSError, sqlite3.Error):
            self.status.setText('No se pudo guardar el borrador. La carta sigue abierta.')
            return
        self.draft_id = identifier
        self.editor.document().setModified(False)
        self.status.setText('Borrador guardado en este equipo.')

    def open_draft(self):
        drafts = self.store.meta('letter_drafts', {})
        if not drafts:
            self.status.setText('Todavía no hay cartas guardadas.')
            return
        if not self.can_replace():
            return
        identifiers = list(reversed(drafts))
        titles = [f'{i}. {drafts[key]["title"]}' for i, key in enumerate(identifiers, 1)]
        title, accepted = QInputDialog.getItem(self, 'Abrir borrador', 'Carta', titles, editable=False)
        if accepted:
            self.draft_id = identifiers[titles.index(title)]
            self.editor.setHtml(drafts[self.draft_id]['html'])
            self.editor.document().setModified(False)
            self.status.setText('Borrador abierto.')

    def printer(self):
        printer = QPrinter(QPrinter.PrinterMode.HighResolution)
        printer.setPageSize(QPageSize(QPageSize.PageSizeId.Letter))
        return printer

    def export_pdf(self):
        if not self.editor.toPlainText().strip():
            return
        name, _ = QFileDialog.getSaveFileName(self, 'Guardar carta', 'Carta.pdf', 'PDF (*.pdf)')
        if not name:
            return
        path = Path(name)
        if path.suffix.lower() != '.pdf':
            path = path.with_name(path.name + '.pdf')
            if path.exists() and QMessageBox.question(
                self, 'Reemplazar PDF', f'Ya existe {path.name}. ¿Desea reemplazarlo?'
            ) != QMessageBox.StandardButton.Yes:
                return
        temporary = path.with_name(f'.micv-{uuid4().hex}.pdf')
        try:
            printer = self.printer()
            printer.setOutputFormat(QPrinter.OutputFormat.PdfFormat)
            printer.setOutputFileName(str(temporary))
            self.editor.document().print_(printer)
            with temporary.open('rb') as result:
                if result.read(5) != b'%PDF-':
                    raise OSError('Salida PDF inválida')
            temporary.replace(path)
            self.status.setText('Carta guardada en PDF.')
        except OSError:
            self.status.setText('No se pudo guardar el PDF. Revise la carpeta elegida.')
        finally:
            temporary.unlink(missing_ok=True)

    def print_letter(self):
        if self.editor.toPlainText().strip():
            printer = self.printer()
            if QPrintDialog(printer, self).exec() == QDialog.DialogCode.Accepted:
                self.editor.document().print_(printer)

    def reject(self):
        if self.can_replace():
            super().reject()
