"""Manual-payment simulation. Use only an isolated seeded demo database."""
import http.cookiejar
import os
import re
import unittest
import urllib.request
import uuid

from note_access_regression import client, request


class PremiumPaymentRegression(unittest.TestCase):
    def test_only_admin_confirmation_activates_once(self):
        admin_password = os.environ.get('NOTEZY_TEST_ADMIN_PASSWORD')
        if not admin_password:
            self.skipTest('Set NOTEZY_TEST_ADMIN_PASSWORD for an isolated demo administrator.')
        buyer, _ = client('user1')
        admin = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        status, login = request(admin, '/api/login.php', 'POST', {'username': 'admin', 'password': admin_password})
        self.assertEqual(status, 200)
        self.assertEqual(login['status'], 'success')
        _, before = request(buyer, '/api/premium.php')
        _, account = request(buyer, '/api/premium_orders.php')
        status, created = request(buyer, '/api/premium_orders.php', 'POST', {
            'action': 'create', 'plan_code': 'PERSONAL', 'months': 1,
            'csrf_token': account['csrf_token'],
        })
        self.assertEqual(status, 201)
        order_path = '/premium_order.php?id=' + str(created['order_id'])
        _, pending = request(buyer, '/api/premium.php')
        self.assertEqual(pending['subscription'], before['subscription'])
        status, admin_html = request(admin, order_path)
        self.assertEqual(status, 200)
        token = re.search(r'name="csrf_token" value="([a-f0-9]+)"', admin_html)
        self.assertIsNotNone(token)
        reference = 'TEST-' + uuid.uuid4().hex
        status, paid_html = request(admin, order_path, 'POST', {
            'action': 'confirm_paid', 'csrf_token': token.group(1),
            'payment_reference': reference,
        }, True)
        self.assertEqual(status, 200)
        self.assertIn('Đã thanh toán', paid_html)
        self.assertIn(reference, paid_html)
        _, after = request(buyer, '/api/premium.php')
        self.assertEqual(after['subscription']['code'], 'PERSONAL')
        status, duplicate = request(admin, order_path, 'POST', {
            'action': 'confirm_paid', 'csrf_token': token.group(1),
            'payment_reference': reference,
        }, True)
        self.assertEqual(status, 200)
        self.assertIn('không còn ở trạng thái chờ', duplicate)
        status, cannot_cancel = request(buyer, '/api/premium_orders.php', 'POST', {
            'action': 'cancel', 'order_id': created['order_id'],
            'csrf_token': account['csrf_token'],
        })
        self.assertEqual(status, 409)
        self.assertEqual(cannot_cancel['status'], 'error')


if __name__ == '__main__':
    unittest.main(verbosity=2)
