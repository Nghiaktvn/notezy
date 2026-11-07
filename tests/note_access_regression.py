"""Run against an isolated seeded demo database, never a real user's data.

NOTEZY_TEST_BASE=http://127.0.0.1:18080 python3 tests/note_access_regression.py
Uses user1/user2 (Password123!) and creates/deletes only its own random fixtures.
"""
import http.cookiejar
import json
import os
import re
import unittest
import urllib.error
import urllib.parse
import urllib.request
import uuid

BASE = os.environ.get('NOTEZY_TEST_BASE', 'http://127.0.0.1:18080').rstrip('/')

def client(username):
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    status, result = request(opener, '/api/login.php', 'POST', {'username': username, 'password': 'Password123!'})
    if status != 200 or result.get('status') != 'success':
        raise RuntimeError('Seeded isolated demo accounts are required.')
    return opener, result['user']['id']

def request(opener, path, method='GET', data=None, form=False):
    headers = {}
    if data is not None:
        headers['Content-Type'] = 'application/x-www-form-urlencoded' if form else 'application/json'
        data = (urllib.parse.urlencode(data) if form else json.dumps(data)).encode()
    try:
        response = opener.open(urllib.request.Request(BASE + path, data=data, method=method, headers=headers), timeout=25)
    except urllib.error.HTTPError as error:
        response = error
    body = response.read().decode('utf-8', 'replace')
    try:
        body = json.loads(body)
    except ValueError:
        pass
    return response.code, body

