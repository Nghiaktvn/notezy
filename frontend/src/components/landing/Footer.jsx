import React from 'react';
import { Link } from 'react-router-dom';
import NotezyLogo from './NotezyLogo.jsx';

const columns = [
  {
    title: 'Sản phẩm',
    links: [
      { label: 'Tính năng', href: '#features' },
      { label: 'AI Assistant', href: '#ai' },
      { label: 'Bảng giá', href: '#pricing' },
    ],
  },
  {
    title: 'Tài khoản',
    links: [
      { label: 'Đăng nhập', to: '/login' },
      { label: 'Đăng ký', to: '/register' },
    ],
  },
];

export default function Footer() {
  return (
    <footer className="border-t border-black/5 bg-white dark:border-white/10 dark:bg-black">
      <div className="mx-auto max-w-6xl px-5 py-12 sm:px-8">
        <div className="flex flex-col justify-between gap-10 sm:flex-row">
          <div className="max-w-xs">
            <div className="flex items-center gap-2.5">
              <NotezyLogo className="h-7 w-7" />
              <span className="font-[var(--font-display)] text-base font-semibold text-gray-900 dark:text-white">
                Notezy
              </span>
            </div>
            <p className="mt-3 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
              Ghi chú thông minh hơn với AI — viết, tổ chức và tìm kiếm mọi ý tưởng của bạn.
            </p>
          </div>

          <div className="grid grid-cols-2 gap-8 sm:gap-16">
            {columns.map((col) => (
              <div key={col.title}>
                <h4 className="text-sm font-semibold text-gray-900 dark:text-white">{col.title}</h4>
                <ul className="mt-3 space-y-2">
                  {col.links.map((l) =>
                    l.to ? (
                      <li key={l.label}>
                        <Link
                          to={l.to}
                          className="text-sm text-gray-500 transition-colors hover:text-green-700 dark:text-gray-400 dark:hover:text-red-400"
                        >
                          {l.label}
                        </Link>
                      </li>
                    ) : (
                      <li key={l.label}>
                        <a
                          href={l.href}
                          className="text-sm text-gray-500 transition-colors hover:text-green-700 dark:text-gray-400 dark:hover:text-red-400"
                        >
                          {l.label}
                        </a>
                      </li>
                    )
                  )}
                </ul>
              </div>
            ))}
          </div>
        </div>

        <div className="mt-10 border-t border-black/5 pt-6 text-xs text-gray-400 dark:border-white/10 dark:text-gray-500">
          © {new Date().getFullYear()} Notezy. Mọi quyền được bảo lưu.
        </div>
      </div>
    </footer>
  );
}
