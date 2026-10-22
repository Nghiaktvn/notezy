import React from 'react';

export default function NotezyLogo({ className = 'h-8 w-8' }) {
  return (
    <svg viewBox="0 0 40 40" fill="none" className={className} aria-hidden="true">
      <rect width="40" height="40" rx="12" fill="#756C91" />
      <path d="M9.5 11.5c4.2-1.7 7.7-1.4 10.5.9 2.8-2.3 6.3-2.6 10.5-.9v17.2c-4.1-1.4-7.6-1.1-10.5 1.2-2.9-2.3-6.4-2.6-10.5-1.2V11.5Z" fill="#FFFCF7" stroke="#FFFCF7" strokeWidth="1.5" strokeLinejoin="round" />
      <path d="M20 12.7v16M12.8 15.5c2-.5 3.4-.3 4.7.4m-4.7 3.3c2-.5 3.4-.3 4.7.4m5-3.7c1.4-.7 2.8-.9 4.8-.4m-4.8 4.1c1.4-.7 2.8-.9 4.8-.4" stroke="#B5AAC4" strokeWidth="1.35" strokeLinecap="round" />
    </svg>
  );
}