class NoteAccessRegression(unittest.TestCase):
    def setUp(self):
        self.owner, self.owner_id = client('user1')
        self.recipient, self.recipient_id = client('user2')
        self.title = 'ACCESS_REGRESSION_' + uuid.uuid4().hex
        self.secret = 'PRIVATE_FIXTURE_' + uuid.uuid4().hex
        _, made = request(self.owner, '/api/notes.php', 'POST', {'title': self.title, 'content': self.secret})
        self.note = made['note_id']

    def tearDown(self):
        request(self.owner, '/api/note_pin.php', 'POST', {'action':'verify', 'note_id':self.note, 'pin':'246810'}, True)
        request(self.owner, '/api/note_password.php', 'POST', {'action':'verify', 'note_id':self.note, 'password':'RegressionPassword'}, True)
        request(self.owner, '/api/notes.php?note_id=' + str(self.note), 'DELETE')

    def pin(self):
        status, body = request(self.owner, '/api/note_pin.php', 'POST', {'action':'set', 'note_id':self.note, 'pin':'246810','pin_confirm':'246810'}, True)
        self.assertTrue(body.get('success'), status)

    def password(self):
        status, body = request(self.owner, '/api/note_password.php', 'POST', {'action':'enable','note_id':self.note,'new_password':'RegressionPassword','confirm_password':'RegressionPassword'}, True)
        self.assertTrue(body.get('success'), status)

    def unlock_pin(self, opener):
        return request(opener, '/api/note_pin.php', 'POST', {'action':'verify','note_id':self.note,'pin':'246810'}, True)

    def unlock_password(self, opener):
        return request(opener, '/api/note_password.php', 'POST', {'action':'verify','note_id':self.note,'password':'RegressionPassword'}, True)

    def test_six_digit_pin_opens_full_detail(self):
        self.pin()
        fresh, _ = client('user1')
        locked_status, locked_html = request(fresh, '/notepass.php?id=' + str(self.note))
        self.assertEqual(locked_status, 200)
        self.assertNotIn(self.secret, locked_html)
        wrong_status, wrong = request(fresh, '/api/note_pin.php', 'POST', {'action':'verify','note_id':self.note,'pin':'000000'}, True)
        self.assertFalse(wrong['success'])
        self.assertNotIn(self.secret, str(wrong))
        self.assertTrue(self.unlock_pin(fresh)[1]['unlocked'])
        detail_status, detail_html = request(fresh, '/notepass.php?id=' + str(self.note))
        self.assertEqual(detail_status, 200)
        self.assertIn(self.title, detail_html)
        self.assertIn(self.secret, detail_html)

    def test_direct_pin_form_limits_wrong_attempts(self):
        self.pin()
        fresh, _ = client('user1')
        _, form_html = request(fresh, '/notepass.php?id=' + str(self.note))
        token = re.search(r'name="csrf_token" value="([a-f0-9]+)"', form_html)
        self.assertIsNotNone(token)
        for _ in range(8):
            status, html = request(fresh, '/notepass.php?id=' + str(self.note), 'POST', {
                'note_pin': '000000', 'csrf_token': token.group(1),
            }, True)
            self.assertEqual(status, 200)
            self.assertNotIn(self.secret, html)
        status, html = request(fresh, '/notepass.php?id=' + str(self.note), 'POST', {
            'note_pin': '246810', 'csrf_token': token.group(1),
        }, True)
        self.assertEqual(status, 429)
        self.assertIn('15 phút', html)
        self.assertNotIn(self.secret, html)

    def share(self, permission='read'):
        status, body = request(self.owner, '/api/chat_api.php', 'POST', {'action':'share_note_chat','note_id':self.note,'with_user_id':self.recipient_id,'permission':permission}, True)
        self.assertTrue(body.get('success'), status)

    def card(self):
        status, body = request(self.recipient, '/api/chat_api.php?action=get_messages&with_user_id=' + str(self.owner_id))
        self.assertEqual(status, 200)
        self.assertNotIn('password_hash', json.dumps(body))
        self.assertNotIn('pin_hash', json.dumps(body))
        return next(row for row in body['messages'] if row['note_id'] == self.note)

    def test_pin_blocks_all_routes_then_allows_verified_access(self):
        self.pin()
        fresh, _ = client('user1')
        routes = [
            ('/api/notes.php?note_id='+str(self.note), 'GET', None, False),
            ('/index_notezy.php', 'GET', None, False),
            ('/search.php', 'POST', {'searchTitle':self.title}, True),
            ('/api/chat_api.php?action=get_my_notes','GET',None,False),
            ('/api/note_password.php','POST',{'action':'verify','note_id':self.note,'password':'irrelevant'},True),
            ('/api/ai/chat.php','POST',{'message':'Tóm tắt ghi chú này','context':{'current_note_id':self.note}},False),
        ]
        for path, method, data, form in routes:
            with self.subTest(path=path):
                status, body = request(fresh,path,method,data,form)
                self.assertNotEqual(status,500)
                self.assertNotIn(self.secret,json.dumps(body))
        locked_status, locked_html = request(fresh, '/notepass.php?id=' + str(self.note))
        self.assertEqual(locked_status, 200)
        self.assertNotIn(self.secret, locked_html)
        self.assertEqual(request(fresh,'/api/collab_token.php?note_id='+str(self.note))[0],423)
        self.assertTrue(self.unlock_pin(fresh)[1]['unlocked'])
        # The dashboard must open the full detail page after a valid 6-digit PIN.
        detail_status, detail_html = request(fresh, '/notepass.php?id=' + str(self.note))
        self.assertEqual(detail_status, 200)
        self.assertIn(self.secret, detail_html)
        self.assertIn(self.secret,json.dumps(request(fresh,'/api/notes.php?note_id='+str(self.note))))
        self.assertIn(self.secret,json.dumps(request(fresh,'/api/chat_api.php?action=get_my_notes')))
        self.assertIn(self.secret,json.dumps(request(fresh,'/api/ai/chat.php','POST',{'message':'Tóm tắt ghi chú này','context':{'current_note_id':self.note}})))
        self.assertEqual(request(fresh,'/api/collab_token.php?note_id='+str(self.note))[0],200)

    def test_pin_and_password_require_both_in_either_order(self):
        self.password()
        self.pin()
        first, _ = client('user1')
        self.assertNotIn(self.secret,json.dumps(self.unlock_pin(first)))
        self.assertIn(self.secret,json.dumps(self.unlock_password(first)))
        second, _ = client('user1')
        self.assertEqual(self.unlock_password(second)[0],423)
        self.assertNotIn(self.secret,json.dumps(request(second,'/api/notes.php?note_id='+str(self.note))))
        self.assertIn(self.secret,json.dumps(self.unlock_pin(second)))
        self.assertIn(self.secret,json.dumps(request(second,'/api/notes.php?note_id='+str(self.note))))

    def test_shared_locked_note_unlock_and_revocation(self):
        self.pin()
        self.share('write')
        card = self.card()
        self.assertTrue(card['is_locked'])
        self.assertIsNone(card['note_content'])
        self.assertEqual(card['can_edit'],0)
        self.unlock_pin(self.recipient)
        card = self.card()
        self.assertEqual(card['note_content'],self.secret)
        self.assertEqual(card['can_edit'],1)
        self.share('read')
        card = self.card()
        self.assertEqual(card['permission'],'read')
        self.assertEqual(card['can_edit'],0)
        status, body = request(self.owner,'/share_note.php','POST',{'action':'unshare','note_id':self.note,'target_user_id':self.recipient_id},True)
        self.assertTrue(body['success'])
        card = self.card()
        self.assertFalse(card['note_accessible'])
        self.assertIsNone(card['note_content'])
        self.assertIsNone(card['permission'])
        self.assertEqual(card['can_edit'],0)
        self.assertEqual(request(self.recipient,'/api/notes.php?note_id='+str(self.note))[1]['status'],'error')

    def test_ai_confirmation_rechecks_lock_before_deletion(self):
        _, response = request(self.owner,'/api/ai/chat.php','POST',{'message':'Xóa ghi chú này','context':{'current_note_id':self.note}})
        self.assertEqual(response['response']['type'],'action_confirmation')
        token = response['response']['confirmation_token']
        self.pin()
        status, body = request(self.owner,'/api/ai/confirm.php','POST',{'confirmation_token':token})
        self.assertEqual(status,400)
        self.assertEqual(body['status'],'error')
        self.unlock_pin(self.owner)
        self.assertIn(self.secret,json.dumps(request(self.owner,'/api/notes.php?note_id='+str(self.note))))

if __name__ == '__main__':
    unittest.main(verbosity=2)
