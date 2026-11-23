import importlib.util
from pathlib import Path
import io
import json
import unittest
from contextlib import redirect_stdout
from types import SimpleNamespace as S
spec=importlib.util.spec_from_file_location('capturar',Path(__file__).resolve().parents[2] / 'resources/tiktok/capturar.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
class AdapterTest(unittest.TestCase):
 def test_event_public_normalized(self):
  event=S(content='Hola <script>texto</script> 😀',user=S(unique_id='autor',nickname='Nombre'),common=S(msg_id=9999999999999999999,create_time=1730000000))
  d=m.comentario(event,1234567890123456789);self.assertEqual(d['sala'],'1234567890123456789');self.assertEqual(d['id'],'9999999999999999999');self.assertEqual(d['ocurrido_ms'],1730000000000);self.assertIn('😀',d['texto'])
 def test_missing_user_is_not_invented(self):
  d=m.comentario(S(content='hola',user=None,common=None),'123456');self.assertEqual(d['autor'],'sin_identificador');self.assertTrue(d['id'])
 def test_private_restrictions_are_respected(self):
  for v in (True,1,'1','private','true'):self.assertTrue(m.restringido({'private_account':v}))
  for v in (False,0,'0','false',None):self.assertFalse(m.restringido({'private_account':v}))
 def test_ndjson_one_line_and_unicode(self):
  out=io.StringIO()
  with redirect_stdout(out):m.enviar('comentario',texto='Línea 1\nLínea 2 😀')
  self.assertEqual(len(out.getvalue().splitlines()),1);self.assertEqual(json.loads(out.getvalue())['texto'],'Línea 1\nLínea 2 😀')
if __name__=='__main__':unittest.main()
