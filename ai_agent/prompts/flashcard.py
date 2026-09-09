"""Flashcard generation prompt template."""
from __future__ import annotations

FLASHCARD_PROMPT = """\
Từ nội dung ghi chú sau, hãy tạo {count} cặp flashcard Q&A để học và ghi nhớ.

Tiêu đề note: {title}

Nội dung:
{content}

Yêu cầu:
- Câu hỏi phải rõ ràng, cụ thể, bám sát nội dung.
- Câu trả lời ngắn gọn, súc tích (1-3 câu).
- Đa dạng: định nghĩa, so sánh, ứng dụng, ví dụ.
- Không bịa thêm thông tin ngoài nội dung note.

Trả lời theo định dạng sau (không cần JSON, dùng markdown):

🗂️ **Flashcard: {title}**

---
**Thẻ 1**
❓ **Q:** [Câu hỏi]
💡 **A:** [Câu trả lời]

---
**Thẻ 2**
❓ **Q:** [Câu hỏi]
💡 **A:** [Câu trả lời]

[Tiếp tục cho các thẻ còn lại...]
"""


def get_flashcard_prompt(title: str, content: str, count: int = 5) -> str:
    return FLASHCARD_PROMPT.format(
        title=title,
        content=content[:6000],
        count=min(count, 15),
    )
