"""Login por roles, licencia solo empresarial y separación de perfiles locales."""
import os
os.environ.setdefault('QT_QPA_PLATFORM','offscreen')
import tempfile,time,unittest
from pathlib import Path
from unittest.mock import patch
from PySide6.QtWidgets import QApplication,QDialog
from PySide6.QtTest import QTest
from micv_desktop.gui import Activate,STYLE,resolve_local_account
from micv_desktop.storage import Store
from micv_desktop.api import OfflineError

class ActivationGuiTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.app=QApplication.instance() or QApplication([]);cls.app.setStyleSheet(STYLE)
    def setUp(self):
        self.tmp=tempfile.TemporaryDirectory();self.addCleanup(self.tmp.cleanup)
        self.store=Store(Path(self.tmp.name));self.dialog=Activate(self.store)
        self.dialog.inputs['user'].setText('demo');self.dialog.inputs['password'].setText('password-test')
    def wait(self):
        deadline=time.monotonic()+5
        while self.dialog.worker.isRunning() or not self.dialog.go.isEnabled():
            QTest.qWait(20);self.assertLess(time.monotonic(),deadline)
    def login_role(self,role):
        calls=[]
        class Connected:
            def __init__(self,store):pass
            def access_direct(self,*args):return {'user_id':1,'name':'Usuario de prueba','role':role,'license_required':role=='cyber','businesses':[]}
            def activate_direct(self,*args):calls.append(args)
        with patch('micv_desktop.gui.Sync',Connected):
            self.assertTrue(self.dialog.license_group.isHidden());self.dialog.activate();self.wait()
            self.assertEqual(self.dialog.license_group.isHidden(),role!='cyber')
            self.assertEqual(self.dialog.business_group.isHidden(),role!='admin')
            self.dialog.inputs['pin'].setText('123456');self.dialog.inputs['repeat'].setText('123456')
            if role=='cyber':
                with patch('micv_desktop.gui.QMessageBox.warning') as warning:self.dialog.activate();warning.assert_called_once()
                self.assertEqual(calls,[]);self.dialog.inputs['key'].setText('EMPRESA-KEY')
            self.dialog.activate();self.wait()
        self.assertEqual(len(calls),1);self.assertEqual(calls[0][2],'EMPRESA-KEY' if role=='cyber' else '')
        self.assertEqual(self.dialog.result(),QDialog.Accepted);self.assertIsNone(self.dialog.credentials)
    def test_admin_no_licencia(self):self.login_role('admin')
    def test_cliente_no_licencia(self):self.login_role('cliente')
    def test_empresa_licencia_despues_de_login(self):self.login_role('cyber')
    def test_error_login_restores_controls(self):
        class Offline:
            def __init__(self,store):pass
            def access_direct(self,*args):raise OfflineError('Sin conexión de prueba')
        with patch('micv_desktop.gui.Sync',Offline):self.dialog.activate();self.wait()
        self.assertIn('Sin conexión',self.dialog.status.text());self.assertTrue(self.dialog.go.isEnabled());self.assertFalse(self.dialog.elapsed.isActive());self.assertIsNone(self.dialog.credentials)
    def test_profiles_do_not_cross_users_or_admin_businesses(self):
        other=Store(Path(self.tmp.name)/'other');records=[(self.store,{'user_id':1,'role':'admin','tenant_id':7})]
        self.assertIs(resolve_local_account(records,{'user_id':1,'role':'admin'},7,other),self.store)
        self.assertIs(resolve_local_account(records,{'user_id':2,'role':'admin'},7,other),other)
        self.assertIs(resolve_local_account(records,{'user_id':1,'role':'admin'},8,other),other)
        self.assertIs(resolve_local_account(records,{'user_id':1,'role':'cliente'},0,other),other)
        self.store.set_meta('profile',{'server_url':'https://otro.example.test'})
        self.assertIs(resolve_local_account(records,{'user_id':1,'role':'admin'},7,other),other)
