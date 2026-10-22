import React from 'react';
import { SunIcon, MoonIcon } from '@heroicons/react/24/outline';
import { useTheme } from '../context/useTheme.js';

export default function ThemeToggle({ className = '' }) {
  const { theme, toggleTheme } = useTheme();
  const isDark = theme === 'dark';
  return (
    <button type="button" onClick={toggleTheme} title={isDark ? 'Chuyển sang giao diện sáng' : 'Chuyển sang giao diện tối'} aria-label={isDark ? 'Chuyển sang giao diện sáng' : 'Chuyển sang giao diện tối'} aria-pressed={isDark} className={`notezy-theme-toggle ${className}`}>
      {isDark ? <MoonIcon aria-hidden="true" /> : <SunIcon aria-hidden="true" />}
      <span>{isDark ? 'Tối' : 'Sáng'}</span>
    </button>
  );
}
