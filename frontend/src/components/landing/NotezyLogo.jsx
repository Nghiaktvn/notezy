import React from 'react';

/**
 * Brand mark: a folded note/leaf shape. Uses currentColor so it can be
 * recolored with Tailwind text-* utilities per light/dark theme.
 */
export default function NotezyLogo({ className = 'h-8 w-8' }) {
  return (
    <svg viewBox="0 0 32 32" fill="none" className={className} aria-hidden="true">
      <rect x="3" y="3" width="26" height="26" rx="8" className="fill-green-600 dark:fill-red-600" />
      <path
        d="M10 10.5c0-.55.45-1 1-1h7.17a1 1 0 0 1 .7.29l3.34 3.34a1 1 0 0 1 .29.7V21.5c0 .55-.45 1-1 1H11c-.55 0-1-.45-1-1z"
        className="fill-white/90"
      />
      <path d="M18.5 9.8v3.2c0 .5.4.9.9.9h3.1" className="stroke-green-600 dark:stroke-red-600" strokeWidth="1" />
      <rect x="12.3" y="16" width="7.4" height="1.3" rx="0.65" className="fill-green-600/70 dark:fill-red-600/70" />
      <rect x="12.3" y="18.6" width="5.2" height="1.3" rx="0.65" className="fill-green-600/40 dark:fill-red-600/40" />
    </svg>
  );
}
