"""Run only against an isolated seeded demo database via NOTEZY_TEST_BASE."""
import unittest

from note_access_regression import client, request


class PremiumOrderRegression(unittest.TestCase):
    def test_order_uses_server_price_and_never_auto_activates(self):
        buyer, _ = client('user1')
        other, _ = client('user2')
        _, before = request(buyer, '/api/premium.php')
        status, account = request(buyer, '/api/premium_orders.php')
        self.assertEqual(status, 200)
        token = account['csrf_token']
        status, rejected = request(buyer, '/api/premium_orders.php', 'POST', {
            'action': 'create', 'plan_code': 'PERSONAL', 'months': 2,
            'csrf_token': token, 'total_vnd': 0,
        })
        self.assertEqual(status, 422)
        self.assertEqual(rejected['status'], 'error')
        status, created = request(buyer, '/api/premium_orders.php', 'POST', {
            'action': 'create', 'plan_code': 'STUDENT', 'months': 1,
            'csrf_token': token, 'total_vnd': 0,
        })
        self.assertEqual(status, 201)
        self.assertEqual(created['total_vnd'], 29000)
        order_id = created['order_id']
        try:
            status, html = request(buyer, '/premium_order.php?id=' + str(order_id))
            self.assertEqual(status, 200)
            self.assertIn('Chờ thanh toán', html)
            self.assertIn('29.000 đ', html)
            self.assertNotIn('Xác nhận đã nhận tiền', html)
            self.assertEqual(request(other, '/premium_order.php?id=' + str(order_id))[0], 404)
            status, account = request(buyer, '/api/premium.php')
            self.assertEqual(status, 200)
            self.assertEqual(account['subscription'], before['subscription'])
        finally:
            request(buyer, '/api/premium_orders.php', 'POST', {
                'action': 'cancel', 'order_id': order_id, 'csrf_token': token,
            })


if __name__ == '__main__':
    unittest.main(verbosity=2)
