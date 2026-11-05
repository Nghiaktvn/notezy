"""Isolated/demo-only fixtures. NOTEZY_DEMO_OTP must be enabled.

NOTEZY_TEST_BASE defaults to localhost:18080. The admin password is supplied
through NOTEZY_TEST_ADMIN_PASSWORD; the documented local demo value is default.
Only accounts/notes created by this test may be removed.
"""
import http.cookiejar
import os
import re
import unittest
import urllib.request
import uuid
from note_access_regression import request, client


def anonymous():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


class DemoFlowRegression(unittest.TestCase):
    def test_demo_activation_login_and_sharing(self):
        owner = anonymous()
        username = 'demo_flow_' + uuid.uuid4().hex[:12]
        email = username + '@example.com'
        password = 'SyntheticDemo2468!'
        status, registered = request(owner, '/api/register.php', 'POST', dict(username=username, display_name='Kiểm thử trình diễn', email=email, password=password))
        self.assertEqual(registered.get('demo_otp'), '123456')
        self.assertEqual(request(owner, '/api/notes.php')[0], 401)
        self.assertEqual(request(owner, '/api/activate.php', 'POST', dict(email=email, otp='123455'))[1]['status'], 'error')
        self.assertEqual(request(owner, '/api/activate.php', 'POST', dict(email=email, otp='123456'))[1]['status'], 'success')
        self.assertEqual(request(owner, '/api/login.php', 'POST', dict(email=email, password=password))[1]['status'], 'success')
        recipient, _ = client('user2')
        _, created = request(owner, '/api/notes.php', 'POST', dict(title='Ghi chú chia sẻ thử', content='Nội dung kiểm thử chia sẻ'))
        note = created['note_id']
        try:
            share = request(owner, '/share_note.php', 'POST', dict(note_id=note, sharedWithUsername='user2', permission='read'), True)
            self.assertTrue(share[1]['success'])
            self.assertEqual(request(recipient, '/api/notes.php?note_id='+str(note))[1]['data']['content'], 'Nội dung kiểm thử chia sẻ')
            self.assertEqual(request(recipient, '/api/notes.php', 'PUT', dict(note_id=note, content='Không được sửa'))[1]['status'], 'error')
            self.assertTrue(request(owner, '/share_note.php', 'POST', dict(note_id=note, sharedWithUsername='user2', permission='write'), True)[1]['success'])
            self.assertEqual(request(recipient, '/api/notes.php', 'PUT', dict(note_id=note, content='Đã sửa hợp lệ'))[1]['status'], 'success')
        finally:
            request(owner, '/api/notes.php?note_id='+str(note), 'DELETE')

    def test_admin_role_and_account_management(self):
        anon = anonymous()
        self.assertEqual(request(anon, '/admin_users.php?demo_admin_login=1')[0], 401)
        request(anon, '/labels.php')
        self.assertEqual(request(anon, '/api/notes.php')[0], 401)
        regular, _ = client('user1')
        self.assertEqual(request(regular, '/admin_users.php')[0], 403)
        admin = anonymous()
        password = os.environ.get('NOTEZY_TEST_ADMIN_PASSWORD', 'AdminDemo123!')
        self.assertEqual(request(admin, '/api/login.php', 'POST', dict(username='admin', password=password))[1]['status'], 'success')
        status, page = request(admin, '/admin_users.php')
        self.assertEqual(status, 200)
        token = re.search(r'name="csrf_token" value="([a-f0-9]+)"', page).group(1)
        self.assertEqual(request(admin, '/admin_users.php?action=delete&id=0')[0], 405)
        self.assertEqual(request(admin, '/admin_users.php?action=add', 'POST', {})[0], 403)
        name = 'admin_fixture_' + uuid.uuid4().hex[:10]
        form = dict(username=name, firstname='Tài khoản', lastname='Kiểm thử', email=name+'@example.com', password='SyntheticDemo2468!', status='1', role='user', csrf_token=token)
        self.assertEqual(request(admin, '/admin_users.php?action=add', 'POST', form, True)[0], 200)
        fixture = anonymous()
        login = request(fixture, '/api/login.php', 'POST', dict(username=name, password=form['password']))[1]
        self.assertEqual(login['status'], 'success')
        uid = login['user']['id']
        self.assertEqual(request(fixture, '/admin_users.php')[0], 403)
        form.update(firstname='Đã cập nhật', password='')
        self.assertEqual(request(admin, '/admin_users.php?action=edit&id='+str(uid), 'POST', form, True)[0], 200)
        # Only this synthetic account is disposable; never target another user.
        status, _ = request(admin, '/admin_users.php?action=delete&id='+str(uid), 'POST', dict(csrf_token=token), True)
        self.assertEqual(status, 200)
        self.assertEqual(request(anonymous(), '/api/login.php', 'POST', dict(username=name, password='SyntheticDemo2468!'))[0], 401)


if __name__ == '__main__':
    unittest.main(verbosity=2)
