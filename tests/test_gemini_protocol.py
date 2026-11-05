"""No network calls: validate real-provider wire format and error behavior."""
import io
import json
import unittest
import urllib.error
from unittest.mock import patch

from ai_agent.providers.gemini import GeminiProvider, LlmError, _from_gemini_response, _to_gemini_contents
from ai_agent.agent import _sanitize_tool_calls, AiAgent
from ai_agent.providers.mock import MockProvider
from ai_agent.tools import tool_definitions


class GeminiProtocolTests(unittest.TestCase):
    def test_normalized_php_call_retains_name_arguments_and_signature(self):
        part = {'functionCall': {'name': 'get_note', 'args': {'note_id': 7}}, 'thoughtSignature': 'opaque-test-signature'}
        response = _from_gemini_response({'candidates': [{'content': {'parts': [part]}}]})
        normalized = _sanitize_tool_calls(response['tool_calls'])
        _, contents = _to_gemini_contents([
            {'role': 'assistant', 'content': '', 'tool_calls': normalized},
            {'role': 'tool', 'name': 'get_note', 'content': json.dumps({'ok': True, 'note_id': 7})},
        ])
        self.assertEqual(contents[0]['parts'][0], part)
        self.assertEqual(contents[1]['parts'][0]['functionResponse']['name'], 'get_note')

    def test_legacy_normalized_calls_work_without_raw_parts(self):
        _, contents = _to_gemini_contents([{'role': 'assistant', 'tool_calls': [{'name': 'get_note', 'arguments': {'note_id': 9}}]}])
        self.assertEqual(contents[0]['parts'][0]['functionCall']['args'], {'note_id': 9})

    def test_quota_failure_never_falls_back_to_fake_mutations(self):
        error = urllib.error.HTTPError('https://example.invalid', 429, 'quota', {}, io.BytesIO(b'{}'))
        with patch.dict('os.environ', {'GEMINI_API_KEY': 'synthetic-test-key'}), patch('urllib.request.urlopen', side_effect=error):
            with self.assertRaises(LlmError) as got:
                GeminiProvider().complete([{'role': 'user', 'content': 'create note'}], [])
        self.assertEqual(got.exception.status, 429)

    def test_greeting_does_not_eagerly_call_embedding_api(self):
        with patch('ai_agent.agent.create_provider', return_value=MockProvider()), patch('ai_agent.agent._retriever.build_query_hints', side_effect=AssertionError('eager retrieval')):
            result = AiAgent().run({'messages': [{'role': 'user', 'content': 'Xin chào bạn, tôi muốn được hỗ trợ.'}]})
        self.assertEqual(result['provider'], 'mock')

    def test_share_schema_exists_but_passwords_are_not_model_tools(self):
        names = {item['function']['name'] for item in tool_definitions()}
        self.assertIn('share_note', names)
        self.assertNotIn('change_password', names)


if __name__ == '__main__':
    unittest.main(verbosity=2)
