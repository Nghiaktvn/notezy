"""Local-only random OTP delivery checks. Uses the isolated SMTP sink, never real mailboxes."""
import http.cookiejar
import urllib.request
import urllib.error
import urllib.parse
import json
import email
import email.policy
import re
import time
import uuid
import os
from pathlib import Path

BASE = os.environ.get("NOTEZY_TEST_BASE", "http://127.0.0.1:18080")
SINK = "http://127.0.0.1:18025"
results = []
def check(name, ok):
    results.append(dict(name=name, passed=bool(ok)))
    print(("PASS " if ok else "FAIL ")+name, flush=True)
    if not ok: raise AssertionError(name)
def client():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def req(c, path, data=None, form=False):
    headers = {}
    payload = None
    if data is not None:
        headers["Content-Type"] = "application/x-www-form-urlencoded" if form else "application/json"
        payload = (urllib.parse.urlencode(data) if form else json.dumps(data)).encode()
    try: response = c.open(urllib.request.Request(BASE+path,data=payload,headers=headers),timeout=35)
    except urllib.error.HTTPError as e: response=e
    req.last_url = response.geturl()
    text=response.read().decode("utf-8","replace")
    try: text=json.loads(text)
    except ValueError: pass
    return response.code,text
def messages(address):
    with urllib.request.urlopen(SINK,timeout=5) as r: all_mail=json.loads(r.read())
    return [m for m in all_mail if m["to"]==address]
def code(address):
    raw=messages(address)[-1]["data"]
    message=email.message_from_string(raw,policy=email.policy.default)
    body=" ".join(p.get_content() for p in message.walk() if p.get_content_type()=="text/plain")
    return re.search(r"(?<![0-9])([0-9]{6})(?![0-9])",body).group(1)

def run():
    c=client()
    suffix=uuid.uuid4().hex[:10]
    username="otp_real_"+suffix
    address=username+"@example.com"
    password="SyntheticOtp1357!"
    status,registered=req(c,"/api/register.php",dict(username=username,display_name="OTP test",email=address,password=password))
    check("Random OTP registration reports successful delivery",status==200 and registered.get("delivery_status")=="sent")
    first=code(address)
    check("OTP is six random digits and not demo 123456",first.isdigit() and len(first)==6 and first!="123456")
    check("OTP and password are absent from registration response",first not in json.dumps(registered) and password not in json.dumps(registered) and "demo_otp" not in registered)
    check("Email contains no link that bypasses OTP","token=" not in messages(address)[-1]["data"])
    check("Pending account cannot access notes",req(c,"/api/notes.php")[0]==401)
    check("Pending account cannot log in",req(c,"/api/login.php",dict(username=username,password=password))[0]==403)
    req(c,"/api/register.php",dict(username=username,display_name="Repeated",email=address,password="DifferentPassword999"))
    check("Repeated signup does not send another email or replace OTP",len(messages(address))==1)
    check("Repeated signup does not replace original password",req(c,"/api/login.php",dict(username=username,password=password))[0]==403)
    wrong=req(c,"/api/activate.php",dict(email=address,otp="000000"))
    check("Wrong code rejected and resend required",wrong[0]==422 and wrong[1].get("requires_resend"))
    check("Original code invalidated after a wrong attempt",req(c,"/api/activate.php",dict(email=address,otp=first))[0]==422)
    check("Wrong code never activates the account",req(c,"/api/login.php",dict(username=username,password=password))[0]==403)
    limited=req(c,"/api/activate.php",dict(action="resend",email=address))
    check("Resend cooldown enforced across requests",limited[0]==429 and limited[1].get("retry_after",0)>0)
    wait=limited[1].get("retry_after",60)
    print("Waiting for real resend cooldown: "+str(wait)+"s",flush=True)
    time.sleep(wait+1)
    resent=req(c,"/api/activate.php",dict(action="resend",email=address))
    check("Resend succeeds after cooldown",resent[0]==200 and resent[1].get("delivery_status")=="sent")
    second=code(address)
    check("Resend email captured",len(messages(address))==2)
    check("Resend issues a fresh code",second!=first)
    check("Correct newest OTP activates",req(c,"/api/activate.php",dict(email=address,otp=second))[1].get("status")=="success")
    check("Original password logs in after activation",req(c,"/api/login.php",dict(username=username,password=password))[1].get("status")=="success")
    check("Authenticated account opens notes",req(c,"/api/notes.php")[0]==200)
    page_client=client()
    page_user="otp_form_"+suffix
    page_email=page_user+"@example.com"
    form={"username":page_user,"display_name":"HTML registration","email":page_email,"pass":password,"pass-confirm":password,"ajax":"1"}
    page_created=req(page_client,"/register.php",form,True)
    check("HTML signup redirects to OTP verification",page_created[0]==200 and page_created[1].get("redirect","").startswith("verify_activation.php?email="))
    check("HTML signup delivers a random email OTP",len(messages(page_email))==1 and code(page_email)!="123456")
    check("HTML signup grants no session",req(page_client,"/api/notes.php")[0]==401)
    page_verified=req(page_client,"/verify_activation.php",{"action":"verify_otp","email":page_email,"otp":code(page_email)},True)
    check("HTML OTP submission redirects to activated login",page_verified[0]==200 and "index.php?auth=login&activated=1" in req.last_url)
    check("HTML activated account can log in",req(page_client,"/api/login.php",{"username":page_user,"password":password})[1].get("status")=="success")
    bad=req(client(),"/register.php",dict(ajax="1",username="x",display_name="x",email="bad",pass_=""),True)
    check("Invalid HTML/AJAX signup returns JSON validation error",bad[0]==400 and isinstance(bad[1],dict) and bad[1].get("success") is False)

try: run()
finally:
    Path(os.environ.get("NOTEZY_OTP_REPORT","/private/tmp/notezy-real-otp-local.json")).write_text(json.dumps(results,ensure_ascii=False,indent=2))
    print("TOTAL",len(results),"PASS",sum(r["passed"] for r in results),flush=True)
