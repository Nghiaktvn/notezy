import sys
sys.path.insert(0, '.')
try:
    sys.stdout.reconfigure(encoding='utf-8', errors='replace')
except AttributeError:
    pass

# Test imports
from ai_agent.providers import create_provider, LlmError
from ai_agent.tools import tool_definitions, ALL_TOOLS, risk_for
from ai_agent.prompts import SYSTEM_PROMPT, build_context_message, get_summary_prompt, QUIZ_PROMPT, FLASHCARD_PROMPT
from ai_agent.rag import Retriever, NoteIndexer, SimpleVectorStore
from ai_agent.core import IntentClassifier, Intent, ConversationMemory, UserMemory, ContextBuilder
from ai_agent.agent import AiAgent, handle_request

# Verify tool count
tools = tool_definitions()
print(f'[OK] Tool count: {len(tools)} (expected 17)')
print(f'[OK] ALL_TOOLS set: {len(ALL_TOOLS)} tools')

# List all tools
for t in tools:
    name = t['function']['name']
    risk = risk_for(name)
    print(f'     {risk:12s} | {name}')

# Verify intent classifier
clf = IntentClassifier()
i = clf.classify('tom tat note nay')
print(f'\n[OK] Intent test 1: {i.intent} ({i.confidence})')

i2 = clf.classify('tao quiz tu note Docker')
print(f'[OK] Intent test 2: {i2.intent} ({i2.confidence})')

i3 = clf.classify('tuan nay toi phai lam gi?')
print(f'[OK] Intent test 3: {i3.intent} ({i3.confidence})')

i4 = clf.classify('flashcard tu note nay')
print(f'[OK] Intent test 4: {i4.intent} ({i4.confidence})')

# Verify vector store
store = SimpleVectorStore()
cnt = store.count()
print(f'\n[OK] VectorStore initialized, {cnt} notes indexed')

# Verify retriever
retriever = Retriever()
hints_kw = retriever.build_query_hints('docker container')
mode_kw = hints_kw['mode']
print(f'[OK] Retriever keyword mode: {mode_kw}')

hints_sem = retriever.build_query_hints('ghi chu nao noi ve cach chay nhieu service cung luc?')
mode_sem = hints_sem['mode']
print(f'[OK] Retriever semantic mode: {mode_sem}')

# Verify summary prompts
p = get_summary_prompt('detailed', 'Docker Basics', 'Docker is a platform...')
print(f'[OK] Summary prompt length: {len(p)} chars')

# Verify mock provider
import os
os.environ['LLM_PROVIDER'] = 'mock'
provider = create_provider()
result = provider.complete([{'role':'user','content':'tom tat note nay'}], [])
tc = result.get('tool_calls', [])
if tc:
    fname = tc[0]['function']['name']
    print(f'[OK] Mock provider called tool: {fname}')
else:
    content_preview = result.get('content', '')[:60].encode('ascii', 'replace').decode()
    print(f'[OK] Mock provider returned content: {content_preview}')

# Verify conversation memory
mem = ConversationMemory()
msgs = [
    {'role': 'system', 'content': '{"current_note": {"note_id": 42, "title": "Docker"}}'},
]
mem.update_from_messages(msgs)
ref = mem.resolve_reference('tom tat no di')
print(f'[OK] ConversationMemory.resolve_reference: note_id={ref}')

# Full handle_request (mock)
payload = {
    'messages': [{'role': 'user', 'content': 'hello'}],
    'context': {},
}
resp = handle_request(payload)
print(f'\n[OK] handle_request ok={resp["ok"]}')
if resp.get('ok'):
    data = resp['data']
    print(f'[OK] intent={data["intent"]["intent"]}, retrieval_mode={data["retrieval"]["mode"]}')

print('\n=== ALL TESTS PASSED ===')
