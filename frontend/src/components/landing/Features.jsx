import React from 'react';
import {
  PencilSquareIcon,
  TagIcon,
  StarIcon,
  MagnifyingGlassIcon,
  BellAlertIcon,
  SparklesIcon,
  LockClosedIcon,
  CalendarDaysIcon,
  ShieldCheckIcon,
} from '@heroicons/react/24/outline';

const features = [
  {
    icon: PencilSquareIcon,
    title: '📝 Quick Notes & Soạn Thảo Phong Phú',
    desc: 'Tạo ghi chú nhanh với màu nền, màu chữ, phông chữ tùy biến, ảnh đính kèm và ghim lên đầu danh sách.',
    tag: 'Cơ bản',
  },
  {
    icon: SparklesIcon,
    title: '✦ AI Intelligent Copilot',
    desc: 'Trợ lý AI tóm tắt biên bản, trích xuất việc cần làm, dịch thuật và mở rộng ý tưởng tức thì.',
    accent: true,
    tag: 'Trí tuệ nhân tạo',
  },
  {
    icon: MagnifyingGlassIcon,
    title: '🔍 Smart Search & Phân Loại',
    desc: 'Tìm kiếm siêu tốc toàn văn bản theo từ khóa, lọc theo nhãn công việc, học tập hay cá nhân.',
    tag: 'Tìm kiếm',
  },
  {
    icon: LockClosedIcon,
    title: '🔒 Bảo Mật Sổ Với Mã 4 Số (PIN)',
    desc: 'Khóa từng sổ ghi chú bằng mã 4 số bí mật. Mở sổ bằng đúng 4 số để xem nội dung và tùy chỉnh sự kiện.',
    highlight: true,
    tag: 'Bảo mật cao',
  },
  {
    icon: BellAlertIcon,
    title: '⏰ Báo Thức & Hẹn Giờ Sự Kiện',
    desc: 'Đặt chuông báo và hẹn giờ nhắc nhở chính xác cho từng sổ, không bỏ lỡ hạn nộp bài hay cuộc hẹn quan trọng.',
    tag: 'Nhắc nhở',
  },
  {
    icon: CalendarDaysIcon,
    title: '📅 Thời Khóa Biểu Thông Minh',
    desc: 'Đồng bộ lịch học, lịch họp và chuông báo thức trực quan với các tiết học và ca làm việc.',
    tag: 'Thời gian',
  },
];

export default function Features() {
  return (
    <section id="features" className="py-20 px-5 sm:px-8 bg-gray-50/50 dark:bg-neutral-950/50 transition-colors duration-300">
      <div className="mx-auto max-w-6xl">
        <div className="text-center max-w-3xl mx-auto mb-14">
          <span className="text-xs font-bold uppercase tracking-widest text-green-700 bg-green-100 px-3 py-1 rounded-full dark:text-red-400 dark:bg-red-950/60 border border-green-200 dark:border-red-900/60">
            Tính năng vượt trội
          </span>
          <h2 className="mt-4 font-[var(--font-display)] text-3xl sm:text-4xl md:text-5xl font-extrabold tracking-tight text-gray-950 dark:text-white uppercase">
            Everything you need to capture
            <br />
            <span className="bg-gradient-to-r from-green-600 to-emerald-500 bg-clip-text text-transparent dark:from-red-500 dark:to-rose-500">
              and understand ideas
            </span>
          </h2>
          <p className="mt-4 text-base text-gray-600 dark:text-gray-400">
            Tất cả công cụ bạn cần để ghi chép, tổ chức, bảo vệ sự riêng tư và biến suy nghĩ thành hành động.
          </p>
        </div>

        {/* Feature Cards Grid matching ASCII mockup */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {features.map((item, idx) => {
            const Icon = item.icon;
            return (
              <div
                key={idx}
                className={`relative rounded-3xl p-6 transition-all duration-300 hover:-translate-y-1.5 ${
                  item.highlight
                    ? 'border-2 border-amber-400/80 bg-gradient-to-b from-amber-50/40 to-white shadow-xl shadow-amber-500/10 dark:border-red-600/50 dark:from-red-950/30 dark:to-neutral-900'
                    : item.accent
                    ? 'border border-green-300/80 bg-white shadow-xl shadow-green-900/5 dark:border-red-900/40 dark:bg-neutral-900 dark:shadow-red-950/20'
                    : 'border border-black/5 bg-white shadow-md shadow-black/5 dark:border-white/10 dark:bg-neutral-900'
                }`}
              >
                <div className="flex items-center justify-between mb-4">
                  <div className={`flex h-12 w-12 items-center justify-center rounded-2xl ${
                    item.highlight
                      ? 'bg-amber-100 text-amber-800 dark:bg-red-900/50 dark:text-red-300'
                      : item.accent
                      ? 'bg-green-100 text-green-700 dark:bg-red-950 dark:text-red-400'
                      : 'bg-gray-100 text-gray-700 dark:bg-neutral-800 dark:text-gray-300'
                  }`}>
                    <Icon className="h-6 w-6" />
                  </div>
                  <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 dark:bg-neutral-800 dark:text-gray-400">
                    {item.tag}
                  </span>
                </div>

                <h3 className="font-bold text-base sm:text-lg text-gray-900 dark:text-white mb-2">
                  {item.title}
                </h3>
                <p className="text-xs sm:text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                  {item.desc}
                </p>
              </div>
            );
          })}
        </div>
      </div>
    </section>
  );
}
