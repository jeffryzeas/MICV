import hashlib,unittest,httpx
from micv_desktop.api import Api,RemoteError
class FileDownloadTests(unittest.TestCase):
 def item(self,raw=b'picture'):
  return {'table':'clientes','key':1,'field':'foto','size':len(raw),'sha256':hashlib.sha256(raw).hexdigest()}
 def api(self,response):return Api({'server_url':'https://example.test'},'token',transport=httpx.MockTransport(response))
 def test_download_uses_record_reference_and_validates_bytes(self):
  def response(request):
   self.assertEqual(request.url.path,'/api/desktop/v1/workspace/file');self.assertNotIn('path',request.url.params);self.assertEqual(request.url.params['key'],'1')
   return httpx.Response(200,content=b'picture')
  api=self.api(response)
  try:self.assertEqual(api.workspace_file(self.item()),b'picture')
  finally:api.close()
 def test_truncated_changed_and_oversized_content_is_rejected(self):
  for raw in (b'pic',b'changed',b'too many bytes'):
   api=self.api(lambda r:httpx.Response(200,content=raw))
   try:
    with self.assertRaises(RemoteError):api.workspace_file(self.item())
   finally:api.close()
 def test_redirect_and_revocation_are_not_followed(self):
  for code in (302,401,403,409):
   api=self.api(lambda r:httpx.Response(code,headers={'Location':'https://foreign.test'}))
   try:
    with self.assertRaises(RemoteError) as error:api.workspace_file(self.item())
    self.assertEqual(error.exception.status,code)
   finally:api.close()
 def test_size_limit_prevents_request(self):
  def response(r):self.fail('A request must not be sent')
  api=self.api(response);item=self.item();item['size']=51*1024*1024
  try:
   with self.assertRaises(RemoteError):api.workspace_file(item)
  finally:api.close()
