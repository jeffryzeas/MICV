"""Private, account-scoped chat drafts used by the desktop."""
from datetime import datetime, timezone
from uuid import uuid4

from PySide6.QtWidgets import (
    QApplication, QComboBox, QDialog, QDialogButtonBox, QFormLayout, QLabel,
    QLineEdit, QMessageBox, QPlainTextEdit, QVBoxLayout,
)

from .security import AccessError
from .workspace_access import authorize_workspace


class ChatDrafts:
    def __init__(self, store):
        self.store = store

    def authorize(self):
        claims = authorize_workspace(self.store)
        if claims.get('role') not in ('admin', 'cliente') and 'chat' not in claims.get('permissions', []):
            raise AccessError('Su cuenta no tiene permiso para usar el chat.')

    def rows(self):
        self.authorize()
        return sorted(self.store.meta('chat_drafts', {}).values(), key=lambda row: row['updated'], reverse=True)

    def save(self, identifier, title, text):
        self.authorize()
        title, text = title.strip(), text.strip()
        if not 1 <= len(title) <= 100 or not 1 <= len(text) <= 6000:
            raise AccessError('Escriba un nombre de hasta 100 caracteres y un mensaje de hasta 6.000.')
        drafts = self.store.meta('chat_drafts', {})
        identifier = identifier or str(uuid4())
        drafts[identifier] = {'id': identifier, 'title': title, 'text': text,
                              'updated': datetime.now(timezone.utc).isoformat()}
        self.store.set_meta('chat_drafts', drafts)
        return identifier

    def delete(self, identifier):
        self.authorize()
        drafts = self.store.meta('chat_drafts', {})
        if drafts.pop(identifier, None) is not None:
            self.store.set_meta('chat_drafts', drafts)


class ChatDraftsDialog(QDialog):
    def __init__(self, drafts, parent=None):
        super().__init__(parent)
        self.drafts = drafts
        self.identifier = None
        self.original = ('', '')
        self.setWindowTitle('Borradores de chat')
        self.resize(620, 480)
        layout = QVBoxLayout(self)
        note = QLabel('Guarde el texto en este equipo. Cópielo y péguelo en la conversación cuando vuelva la conexión.')
        note.setWordWrap(True)
        layout.addWidget(note)
        form = QFormLayout()
        self.items = QComboBox()
        self.title = QLineEdit()
        self.title.setMaxLength(100)
        form.addRow('Borradores', self.items)
        form.addRow('Contacto o asunto', self.title)
        layout.addLayout(form)
        self.message = QPlainTextEdit()
        layout.addWidget(self.message, 1)
        self.status = QLabel()
        self.status.setWordWrap(True)
        layout.addWidget(self.status)
        actions = QDialogButtonBox(QDialogButtonBox.StandardButton.Close)
        for label, callback in [('Guardar', self.save), ('Copiar texto', self.copy), ('Eliminar', self.delete)]:
            actions.addButton(label, QDialogButtonBox.ButtonRole.ActionRole).clicked.connect(callback)
        actions.rejected.connect(self.reject)
        layout.addWidget(actions)
        self.items.currentIndexChanged.connect(self.select)
        self.reload(None)

    def reload(self, identifier):
        self.items.blockSignals(True)
        try:
            self.items.clear()
            self.items.addItem('Nuevo borrador', None)
            for draft in self.drafts.rows():
                self.items.addItem(draft['title'], draft)
            self.items.setCurrentIndex(self.index_of(identifier))
            self.display()
        finally:
            self.items.blockSignals(False)

    def index_of(self, identifier):
        return next((i for i in range(1, self.items.count())
                     if self.items.itemData(i)['id'] == identifier), 0)

    def display(self):
        row = self.items.currentData()
        self.identifier = row['id'] if row else None
        self.original = (row['title'], row['text']) if row else ('', '')
        self.title.setText(self.original[0])
        self.message.setPlainText(self.original[1])
        self.status.clear()

    def can_discard(self):
        return (self.title.text(), self.message.toPlainText()) == self.original or QMessageBox.question(
            self, 'Cambios sin guardar', '¿Desea descartar los cambios de este borrador?'
        ) == QMessageBox.StandardButton.Yes

    def select(self):
        if self.can_discard():
            self.display()
        else:
            self.items.blockSignals(True)
            self.items.setCurrentIndex(self.index_of(self.identifier))
            self.items.blockSignals(False)

    def save(self):
        try:
            identifier = self.drafts.save(self.identifier, self.title.text(), self.message.toPlainText())
            self.reload(identifier)
            self.status.setText('Borrador guardado en este equipo.')
        except AccessError as error:
            self.status.setText(str(error))

    def copy(self):
        try:
            self.drafts.authorize()
            if self.message.toPlainText().strip():
                QApplication.clipboard().setText(self.message.toPlainText())
                self.status.setText('Texto copiado.')
        except AccessError as error:
            self.status.setText(str(error))

    def delete(self):
        if self.identifier and QMessageBox.question(
            self, 'Eliminar borrador', '¿Desea eliminar este borrador?'
        ) == QMessageBox.StandardButton.Yes:
            try:
                self.drafts.delete(self.identifier)
                self.reload(None)
                self.status.setText('Borrador eliminado.')
            except AccessError as error:
                self.status.setText(str(error))

    def reject(self):
        if self.can_discard():
            super().reject()
