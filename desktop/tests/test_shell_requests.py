import unittest
from PySide6.QtCore import QUrl
from micv_desktop.desktop_shell import LocalRequests,same_origin
class Engine:
    origin='https://micv.example.test'
    def request_headers(self):return {b'Authorization':b'Bearer private',b'X-MiCV-Desktop':b'1'}
class Info:
    def __init__(self,url):self.url=url;self.headers={};self.blocked=False
    def requestUrl(self):return QUrl(self.url)
    def setHttpHeader(self,key,value):self.headers[key]=value
    def block(self,value):self.blocked=value
class ShellRequestTests(unittest.TestCase):
    def test_default_ports_are_equivalent_but_credentials_and_other_hosts_are_not(self):
        self.assertTrue(same_origin('https://micv.example.test:443/file',Engine.origin))
        for url in ['https://evil.example.test','https://micv.example.test:444','https://user:pw@micv.example.test','http://micv.example.test','https://micv.example.test.evil.test']:
            self.assertFalse(same_origin(url,Engine.origin))
    def test_external_requests_never_receive_credentials(self):
        interceptor=LocalRequests(Engine(),None)
        for url in ['https://external.test/x','file:///etc/passwd','http://127.0.0.1:7000']:
            info=Info(url);interceptor.interceptRequest(info);self.assertTrue(info.blocked);self.assertFalse(info.headers)
        info=Info(Engine.origin+'/cv');interceptor.interceptRequest(info)
        self.assertFalse(info.blocked);self.assertEqual(info.headers,Engine().request_headers())
