"""Quiz generation prompt template."""
from __future__ import annotations

QUIZ_PROMPT = """\
Từ nội dung ghi chú sau, hãy tạo {count} câu hỏi trắc nghiệm 4 đáp án (A, B, C, D).

Tiêu đề note: {title}

Nội dung:
{content}

Yêu cầu:
- Câu hỏi phải bám sát nội dung note, không bịa thêm thông tin.
- Mỗi câu có đúng 1 đáp án đúng.
- Đáp án sai phải hợp lý (không quá dễ loại trừ).
- Giải thích ngắn gọn tại sao đáp án đúng.

Trả lời theo định dạng sau (không cần JSON, dùng markdown):

🎯 **Quiz: {title}**

**Câu 1:** [Câu hỏi]
A. [Đáp án A]
B. [Đáp án B]
C. [Đáp án C]
D. [Đáp án D]
✅ **Đáp án: [Chữ cái]** — [Giải thích ngắn]

[Tiếp tục cho các câu còn lại...]
"""


def get_quiz_prompt(title: str, content: str, count: int = 5) -> str:
    return QUIZ_PROMPT.format(
        title=title,
        content=content[:6000],
        count=min(count, 15),
    )
