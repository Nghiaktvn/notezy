import React from 'react';
import { Link } from 'react-router-dom';
import { ArrowRightIcon, PlayIcon } from '@heroicons/react/24/outline';
import AppMockup from './AppMockup.jsx';

export default function Hero() {
  return (
    <section className="relative overflow-hidden bg-white dark:bg-black transition-colors duration-300">
      {/* Background glow: Green for Light mode, Deep Red for Dark mode */}
      <div
        aria-hidden="true"
        className="pointer-events-none absolute inset-x-0 -top-32 -z-10 h-[620px] bg-[radial-gradient(ellipse_70%_60%_at_50%_0%,rgba(16,185,129,0.18),transparent)] dark:bg-[radial-gradient(ellipse_70%_60%_at_50%_0%,rgba(220,38,38,0.25),transparent)] transition-all duration-500"
      />

      <div className="mx-auto max-w-4xl px-5 pt-14 text-center sm:px-8 sm:pt-20">
        {/* Pill Badge */}
        <div className="inline-flex items-center gap-2 rounded-full border border-green-300/80 bg-green-50/80 px-3.5 py-1.5 text-xs font-semibold text-green-800 shadow-sm transition-all hover:scale-105 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
          <span className="rounded-full bg-green-600 px-2 py-0.5 text-[10px] font-bold text-white uppercase tracking-wider dark:bg-red-600">
            Mới
          </span>
          <span>Trợ lý AI tóm tắt • Bảo mật sổ mã 4 số</span>
          <span className="text-green-600 dark:text-red-400">→</span>
        </div>

        {/* Headline as requested in ASCII mockup */}
        <h1 className="mt-8 font-[var(--font-display)] text-4xl font-extrabold tracking-tight text-gray-950 sm:text-6xl md:text-7xl uppercase leading-[1.08] dark:text-white">
          YOUR THOUGHTS,
          <br />
          <span className="bg-gradient-to-r from-green-600 via-emerald-500 to-teal-600 bg-clip-text text-transparent dark:from-red-500 dark:via-rose-500 dark:to-orange-500">
            ORGANIZED & INTELLIGENT.
          </span>
        </h1>

        {/* Subtitle */}
        <p className="mx-auto mt-6 max-w-2xl text-base leading-relaxed text-gray-600 sm:text-xl dark:text-gray-300">
          Capture your ideas. Let AI organize the rest.
          <br className="hidden sm:inline" />
          {' '}Sổ ghi chép thông minh thế hệ mới, tích hợp khóa bảo mật 4 số riêng tư và trợ lý AI xử lý tức thì.
        </p>

        {/* CTA Buttons */}
        <div className="mt-9 flex flex-col items-center justify-center gap-3.5 sm:flex-row">
          <Link
            to="/register"
            className="group inline-flex w-full items-center justify-center gap-2 rounded-full bg-green-600 px-8 py-3.5 text-sm font-bold text-white shadow-xl shadow-green-600/30 transition-all hover:bg-green-700 hover:scale-105 active:scale-95 sm:w-auto dark:bg-red-600 dark:shadow-red-600/30 dark:hover:bg-red-500"
          >
            <span>Bắt đầu miễn phí</span>
            <ArrowRightIcon className="h-4 w-4 transition-transform group-hover:translate-x-1" />
          </Link>
          <a
            href="#demo"
            className="inline-flex w-full items-center justify-center gap-2 rounded-full border border-gray-300 bg-white/80 px-7 py-3.5 text-sm font-bold text-gray-800 shadow-sm transition-all hover:border-green-600 hover:text-green-700 hover:bg-green-50/50 sm:w-auto dark:border-white/15 dark:bg-neutral-900/60 dark:text-gray-200 dark:hover:border-red-500 dark:hover:text-red-400 dark:hover:bg-red-950/30"
          >
            <PlayIcon className="h-4 w-4 text-green-600 dark:text-red-500 fill-current" />
            <span>Xem demo (30s)</span>
          </a>
        </div>

        <p className="mt-4 text-xs font-medium text-gray-400 dark:text-gray-500">
          ✦ Miễn phí trải nghiệm · Không cần thẻ tín dụng · Dữ liệu mã hóa an toàn
        </p>
      </div>

      <div className="mx-auto mt-14 max-w-6xl px-4 pb-20 sm:px-8 sm:pb-28">
        <AppMockup />
      </div>
    </section>
  );
}
