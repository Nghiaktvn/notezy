import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import AuthLayout from '../components/landing/AuthLayout.jsx';

export default function Login() {
  const [form, setForm] = useState({ email: '', password: '' });

  const onSubmit = (e) => {
    e.preventDefault();
    // Wire this up to your auth endpoint (e.g. /login.php or an API route)
    // once the backend integration for this React app is ready.
    console.log('login submit', form);
  };

  return (
    <AuthLayout
      title="Chào mừng trở lại"
      subtitle="Đăng nhập để tiếp tục với ghi chú của bạn"
      footer={
        <>
          Chưa có tài khoản?{' '}
          <Link to="/register" className="font-medium text-green-700 hover:underline dark:text-red-400">
            Đăng ký miễn phí
          </Link>
        </>
      }
    >
      <form className="space-y-4" onSubmit={onSubmit}>
        <Field
          label="Email"
          type="email"
          value={form.email}
          onChange={(v) => setForm((f) => ({ ...f, email: v }))}
          placeholder="ban@vidu.com"
          required
        />
        <Field
          label="Mật khẩu"
          type="password"
          value={form.password}
          onChange={(v) => setForm((f) => ({ ...f, password: v }))}
          placeholder="••••••••"
          required
        />

        <div className="flex items-center justify-end">
          <a href="#" className="text-xs font-medium text-green-700 hover:underline dark:text-red-400">
            Quên mật khẩu?
          </a>
        </div>

        <button
          type="submit"
          className="w-full rounded-full bg-green-600 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-green-700 dark:bg-red-600 dark:hover:bg-red-500"
        >
          Đăng nhập
        </button>
      </form>
    </AuthLayout>
  );
}

function Field({ label, type, value, onChange, placeholder, required }) {
  return (
    <label className="block">
      <span className="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300">{label}</span>
      <input
        type={type}
        value={value}
        required={required}
        placeholder={placeholder}
        onChange={(e) => onChange(e.target.value)}
        className="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 outline-none transition-colors placeholder:text-gray-400 focus:border-green-600 focus:ring-2 focus:ring-green-100 dark:border-white/15 dark:bg-neutral-900 dark:text-white dark:placeholder:text-gray-500 dark:focus:border-red-600 dark:focus:ring-red-950"
      />
    </label>
  );
}
