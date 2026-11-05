"""Opt-in live Gemini flow against an isolated/demo database, no real user edits.

NOTEZY_TEST_BASE=http://127.0.0.1:18080 python3 tests/ai_live_flow.py
Creates its own activated account; changes only that account's password.
"""
import http.cookiejar
import json
import os
from pathlib import Path
import time
import urllib.error
import urllib.request
import uuid

BASE = os.environ.get('NOTEZY_TEST_BASE', 'http://127.0.0.1:18080').rstrip('/')
results = []
fixture = {}

def client():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

def req(c, path, data=None, method=None):
    payload = None if data is None else json.dumps(data).encode()
    started = time.monotonic()
    try:
        response = c.open(urllib.request.Request(BASE+path, data=payload, method=method, headers={'Content-Type':'application/json'}), timeout=70)
    except urllib.error.HTTPError as error:
        response = error
    body = response.read().decode('utf-8', 'replace')
    try: body = json.loads(body)
    except ValueError: pass
    return response.code, body

def check(name, predicate):
    ok = bool(predicate)
    results.append({'name':name, 'pass':ok})
    print(('PASS ' if ok else 'FAIL ')+name, flush=True)
    if not ok: raise AssertionError(name)

def main():
    owner, recipient = client(), client()
    suffix = uuid.uuid4().hex[:12]
    username = 'ai_live_'+suffix
    email = username+'@example.com'
    old, new = 'SyntheticAiOld2468!', 'SyntheticAiNew1357!'
    fixture['username'] = username
    check('Register dedicated test account', req(owner,'/api/register.php',dict(username=username,display_name='Kiểm thử Gemini',email=email,password=old))[1]['status']=='success')
    check('Activate test account',req(owner,'/api/activate.php',dict(email=email,otp='123456'))[1]['status']=='success')
    logged = req(owner,'/api/login.php',dict(username=username,password=old))[1]
    check('Login test account',logged['status']=='success')
    recipient_name = 'ai_recv_'+suffix
    recipient_email = recipient_name+'@example.com'
    check('Register dedicated recipient',req(recipient,'/api/register.php',dict(username=recipient_name,display_name='Người nhận thử',email=recipient_email,password=old))[1]['status']=='success')
    check('Activate dedicated recipient',req(recipient,'/api/activate.php',dict(email=recipient_email,otp='123456'))[1]['status']=='success')
    check('Login recipient',req(recipient,'/api/login.php',dict(username=recipient_name,password=old))[1]['status']=='success')
    conversation = None
    def chat(message, note=0):
        nonlocal conversation
        status, data = req(owner,'/api/ai/chat.php',dict(message=message,conversation_id=conversation,context={'current_note_id':note}))
        check('Chat responds successfully: '+message[:45],status==200 and data.get('status')=='success')
        if data.get('conversation_id'): conversation = data['conversation_id']
        if data['response']['type']!='password_change_form': check('Actual Gemini provider reported',data.get('provider')=='gemini')
        return data['response']
    title = 'Ghi chú kiểm thử '+suffix
    made = chat('Tạo ghi chú mới có tiêu đề "'+title+'" và nội dung "Học kiểm thử phần mềm vào thứ sáu."')
    check('AI creates persisted note',made['type']=='note_created')
    note = made['note_id']; fixture['note_id']=note
    check('Created note exists in database',req(owner,'/api/notes.php?note_id='+str(note))[1]['data']['title']==made['title'])
    edited = chat('Sửa ghi chú đang mở: đổi tiêu đề thành "Đã sửa '+suffix+'" và nội dung thành "Nội dung đã được sửa qua Gemini."',note)
    check('Edit requires confirmation',edited['type']=='note_preview' and edited['action']=='update_note')
    check('Edit not applied before confirmation',req(owner,'/api/notes.php?note_id='+str(note))[1]['data']['title']==made['title'])
    check('Confirm AI edit',req(owner,'/api/ai/confirm.php',dict(confirmation_token=edited['confirmation_token']))[1]['status']=='success')
    check('Edited title persisted',req(owner,'/api/notes.php?note_id='+str(note))[1]['data']['title']=='Đã sửa '+suffix)
    summary = chat('Đọc ghi chú đang mở và cho biết nội dung hiện tại trong database.',note)
    check('AI reads persisted content through authorized tools', 'Nội dung đã được sửa' in summary.get('content','') or 'Nội dung đã được sửa' in summary.get('summary',''))
    shared = chat('Chia sẻ ghi chú đang mở cho tài khoản '+recipient_name+' với quyền chỉ xem.',note)
    check('Share requires confirmation',shared['type']=='action_confirmation' and shared['action']=='share_note')
    check('No share before confirmation',req(recipient,'/api/notes.php?note_id='+str(note))[1]['status']=='error')
    check('Confirm AI share',req(owner,'/api/ai/confirm.php',dict(confirmation_token=shared['confirmation_token']))[1]['status']=='success')
    check('Recipient opens shared note',req(recipient,'/api/notes.php?note_id='+str(note))[1]['data']['note_id']==note)
    check('Viewer cannot edit',req(recipient,'/api/notes.php',dict(note_id=note,content='forbidden'),method='PUT')[1]['status']=='error')
    check('Confirmation cannot replay',req(owner,'/api/ai/confirm.php',dict(confirmation_token=shared['confirmation_token']))[0]==400)
    password = chat('Tôi muốn đổi mật khẩu')
    check('Password request opens dedicated form',password['type']=='password_change_form')
    token = password['form_token']
    check('Password endpoint rejects missing form token',req(owner,'/api/ai/change_password.php',dict(current_password=old,new_password=new,confirm_password=new))[0]==403)
    check('Wrong current password rejected',req(owner,'/api/ai/change_password.php',dict(form_token=token,current_password='wrong',new_password=new,confirm_password=new))[0]==422)
    check('Mismatched confirmation rejected',req(owner,'/api/ai/change_password.php',dict(form_token=token,current_password=old,new_password=new,confirm_password='mismatch'))[0]==422)
    check('Password saved to database',req(owner,'/api/ai/change_password.php',dict(form_token=token,current_password=old,new_password=new,confirm_password=new,user_id=1))[1]['status']=='success')
    check('Old password no longer logs in',req(client(),'/api/login.php',dict(username=username,password=old))[0]==401)
    check('New password logs in with fresh session',req(client(),'/api/login.php',dict(username=username,password=new))[1]['status']=='success')
    check('Recipient password unchanged',req(client(),'/api/login.php',dict(username=recipient_name,password=old))[1]['status']=='success')
    check('Password form token consumed',req(owner,'/api/ai/change_password.php',dict(form_token=token,current_password=new,new_password=old,confirm_password=old))[0]==403)
    history = req(owner,'/api/ai/conversations.php?id='+str(conversation))[1]
    check('Conversation messages actually retrieved', bool(history.get('messages')))
    check('Passwords absent from chat history',old not in json.dumps(history) and new not in json.dumps(history))
    deleted = chat('Xóa ghi chú đang mở.',note)
    check('Delete requires confirmation',deleted['type']=='action_confirmation' and deleted['action']=='delete_note')
    check('Note remains before delete confirmation',req(owner,'/api/notes.php?note_id='+str(note))[1]['status']=='success')
    check('Confirm AI deletion',req(owner,'/api/ai/confirm.php',dict(confirmation_token=deleted['confirmation_token']))[1]['status']=='success')
    check('Deleted note not readable',req(owner,'/api/notes.php?note_id='+str(note))[1]['status']=='error')

try:
    main()
finally:
    target = Path(os.environ.get('NOTEZY_TEST_REPORT','/private/tmp/notezy-ai-live-results.json'))
    target.write_text(json.dumps(dict(base=BASE,results=results,fixture=fixture),ensure_ascii=False,indent=2))
    print('TOTAL',len(results),'PASS',sum(r['pass'] for r in results),'FAIL',sum(not r['pass'] for r in results),flush=True)
