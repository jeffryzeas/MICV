import os
os.environ.setdefault('QT_QPA_PLATFORM','offscreen')
import tempfile,unittest,re
from pathlib import Path
from PySide6.QtWidgets import QApplication,QDialog
from PySide6.QtCore import QPoint
from micv_desktop.forms import ClientForm,PaymentForm,LedgerForm
from micv_desktop.reports import excel_report,pdf_report
from micv_desktop.security import AccessError

class UsabilityTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):cls.app=QApplication.instance() or QApplication([])
    def setUp(self):
        from test_desktop import DesktopTests
        self.fixture=DesktopTests();self.fixture.setUp();self.addCleanup(self.fixture.doCleanups)
    def test_save_footer_remains_visible_on_small_dialogs(self):
        for dialog in [ClientForm(self.fixture.store),PaymentForm(self.fixture.store,'10'),LedgerForm()]:
            dialog.show();dialog.resize(640,380);self.app.processEvents()
            pos=dialog.save_button.mapTo(dialog,QPoint(0,0));self.assertGreaterEqual(pos.y(),0);self.assertLessEqual(pos.y()+dialog.save_button.height(),dialog.height());self.assertTrue(dialog.save_button.isVisible());dialog.close()
    def test_client_error_preserves_fields_and_success_saves_once(self):
        data={'pais':'CR','tipo_documento':'dimex','numero_documento':'12345678901','primer_nombre':'Ana','primer_apellido':'Prueba','nacionalidad':'Nicaragüense','pais_residencia':'CR'}
        def fail(data):raise AccessError('Conflicto de prueba')
        d=ClientForm(self.fixture.store,{'data':data},on_save=fail);d.accept();self.assertEqual(d.result(),QDialog.Rejected);self.assertEqual(d.data()['primer_nombre'],'Ana');self.assertIn('Conflicto',d.error.text())
        saved=[];d.on_save=saved.append;d.accept();self.assertEqual(d.result(),QDialog.Accepted);self.assertEqual(len(saved),1)
    def test_payment_error_keeps_payment_and_validates_change(self):
        def fail(data):raise AccessError('Caja cerrada')
        d=PaymentForm(self.fixture.store,'10',on_save=fail);d.accept();self.assertEqual(d.result(),QDialog.Rejected);self.assertEqual(len(d.payments),1);self.assertIn('Caja',d.error.text())
        d.payments=[{'metodo':'tarjeta','monto':'12.00'}];d.accept();self.assertIn('efectivo',d.error.text())
    def test_ledger_rejects_empty_then_preserves_data_on_failure(self):
        d=LedgerForm();d.accept();self.assertEqual(d.result(),QDialog.Rejected)
        d.inputs['categoria'].setText('Servicios');d.inputs['concepto'].setText('Prueba');d.amount.setValue(10)
        def fail(data):raise AccessError('Sin permiso')
        d.on_save=fail;d.accept();self.assertEqual(d.inputs['concepto'].text(),'Prueba');self.assertIn('permiso',d.error.text())
    def test_excel_keeps_numbers_documents_and_formula_like_text(self):
        from openpyxl import load_workbook
        file=Path(self.fixture.tmp.name)/'report.xlsx';excel_report(file,'Clientes','Negocio',['Documento','Nombre','Total'],[['001234','=SUM(1,2)','12.50']]);book=load_workbook(file);sheet=book.active
        self.assertEqual(sheet['A5'].value,'001234');self.assertEqual(sheet['B5'].data_type,'s');self.assertEqual(sheet['C5'].value,12.5);self.assertEqual(sheet.freeze_panes,'A5');self.assertEqual(sheet.print_title_rows,'$1:$4');book.close()
    def test_pdf_supports_multiple_pages_and_orientation(self):
        for direction in ['portrait','landscape']:
            file=Path(self.fixture.tmp.name)/(direction+'.pdf');pdf_report(file,'Reporte','Comercio de prueba',['Nombre','Concepto','Total'],[['José García','Descripción larga de prueba '*5,'12.50']]*70,direction)
            raw=file.read_bytes();self.assertTrue(raw.startswith(b'%PDF'));self.assertGreater(len(re.findall(rb'/Type\s*/Page\b',raw)),1)
            box=re.search(rb'/MediaBox\s*\[\s*0\s+0\s+([\d.]+)\s+([\d.]+)',raw);self.assertIsNotNone(box);self.assertEqual(float(box[1])>float(box[2]),direction=='landscape')
