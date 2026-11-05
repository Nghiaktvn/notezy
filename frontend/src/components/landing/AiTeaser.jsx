import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { XMarkIcon } from '@heroicons/react/24/outline';

export default function AiTeaser() {
  const [open, setOpen] = useState(false);
  return <aside className="notezy-ai-teaser" aria-label="Giới thiệu trợ lý thông minh Notezy">
    {open && <div id="notezy-ai-teaser-panel" className="notezy-ai-teaser__panel"><button type="button" onClick={() => setOpen(false)} aria-label="Đóng giới thiệu trợ lý thông minh"><XMarkIcon aria-hidden="true" /></button><span className="notezy-eyebrow">TRỢ LÝ NOTEZY</span><h2>Học cùng một người bạn nhỏ.</h2><p>Hỏi đáp, tóm tắt và phát triển ý tưởng từ ghi chú của bạn khi đã đăng nhập.</p><Link to="/login">Đăng nhập để trò chuyện →</Link></div>}
    <button type="button" className="notezy-ai-teaser__fab" onClick={() => setOpen(value => !value)} aria-expanded={open} aria-controls="notezy-ai-teaser-panel" aria-label={open ? 'Đóng giới thiệu trợ lý thông minh' : 'Khám phá trợ lý thông minh'}><img src="/assets/notezy-mascot-2026.png" width="1302" height="1208" alt="" /><span>Trợ lý thông minh</span></button>
  </aside>;
}
