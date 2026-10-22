import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import AuthLayout from '../components/landing/AuthLayout.jsx';
import { getAppUrl, submitAuth } from '../api/auth.js';

export default function Register() {
  const [form, setForm] = useState({ name: '', email: '', password: '', confirmPassword: '' });
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const onSubmit = async (e) => {
    e.preventDefault();
    setError('');
    if (form.password !== form.confirmPassword) {
      setError('Hai lần nhập mật khẩu chưa khớp.');
      return;
    }
    setLoading(true);
    const [firstname = '', ...lastNameParts] = form.name.trim().split(/\s+/);
    try {
      const result = await submitAuth('register.php', {
        firstname,
        lastname: lastNameParts.join(' '),
        display_name: form.name.trim(),
        email: form.email.trim(),
        password: form.password,
      });
      if (result.status === 'warning') {
        const email = encodeURIComponent(form.email.trim());
        window.location.assign(getAppUrl(`verify_activation.php?email=${email}`));
        return;
      }
      window.location.assign(getAppUrl(result.redirect || 'index_notezy.php?unverified=1'));
    } catch (requestError) {
      setError(requestError.message);
      setLoading(false);
    }
  };

  return (
    <AuthLayout
      title="Tạo tài khoản Notezy"
      subtitle="Miễn phí trọn đời, không cần thẻ tín dụng"
      footer={
        <>
          Đã có tài khoản?{' '}
          <Link to="/login" className="notezy-text-link">
            Đăng nhập
          </Link>
        </>
      }
    >
      <form className="notezy-auth-form" onSubmit={onSubmit}>
        <Field
          label="Họ và tên"
          type="text"
          value={form.name}
          onChange={(v) => setForm((f) => ({ ...f, name: v }))}
          placeholder="Nguyễn Văn A"
          required
        />
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
          placeholder="Tối thiểu 6 ký tự"
          required
        />
        <Field
          label="Nhập lại mật khẩu"
          type="password"
          value={form.confirmPassword}
          onChange={(v) => setForm((f) => ({ ...f, confirmPassword: v }))}
          placeholder="Xác nhận mật khẩu"
          required
        />

        {error && <p role="alert" className="text-sm text-red-600 dark:text-red-400">{error}</p>}
        <button
          type="submit"
          disabled={loading}
          className="notezy-button notezy-button--primary notezy-auth-form__submit"
        >
          {loading ? 'Đang tạo tài khoản…' : 'Tạo tài khoản miễn phí'}
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
