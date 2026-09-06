import React from 'react';
import { UserPlusIcon, PencilIcon, FolderIcon } from '@heroicons/react/24/outline';

const steps = [
  {
    n: '01',
    icon: UserPlusIcon,
    title: 'Tạo tài khoản',
    desc: 'Đăng ký miễn phí bằng email — không cần thẻ tín dụng.',
  },
  {
    n: '02',
    icon: PencilIcon,
    title: 'Viết ghi chú đầu tiên',
    desc: 'Nhấn "Ghi chú mới" và bắt đầu viết, trình soạn thảo đã sẵn sàng.',
  },
  {
    n: '03',
    icon: FolderIcon,
    title: 'Tổ chức & tìm kiếm',
    desc: 'Gắn nhãn, ghim ghi chú quan trọng và tìm lại chỉ trong tích tắc.',
  },
];

export default function HowItWorks() {
  return (
    <section className="bg-green-50/60 py-20 sm:py-28 dark:bg-neutral-950">
      <div className="mx-auto max-w-6xl px-5 sm:px-8">
        <div className="mx-auto max-w-2xl text-center">
          <p className="text-sm font-semibold text-green-700 dark:text-red-400">Cách dùng</p>
          <h2 className="mt-2 font-[var(--font-display)] text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl dark:text-white">
            Bắt đầu trong 30 giây
          </h2>
        </div>

        <div className="relative mt-14 grid grid-cols-1 gap-8 sm:grid-cols-3">
          {steps.map((s, i) => (
            <div key={s.n} className="relative">
              {i < steps.length - 1 && (
                <div className="absolute right-[-1.25rem] top-6 hidden h-px w-8 bg-green-200 sm:block dark:bg-red-900/60" />
              )}
              <div className="flex items-center gap-3">
                <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-white text-sm font-bold text-green-700 shadow-sm ring-1 ring-green-200 dark:bg-neutral-900 dark:text-red-400 dark:ring-red-900/60">
                  {s.n}
                </span>
                <s.icon className="h-6 w-6 text-green-600 dark:text-red-500" />
              </div>
              <h3 className="mt-4 text-base font-semibold text-gray-900 dark:text-white">{s.title}</h3>
              <p className="mt-1.5 text-sm leading-relaxed text-gray-600 dark:text-gray-400">{s.desc}</p>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
