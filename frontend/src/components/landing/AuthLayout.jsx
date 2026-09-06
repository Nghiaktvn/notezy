import React from 'react';
import { Link } from 'react-router-dom';
import NotezyLogo from './NotezyLogo.jsx';
import ThemeToggle from '../ThemeToggle.jsx';

export default function AuthLayout({ title, subtitle, footer, children }) {
  return (
    <div className="flex min-h-screen flex-col bg-white transition-colors duration-300 dark:bg-black">
      <header className="flex items-center justify-between px-5 py-4 sm:px-8">
        <Link to="/" className="flex items-center gap-2.5">
          <NotezyLogo className="h-7 w-7" />
          <span className="font-[var(--font-display)] text-base font-semibold text-gray-900 dark:text-white">
            Notezy
          </span>
        </Link>
        <ThemeToggle />
      </header>

      <main className="flex flex-1 items-center justify-center px-5 py-10">
        <div className="w-full max-w-sm">
          <div className="text-center">
            <h1 className="font-[var(--font-display)] text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
              {title}
            </h1>
            {subtitle && <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">{subtitle}</p>}
          </div>

          <div className="mt-8 rounded-2xl border border-black/10 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-neutral-950">
            {children}
          </div>

          {footer && <div className="mt-6 text-center text-sm text-gray-500 dark:text-gray-400">{footer}</div>}
        </div>
      </main>
    </div>
  );
}
