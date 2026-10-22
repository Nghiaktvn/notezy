import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { XMarkIcon } from '@heroicons/react/24/outline';

export default function AiTeaser() {
  const [open, setOpen] = useState(false);
  return <aside className="notezy-ai-teaser" aria-label="Giới thiệu trợ lý AI Notezy">
    {open && <div id="notezy-ai-teaser-panel" className="notezy-ai-teaser__panel"><button type="button" onClick={() => setOpen(false)} aria-label="Đóng giới thiệu trợ lý AI"><XMarkIcon aria-hidden="true" /></button><span className="notezy-eyebrow">TRỢ LÝ NOTEZY</span><h2>Học cùng một người bạn nhỏ.</h2><p>Hỏi đáp, tóm tắt và phát triển ý tưởng từ ghi chú của bạn khi đã đăng nhập.</p><Link to="/login">Đăng nhập để trò chuyện →</Link></div>}
    <button type="button" className="notezy-ai-teaser__fab" onClick={() => setOpen(value => !value)} aria-expanded={open} aria-controls="notezy-ai-teaser-panel" aria-label={open ? 'Đóng giới thiệu trợ lý AI' : 'Khám phá trợ lý AI'}><img src="/assets/notezy-mascot-2026.png" width="1302" height="1208" alt="" /><span>Trợ lý AI</span></button>
  </aside>;
}
