import tempfile
import unittest
import uuid
from pathlib import Path

from micv_desktop.diagnostics import record_event, report
from micv_desktop.storage import Store


class DiagnosticsTests(unittest.TestCase):
    def test_summary_keeps_accounts_separate_and_omits_raw_messages(self):
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            store = Store(root/'first')
            other = Store(root/'second')
            workspace = str(uuid.uuid4())
            store.set_meta('workspace_active', workspace)
            logs = store.folder/'workspaces'/workspace/'storage/logs'
            logs.mkdir(parents=True)
            (logs/'laravel.log').write_text('[2026-11-11 00:00:00] production.ERROR: PRIVATE-PAYLOAD\n')
            (logs/'engine.log').write_text('[Wed Nov 11 00:00:00 2026] PHP Fatal error: PRIVATE-TOKEN\n')
            op = store.queue('sale.create', {'cliente': 'PRIVATE-CUSTOMER'})
            store.ack({'uuid': op['uuid'], 'status': 'conflict', 'message': 'PRIVATE-CONFLICT'})
            record_event(store, 'server')
            record_event(store, 'server')

            summary = report(store)
            self.assertEqual(summary.count('El servidor no respondió'), 1)
            self.assertIn('Laravel local · 1 error', summary)
            self.assertIn('Motor PHP · 1 error', summary)
            self.assertIn('1 por revisar', summary)
            self.assertIn(op['uuid'][:8], summary)
            self.assertNotIn('PRIVATE', summary)
            self.assertIn('Sin incidencias registradas.', report(other))
            self.assertIn('0 por revisar', report(other))
