"""Summary prompt templates for Notezy AI."""
from __future__ import annotations

SUMMARY_PROMPTS = {
    "short": (
        "Tóm tắt nội dung sau thành TL;DR không quá 2 câu súc tích:\n\n"
        "Tiêu đề: {title}\n\n"
        "{content}\n\n"
        "Trả lời bắt đầu bằng: 📋 TL;DR:"
    ),
    "detailed": (
        "Tóm tắt nội dung ghi chú sau theo cấu trúc đầy đủ:\n\n"
        "Tiêu đề: {title}\n\n"
        "{content}\n\n"
        "Trả lời theo định dạng:\n"
        "📌 Nội dung chính\n- ...\n\n"
        "🔑 Điểm quan trọng\n- ...\n\n"
        "⚠️ Cần lưu ý\n- ...\n\n"
        "📋 Việc cần làm (nếu có)\n- ..."
    ),
    "study": (
        "Tóm tắt nội dung ghi chú sau theo dạng ôn tập học tập:\n\n"
        "Tiêu đề: {title}\n\n"
        "{content}\n\n"
        "Trả lời theo định dạng:\n"
        "🎓 Chủ đề: ...\n\n"
        "📖 Khái niệm chính:\n- ...\n\n"
        "🧠 Cần nhớ:\n- ...\n\n"
        "❓ Câu hỏi ôn tập:\n1. ...\n2. ...\n3. ..."
    ),
}


def get_summary_prompt(style: str, title: str, content: str) -> str:
    """Return formatted summary prompt for the given style."""
    template = SUMMARY_PROMPTS.get(style, SUMMARY_PROMPTS["short"])
    return template.format(title=title, content=content[:6000])
