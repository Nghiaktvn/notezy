import React from 'react';
import { Link } from 'react-router-dom';
import NotezyLogo from './NotezyLogo.jsx';
import ThemeToggle from '../ThemeToggle.jsx';

export default function AuthLayout({ title, subtitle, footer, children }) {
  return <div className="notezy-auth">
    <header className="notezy-auth__header"><Link to="/" className="notezy-nav__brand"><NotezyLogo className="h-9 w-9" /><span>Notezy</span></Link><ThemeToggle /></header>
    <main className="notezy-auth__main">
      <section className="notezy-auth__story" aria-labelledby="auth-story-title">
        <span className="notezy-eyebrow">NOTEZY · KHÔNG GIAN GHI CHÚ</span>
        <h1 id="auth-story-title">Ý tưởng của bạn<br /><em>xứng đáng được giữ gìn.</em></h1>
        <p>Một cuốn sổ dịu dàng cho suy nghĩ, kế hoạch và những điều bạn muốn nhớ.</p>
        <img src="/assets/notezy-notebooks-2026.png" width="1536" height="1024" alt="Những cuốn sổ tay hoạt hình Notezy" />
      </section>
      <section className="notezy-auth__form-wrap" aria-labelledby="auth-form-title">
        <img className="notezy-auth__mascot" src="/assets/notezy-mascot-2026.png" width="1302" height="1208" alt="Linh vật sổ tay Notezy" />
        <span className="notezy-eyebrow">CHÀO MỪNG BẠN</span>
        <h2 id="auth-form-title">{title}</h2>
        {subtitle && <p className="notezy-auth__subtitle">{subtitle}</p>}
        {children}
        {footer && <div className="notezy-auth__footer">{footer}</div>}
      </section>
    </main>
  </div>;
}
