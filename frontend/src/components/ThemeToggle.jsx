import React from 'react';
import { SunIcon, MoonIcon } from '@heroicons/react/24/outline';
import { useTheme } from '../context/ThemeContext.jsx';

export default function ThemeToggle({ className = '', showLabel = false }) {
  const { theme, toggleTheme } = useTheme();
  const isDark = theme === 'dark';

  return (
    <button
      type="button"
      onClick={toggleTheme}
      title={isDark ? 'Chế độ Tối: Đen & Đỏ (Bấm để đổi sang Sáng: Trắng & Xanh lá)' : 'Chế độ Sáng: Trắng & Xanh lá (Bấm để đổi sang Tối: Đen & Đỏ)'}
      aria-label={isDark ? 'Chuyển sang giao diện Sáng (Trắng & Xanh lá)' : 'Chuyển sang giao diện Tối (Đen & Đỏ)'}
      aria-pressed={isDark}
      className={`group relative inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold transition-all duration-300 ${
        isDark
          ? 'border-red-600/40 bg-red-950/30 text-red-400 hover:border-red-500 hover:bg-red-900/40 hover:text-red-300 shadow-sm shadow-red-950'
          : 'border-green-600/30 bg-green-50 text-green-700 hover:border-green-500 hover:bg-green-100 hover:text-green-800 shadow-sm shadow-green-100'
      } ${className}`}
    >
      <span className="flex h-5 w-5 items-center justify-center rounded-full transition-transform group-hover:scale-110">
        {isDark ? (
          <MoonIcon className="h-4 w-4 text-red-500 fill-red-500/20" />
        ) : (
          <SunIcon className="h-4 w-4 text-green-600 fill-green-600/20" />
        )}
      </span>
      <span className="hidden sm:inline font-medium tracking-tight">
        {isDark ? 'Tối (Đen & Đỏ)' : 'Sáng (Trắng & Xanh)'}
      </span>
      <span
        className={`inline-block h-2 w-2 rounded-full animate-pulse ${
          isDark ? 'bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.8)]' : 'bg-green-500 shadow-[0_0_8px_rgba(34,197,94,0.8)]'
        }`}
      />
    </button>
  );
}

