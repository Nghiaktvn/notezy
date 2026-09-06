import React from 'react';
import { Link } from 'react-router-dom';

export default function CTASection() {
  return (
    <section className="px-5 pb-20 sm:px-8 sm:pb-28">
      <div className="mx-auto max-w-5xl overflow-hidden rounded-3xl bg-[linear-gradient(135deg,var(--color-green-600),var(--color-green-800))] px-8 py-14 text-center shadow-xl dark:bg-[linear-gradient(135deg,var(--color-red-700),black)]">
        <p className="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-medium text-white">
          Miễn phí trọn đời
        </p>
        <h2 className="mx-auto mt-4 max-w-lg font-[var(--font-display)] text-3xl font-bold tracking-tight text-white sm:text-4xl">
          Sẵn sàng bắt đầu?
        </h2>
        <p className="mx-auto mt-3 max-w-md text-sm text-white/80 sm:text-base">
          Tạo tài khoản miễn phí và viết ghi chú đầu tiên của bạn ngay hôm nay.
        </p>

        <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
          <Link
            to="/register"
            className="rounded-full bg-white px-8 py-3.5 text-sm font-bold text-green-700 shadow-xl transition-all hover:scale-105 active:scale-95 dark:text-red-700 hover:shadow-2xl"
          >
            Start using Notezy
          </Link>
          <Link
            to="/login"
            className="rounded-full border border-white/50 px-7 py-3.5 text-sm font-bold text-white transition-all hover:bg-white/10"
          >
            Đăng nhập tài khoản
          </Link>
        </div>

        <p className="mt-6 text-xs text-white/70">
          Không cần thẻ tín dụng · Riêng tư tuyệt đối · Miễn phí mãi mãi
        </p>
      </div>
    </section>
  );
}
