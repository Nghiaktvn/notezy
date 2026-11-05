"""Owner-authorized sharing; PHP validates recipient and confirms mutations."""
def sharing_tool_definitions() -> list[dict]:
    return [{
        'type': 'function',
        'function': {
            'name': 'share_note',
            'description': 'Propose sharing one owned note with an activated Notezy account. Ask for recipient if missing. The backend requires confirmation before sharing.',
            'parameters': {
                'type': 'object',
                'properties': {
                    'note_id': {'type': 'integer', 'minimum': 1},
                    'recipient': {'type': 'string', 'description': 'Exact username or email supplied by the user'},
                    'permission': {'type': 'string', 'enum': ['read', 'write']},
                },
                'required': ['note_id', 'recipient', 'permission'],
                'additionalProperties': False,
            },
        },
    }]
