"""Check that pending registration cannot authenticate without email activation.

Uses a synthetic account retained in the isolated/demo database for inspection.
Run with NOTEZY_TEST_BASE like note_access_regression.py.
"""
import http.cookiejar
import unittest
import uuid

from note_access_regression import request


class RegistrationActivationRegression(unittest.TestCase):
    def test_registration_requires_activation_before_authenticated_access(self):
        import urllib.request
        pending = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        username = 'activation_' + uuid.uuid4().hex[:20]
        # Match the landing-page modal, which requests a display name and email.
        data = {'display_name': 'Synthetic activation fixture',
                'email': username + '@example.com', 'password': 'FixturePassword2468!'}
        status, body = request(pending, '/api/register.php', 'POST', data)
        self.assertEqual(status, 200)
        self.assertIn(body['status'], ['success', 'warning'])
        self.assertTrue(body['redirect'].startswith('verify_activation.php?email='))
        self.assertEqual(request(pending, '/api/notes.php')[0], 401)
        # An unactivated account can restart activation, without gaining a session.
        self.assertIn(request(pending, '/api/register.php', 'POST', data)[1]['status'], ['success', 'warning'])
        self.assertEqual(request(pending, '/api/notes.php')[0], 401)
        status, body = request(pending, '/api/login.php', 'POST', {'email': data['email'], 'password': data['password']})
        self.assertEqual(status, 403)
        self.assertEqual(body['code'], 'not_activated')
        self.assertEqual(request(pending, '/api/notes.php')[0], 401)


if __name__ == '__main__':
    unittest.main(verbosity=2)
