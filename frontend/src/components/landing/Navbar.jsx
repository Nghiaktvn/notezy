import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Bars3Icon, XMarkIcon } from '@heroicons/react/24/outline';
import NotezyLogo from './NotezyLogo.jsx';
import ThemeToggle from '../ThemeToggle.jsx';

const links = [
  { href: '#features', label: 'Tính năng' },
  { href: '#ai', label: 'Trợ lý AI' },
  { href: '#security', label: 'Bảo mật 4 số' },
  { href: '#demo', label: 'Xem Demo' },
  { href: '#pricing', label: 'Bảng giá' },
];

export default function Navbar() {
  const [scrolled, setScrolled] = useState(false);
  const [open, setOpen] = useState(false);

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 8);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
    return () => window.removeEventListener('scroll', onScroll);
  }, []);

  return (
    <header className="sticky top-0 z-50 pt-3 px-4 sm:px-6 transition-all duration-300">
      <nav
        className={`mx-auto flex max-w-6xl items-center justify-between px-5 py-3 rounded-full transition-all duration-300 ${
          scrolled
            ? 'bg-white/90 backdrop-blur-xl border border-black/10 shadow-lg shadow-green-950/5 dark:bg-black/90 dark:border-red-900/40 dark:shadow-red-950/30'
            : 'bg-white/80 backdrop-blur-md border border-black/5 shadow-md shadow-black/5 dark:bg-neutral-950/80 dark:border-white/10 dark:shadow-red-950/20'
        }`}
      >
        <Link to="/" className="flex items-center gap-2.5" onClick={() => setOpen(false)}>
          <NotezyLogo className="h-8 w-8" />
          <span className="font-[var(--font-display)] text-lg font-bold tracking-tight text-gray-900 dark:text-white flex items-center gap-1.5">
            Notezy
            <span className="text-[10px] uppercase tracking-widest px-1.5 py-0.5 rounded-full font-bold bg-green-100 text-green-700 dark:bg-red-950 dark:text-red-400 border border-green-200 dark:border-red-900/60">
              AI
            </span>
          </span>
        </Link>

        <div className="hidden items-center gap-7 lg:flex">
          {links.map((l) => (
            <a
              key={l.href}
              href={l.href}
              className="text-xs font-semibold tracking-wide text-gray-600 transition-colors hover:text-green-700 dark:text-gray-300 dark:hover:text-red-400"
            >
              {l.label}
            </a>
          ))}
        </div>

        <div className="hidden items-center gap-3 sm:flex">
          <ThemeToggle />
          <Link
            to="/login"
            className="rounded-full px-4 py-2 text-xs font-bold text-gray-700 transition-all hover:bg-gray-100 hover:text-green-700 dark:text-gray-200 dark:hover:bg-neutral-900 dark:hover:text-red-400"
          >
            Đăng nhập
          </Link>
          <Link
            to="/register"
            className="rounded-full bg-green-600 px-5 py-2 text-xs font-bold text-white shadow-md shadow-green-600/25 transition-all hover:bg-green-700 hover:scale-105 active:scale-95 dark:bg-red-600 dark:shadow-red-600/30 dark:hover:bg-red-500"
          >
            Bắt đầu miễn phí
          </Link>
        </div>

        <div className="flex items-center gap-2 md:hidden">
          <ThemeToggle />
          <button
            type="button"
            onClick={() => setOpen((v) => !v)}
            aria-label={open ? 'Đóng menu' : 'Mở menu'}
            aria-expanded={open}
            className="inline-flex h-9 w-9 items-center justify-center rounded-full text-gray-700 dark:text-gray-200"
          >
            {open ? <XMarkIcon className="h-6 w-6" /> : <Bars3Icon className="h-6 w-6" />}
          </button>
        </div>
      </nav>

      {open && (
        <div className="border-t border-black/5 bg-white px-5 pb-5 pt-2 md:hidden dark:border-white/10 dark:bg-black">
          <div className="flex flex-col gap-1">
            {links.map((l) => (
              <a
                key={l.href}
                href={l.href}
                onClick={() => setOpen(false)}
                className="rounded-lg px-2 py-2.5 text-sm font-medium text-gray-700 hover:bg-green-50 dark:text-gray-200 dark:hover:bg-red-950"
              >
                {l.label}
              </a>
            ))}
            <div className="mt-2 flex flex-col gap-2 border-t border-black/5 pt-3 dark:border-white/10">
              <Link
                to="/login"
                onClick={() => setOpen(false)}
                className="rounded-lg px-2 py-2.5 text-center text-sm font-medium text-gray-700 dark:text-gray-200"
              >
                Đăng nhập
              </Link>
              <Link
                to="/register"
                onClick={() => setOpen(false)}
                className="rounded-full bg-green-600 px-4 py-2.5 text-center text-sm font-semibold text-white dark:bg-red-600"
              >
                Bắt đầu miễn phí
              </Link>
            </div>
          </div>
        </div>
      )}
    </header>
  );
}
