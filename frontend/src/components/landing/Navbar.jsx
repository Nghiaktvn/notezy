import React from 'react';
import { Link } from 'react-router-dom';
import NotezyLogo from './NotezyLogo.jsx';

export default function Navbar() {
  return (
    <header className="notezy-nav-wrap">
      <nav className="notezy-nav" aria-label="Điều hướng chính">
        <Link to="/" className="notezy-nav__brand"><NotezyLogo className="h-9 w-9" /><span>Notezy <small>AI</small></span></Link>
        <div className="notezy-nav__links">
          <a href="#top" className="notezy-nav__home">Home</a>
          <a href="#contact">Contact</a>
          <a href="#about">About</a>
        </div>
        <div className="notezy-nav__actions">
          <Link to="/login" className="notezy-button notezy-button--primary notezy-nav__login">Đăng nhập</Link>
          <Link to="/register" className="notezy-button notezy-button--ghost notezy-nav__register">Đăng ký</Link>
        </div>
      </nav>
    </header>
  );
}
