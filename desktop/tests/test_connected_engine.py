import unittest
from unittest.mock import patch
from micv_desktop.api import OfflineError, RemoteError, server_unavailable
from micv_desktop.connected_engine import ConnectedEngine
from micv_desktop.security import AccessError

class Store:
    def __init__(self, **extra):
        self.values={'profile':{'server_url':'https://micv.example.test','public_key':'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA='},'token':'a'*64,**extra}
    def meta(self,key,default=None):return self.values.get(key,default)

class ConnectedEngineTests(unittest.TestCase):
    @patch('micv_desktop.connected_engine.Service')
    def test_token_is_only_in_headers(self,service):
        engine=ConnectedEngine(Store());self.assertEqual(engine.origin,'https://micv.example.test')
        self.assertEqual(engine.request_headers(),{b'Authorization':b'Bearer '+b'a'*64,b'X-MiCV-Desktop':b'1'})
        service.return_value.claims.assert_called_with(valid=True)
    def test_rejects_plain_http_and_malformed_tokens(self):
        for value in ('bad','a'*63,'a'*64+'\r\nCookie: bad'):
            with self.assertRaises(AccessError):ConnectedEngine(Store(token=value))
        with self.assertRaises(AccessError):ConnectedEngine(Store(profile={'server_url':'http://micv.example.test','public_key':'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA='}))
    def test_revocation_and_clock_rollback_stop_session(self):
        with self.assertRaises(AccessError):ConnectedEngine(Store(blocked=True))
        with self.assertRaises(AccessError):ConnectedEngine(Store(last_clock=9999999999))

    def test_server_outages_are_distinct_from_denied_access(self):
        self.assertTrue(server_unavailable(OfflineError('No route')))
        for status in (500, 502, 503, 504):
            self.assertTrue(server_unavailable(RemoteError(status, 'Server error')))
        for status in (401, 403, 404, 422):
            self.assertFalse(server_unavailable(RemoteError(status, 'Request error')))
        self.assertFalse(server_unavailable(AccessError('Invalid signature')))
