import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import AuthLayout from '../components/landing/AuthLayout.jsx';
import { getAppUrl, submitAuth } from '../api/auth.js';

export default function Login() {
  const [form, setForm] = useState({ username: '', password: '' });
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const onSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);
    try {
      await submitAuth('login.php', form);
      window.location.assign(getAppUrl('index_notezy.php'));
    } catch (requestError) {
      setError(requestError.message);
      setLoading(false);
    }
  };

  return (
    <AuthLayout
      title="Chào mừng trở lại"
      subtitle="Đăng nhập để tiếp tục với ghi chú của bạn"
      footer={
        <>
          Chưa có tài khoản?{' '}
          <Link to="/register" className="notezy-text-link">
            Đăng ký miễn phí
          </Link>
        </>
      }
    >
      <form className="notezy-auth-form" onSubmit={onSubmit}>
        <Field
          label="Email hoặc tên tài khoản"
          type="text"
          value={form.username}
          onChange={(v) => setForm((f) => ({ ...f, username: v }))}
          placeholder="email hoặc tên tài khoản"
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
          <a href={getAppUrl('forgot.php')} className="notezy-text-link">
            Quên mật khẩu?
          </a>
        </div>

        {error && <p role="alert" className="text-sm text-red-600 dark:text-red-400">{error}</p>}

        <button
          type="submit"
          disabled={loading}
          className="notezy-button notezy-button--primary notezy-auth-form__submit"
        >
          {loading ? 'Đang đăng nhập…' : 'Đăng nhập'}
        </button>
      </form>
    </AuthLayout>
  );
}

function Field({ label, type, value, onChange, placeholder, required }) {
  return (
    <label className="notezy-field">
      <span>{label}</span>
      <input
        type={type}
        value={value}
        required={required}
        placeholder={placeholder}
        onChange={(e) => onChange(e.target.value)}
        className="notezy-field__input"
      />
    </label>
  );
}
