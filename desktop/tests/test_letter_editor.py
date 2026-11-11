from pathlib import Path
import os
import sqlite3
import tempfile
import unittest
from unittest.mock import patch

os.environ.setdefault('QT_QPA_PLATFORM', 'offscreen')

import httpx
from PySide6.QtWidgets import QApplication, QMessageBox

from micv_desktop.letter_editor import LetterEditor
from micv_desktop.storage import Store


class LetterEditorTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.app = QApplication.instance() or QApplication([])

    def setUp(self):
        tmp = tempfile.TemporaryDirectory()
        self.addCleanup(tmp.cleanup)
        self.root = Path(tmp.name)
        self.store = Store(self.root)

    def make_editor(self, engine=None):
        editor = LetterEditor(engine, self.store)
        self.addCleanup(editor.close_session)
        return editor

    def test_server_outage_keeps_editor_and_drafts_available(self):
        class Engine:
            origin = 'https://fixture.test'
            connected = True

            def configuration(self):
                pass

            def request_headers(self):
                return {}

        def unavailable(request):
            raise httpx.ConnectError('Server down', request=request)

        client = httpx.Client(base_url=Engine.origin, transport=httpx.MockTransport(unavailable))
        with patch('micv_desktop.letter_editor.httpx.Client', return_value=client):
            editor = self.make_editor(Engine())
        self.assertIsNone(editor.http)
        self.assertIn('abrir un borrador', editor.status.text())
        editor.editor.setPlainText('Carta escrita durante el apagón')
        with patch('micv_desktop.letter_editor.QInputDialog.getText', return_value=('Carta local', True)):
            editor.save_draft()
        editor.new_letter()
        with patch('micv_desktop.letter_editor.QInputDialog.getItem', return_value=('1. Carta local', True)):
            editor.open_draft()
        self.assertEqual(editor.editor.toPlainText(), 'Carta escrita durante el apagón')

    def test_draft_save_failure_leaves_original_content_editable(self):
        editor = self.make_editor()
        editor.editor.insertPlainText('Texto que se debe conservar')
        with patch('micv_desktop.letter_editor.QInputDialog.getText', return_value=('Carta', True)), \
             patch.object(self.store, 'set_meta', side_effect=sqlite3.OperationalError('disk full')):
            editor.save_draft()
        self.assertIsNone(editor.draft_id)
        self.assertTrue(editor.editor.document().isModified())
        self.assertIn('La carta sigue abierta', editor.status.text())
        self.assertEqual(editor.editor.toPlainText(), 'Texto que se debe conservar')

    def test_pdf_extension_does_not_overwrite_another_file_without_confirmation(self):
        editor = self.make_editor()
        editor.editor.setPlainText('Texto de prueba')
        target = self.root/'Carta.docx.pdf'
        target.write_bytes(b'PDF anterior')
        with patch('micv_desktop.letter_editor.QFileDialog.getSaveFileName',
                   return_value=(str(self.root/'Carta.docx'), 'PDF')), \
             patch('micv_desktop.letter_editor.QMessageBox.question', return_value=QMessageBox.StandardButton.No) as ask:
            editor.export_pdf()
        ask.assert_called_once()
        self.assertEqual(target.read_bytes(), b'PDF anterior')
