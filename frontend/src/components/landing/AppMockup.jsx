import React, { useState } from 'react';
import {
  DocumentTextIcon,
  StarIcon,
  TrashIcon,
  PlusIcon,
  MagnifyingGlassIcon,
  SparklesIcon,
  LockClosedIcon,
  LockOpenIcon,
  BellIcon,
  CheckCircleIcon,
  TagIcon,
} from '@heroicons/react/24/outline';

export default function AppMockup() {
  const [activeTab, setActiveTab] = useState('ai'); // 'ai' | 'search' | 'notes'
  const [isPinUnlocked, setIsPinUnlocked] = useState(false);
  const [searchQuery, setSearchQuery] = useState('dự án');

  return (
    <div className="relative mx-auto w-full max-w-4xl">
      {/* Tab switcher buttons matching user ASCII mockup */}
      <div className="mb-4 flex flex-wrap items-center justify-center gap-2">
        <button
          type="button"
          onClick={() => setActiveTab('ai')}
          className={`flex items-center gap-2 rounded-full px-4 py-2 text-xs font-bold transition-all ${
            activeTab === 'ai'
              ? 'bg-green-600 text-white shadow-md shadow-green-600/30 scale-105 dark:bg-red-600 dark:shadow-red-600/30'
              : 'bg-white/80 text-gray-700 hover:bg-gray-100 border border-black/5 dark:bg-neutral-900/80 dark:text-gray-300 dark:border-white/10 dark:hover:bg-neutral-800'
          }`}
        >
          <SparklesIcon className="h-4 w-4" />
          <span>✦ AI summarizes</span>
        </button>

        <button
          type="button"
          onClick={() => setActiveTab('search')}
          className={`flex items-center gap-2 rounded-full px-4 py-2 text-xs font-bold transition-all ${
            activeTab === 'search'
              ? 'bg-green-600 text-white shadow-md shadow-green-600/30 scale-105 dark:bg-red-600 dark:shadow-red-600/30'
              : 'bg-white/80 text-gray-700 hover:bg-gray-100 border border-black/5 dark:bg-neutral-900/80 dark:text-gray-300 dark:border-white/10 dark:hover:bg-neutral-800'
          }`}
        >
          <MagnifyingGlassIcon className="h-4 w-4" />
          <span>🔍 Smart search</span>
        </button>

        <button
          type="button"
          onClick={() => setActiveTab('notes')}
          className={`flex items-center gap-2 rounded-full px-4 py-2 text-xs font-bold transition-all ${
            activeTab === 'notes'
              ? 'bg-green-600 text-white shadow-md shadow-green-600/30 scale-105 dark:bg-red-600 dark:shadow-red-600/30'
              : 'bg-white/80 text-gray-700 hover:bg-gray-100 border border-black/5 dark:bg-neutral-900/80 dark:text-gray-300 dark:border-white/10 dark:hover:bg-neutral-800'
          }`}
        >
          <DocumentTextIcon className="h-4 w-4" />
          <span>📝 Quick notes & Khóa 4 số</span>
        </button>
      </div>

      {/* Floating badges for visual depth */}
      <div className="absolute -left-6 top-16 hidden w-44 rotate-[-4deg] rounded-2xl border border-black/10 bg-white/95 p-3.5 shadow-xl backdrop-blur-md transition-transform hover:rotate-0 md:block dark:border-white/10 dark:bg-neutral-900/95">
        <div className="flex items-center gap-2 text-[11px] font-bold text-green-700 dark:text-red-400">
          <BellIcon className="h-3.5 w-3.5" />
          <span>Sự kiện: 09:30 AM</span>
        </div>
        <p className="mt-1 text-xs font-semibold text-gray-800 dark:text-gray-200">
          Báo thức nộp đồ án
        </p>
        <span className="mt-2 inline-block rounded-md bg-green-100 px-1.5 py-0.5 text-[9px] font-bold text-green-800 dark:bg-red-950 dark:text-red-300">
          Đã ghim
        </span>
      </div>

      <div className="absolute -right-6 bottom-10 hidden w-48 rotate-[4deg] rounded-2xl border border-black/10 bg-white/95 p-3.5 shadow-xl backdrop-blur-md transition-transform hover:rotate-0 md:block dark:border-white/10 dark:bg-neutral-900/95">
        <div className="flex items-center gap-1.5 text-[11px] font-bold text-amber-600 dark:text-amber-400">
          <LockClosedIcon className="h-3.5 w-3.5" />
          <span>Bảo mật 4 số: [2026]</span>
        </div>
        <p className="mt-1 text-xs font-semibold text-gray-800 dark:text-gray-200">
          Sổ nhật ký bí mật
        </p>
        <span className="mt-2 inline-block rounded-md bg-amber-100 px-1.5 py-0.5 text-[9px] font-bold text-amber-800 dark:bg-amber-950 dark:text-amber-300">
          Chỉ mở khi nhập PIN
        </span>
      </div>

      {/* Main app window */}
      <div className="relative overflow-hidden rounded-3xl border border-black/10 bg-white shadow-2xl shadow-green-950/10 transition-all duration-300 dark:border-red-900/30 dark:bg-neutral-950 dark:shadow-red-950/40">
        {/* Title bar with macOS buttons */}
        <div className="flex items-center justify-between border-b border-black/5 bg-gray-50/90 px-4 py-3 dark:border-white/10 dark:bg-neutral-900/90">
          <div className="flex items-center gap-2">
            <span className="h-3 w-3 rounded-full bg-red-500 shadow-sm" />
            <span className="h-3 w-3 rounded-full bg-amber-400 shadow-sm" />
            <span className="h-3 w-3 rounded-full bg-green-500 shadow-sm" />
            <span className="ml-3 text-xs font-semibold text-gray-500 dark:text-gray-400">
              Notezy Intelligent Workspace
            </span>
          </div>
          <div className="flex items-center gap-2 text-[11px] text-gray-400">
            <span className="inline-block h-2 w-2 rounded-full bg-green-500 animate-ping" />
            <span>AI Online</span>
          </div>
        </div>

        {/* Dynamic content based on Active Tab */}
        <div className="p-4 sm:p-6 text-left">
          {activeTab === 'ai' && (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="rounded-2xl border border-black/5 bg-gray-50 p-4 dark:border-white/10 dark:bg-neutral-900">
                <div className="flex items-center justify-between mb-2">
                  <span className="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    📝 Biên bản cuộc họp thô (Raw Note)
                  </span>
                  <span className="text-[10px] text-gray-400">Hôm nay 10:00</span>
                </div>
                <h4 className="font-bold text-sm text-gray-900 dark:text-white mb-2">
                  Kế hoạch ra mắt sản phẩm Notezy 2026
                </h4>
                <p className="text-xs leading-relaxed text-gray-600 dark:text-gray-300">
                  Cuộc họp thảo luận về tính năng bảo mật sổ ghi chú bằng mã 4 số, tích hợp trợ lý AI tóm tắt văn bản, và thiết kế 2 chế độ màu Sáng (Trắng + Xanh) và Tối (Đen + Đỏ). Nhóm kỹ thuật cần hoàn thành tích hợp trong tuần này...
                </p>
              </div>

              <div className="rounded-2xl border border-green-300/80 bg-green-50/50 p-4 dark:border-red-900/60 dark:bg-red-950/30">
                <div className="flex items-center justify-between mb-2 text-green-700 dark:text-red-400">
                  <div className="flex items-center gap-1.5 text-xs font-bold">
                    <SparklesIcon className="h-4 w-4" />
                    <span>✦ AI Tóm Tắt & Hành Động Cụ Thể</span>
                  </div>
                  <span className="text-[10px] font-semibold bg-green-200/80 text-green-800 px-2 py-0.5 rounded-full dark:bg-red-900/60 dark:text-red-200">
                    Xử lý trong 0.4s
                  </span>
                </div>
                <div className="space-y-2 mt-3">
                  <div className="flex items-start gap-2 text-xs font-medium text-gray-800 dark:text-gray-200">
                    <CheckCircleIcon className="h-4 w-4 text-green-600 dark:text-red-500 shrink-0 mt-0.5" />
                    <span><strong>Bảo mật 4 số:</strong> Khóa sổ an toàn, nhập mã 4 số mở nội dung và sự kiện.</span>
                  </div>
                  <div className="flex items-start gap-2 text-xs font-medium text-gray-800 dark:text-gray-200">
                    <CheckCircleIcon className="h-4 w-4 text-green-600 dark:text-red-500 shrink-0 mt-0.5" />
                    <span><strong>Giao diện 2 chế độ:</strong> Sáng (Trắng + Xanh lá) & Tối (Đen + Đỏ).</span>
                  </div>
                  <div className="flex items-start gap-2 text-xs font-medium text-gray-800 dark:text-gray-200">
                    <CheckCircleIcon className="h-4 w-4 text-green-600 dark:text-red-500 shrink-0 mt-0.5" />
                    <span><strong>Trợ lý AI:</strong> Hỗ trợ tóm tắt, trích xuất việc cần làm và phân tích ý tưởng.</span>
                  </div>
                </div>
              </div>
            </div>
          )}

          {activeTab === 'search' && (
            <div>
              <div className="flex items-center gap-3 mb-4 rounded-xl border border-black/10 bg-gray-50 px-3.5 py-2.5 dark:border-white/10 dark:bg-neutral-900">
                <MagnifyingGlassIcon className="h-4 w-4 text-green-600 dark:text-red-500" />
                <input
                  type="text"
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  placeholder="Nhập từ khóa tìm kiếm nhanh..."
                  className="bg-transparent text-xs sm:text-sm font-medium w-full focus:outline-none text-gray-900 dark:text-white"
                />
                <span className="text-[10px] text-gray-400 bg-gray-200 dark:bg-neutral-800 px-2 py-0.5 rounded">
                  Tìm kiếm tức thì
                </span>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div className="rounded-xl border border-green-300 bg-green-50/40 p-3.5 dark:border-red-900/50 dark:bg-neutral-900">
                  <div className="flex items-center justify-between mb-1">
                    <h5 className="text-xs font-bold text-gray-900 dark:text-white">
                      Kế hoạch <span className="bg-yellow-200 text-yellow-900 px-1 rounded">dự án</span> Notezy Web
                    </h5>
                    <span className="text-[10px] text-green-700 dark:text-red-400 font-bold">Khớp 100%</span>
                  </div>
                  <p className="text-xs text-gray-600 dark:text-gray-400">
                    Phát triển giao diện React kết hợp Tailwind CSS...
                  </p>
                  <div className="flex gap-1.5 mt-2">
                    <span className="text-[9px] bg-green-200/80 text-green-800 px-2 py-0.5 rounded dark:bg-red-950 dark:text-red-300">Công việc</span>
                    <span className="text-[9px] bg-gray-200 text-gray-700 px-2 py-0.5 rounded dark:bg-neutral-800 dark:text-gray-300">Dự án</span>
                  </div>
                </div>

                <div className="rounded-xl border border-black/5 bg-gray-50 p-3.5 dark:border-white/10 dark:bg-neutral-900">
                  <div className="flex items-center justify-between mb-1">
                    <h5 className="text-xs font-bold text-gray-900 dark:text-white">
                      Phân công công việc <span className="bg-yellow-200 text-yellow-900 px-1 rounded">dự án</span>
                    </h5>
                    <span className="text-[10px] text-green-700 dark:text-red-400 font-bold">Khớp 85%</span>
                  </div>
                  <p className="text-xs text-gray-600 dark:text-gray-400">
                    Hoàn thành tài liệu kiến trúc và hướng dẫn triển khai.
                  </p>
                  <div className="flex gap-1.5 mt-2">
                    <span className="text-[9px] bg-blue-100 text-blue-800 px-2 py-0.5 rounded dark:bg-blue-950 dark:text-blue-300">Đội ngũ</span>
                  </div>
                </div>
              </div>
            </div>
          )}

          {activeTab === 'notes' && (
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center">
              {/* Note locked state */}
              <div className="rounded-2xl border border-amber-300 bg-amber-50/50 p-4 text-center dark:border-red-900/60 dark:bg-neutral-900">
                <div className="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-amber-200 text-amber-800 dark:bg-red-950 dark:text-red-400">
                  {isPinUnlocked ? <LockOpenIcon className="h-5 w-5" /> : <LockClosedIcon className="h-5 w-5" />}
                </div>
                <h5 className="text-sm font-bold text-gray-900 dark:text-white">
                  {isPinUnlocked ? 'Sổ đã được mở khóa!' : 'Sổ ghi chú đã khóa bằng mã 4 số'}
                </h5>
                <p className="text-xs text-gray-500 mt-1 mb-3">
                  {isPinUnlocked ? 'Nội dung và sự kiện đã sẵn sàng để chỉnh sửa.' : 'Mã PIN bảo mật gồm 4 chữ số bí mật (0000 - 9999).'}
                </p>
                <button
                  type="button"
                  onClick={() => setIsPinUnlocked(!isPinUnlocked)}
                  className={`rounded-full px-4 py-1.5 text-xs font-bold text-white transition-all ${
                    isPinUnlocked
                      ? 'bg-gray-700 hover:bg-gray-800'
                      : 'bg-green-600 hover:bg-green-700 dark:bg-red-600 dark:hover:bg-red-500 shadow-md'
                  }`}
                >
                  {isPinUnlocked ? 'Khóa lại sổ' : '✦ Thử mở khóa mã 4 số'}
                </button>
              </div>

              {/* Note revealed or hidden content */}
              <div className="rounded-2xl border border-black/10 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-neutral-900">
                <div className="flex items-center justify-between mb-2">
                  <span className="text-xs font-bold text-gray-900 dark:text-white">
                    Nhật ký tài chính & Ý tưởng cá nhân
                  </span>
                  <span className="flex items-center gap-1 text-[10px] font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full dark:bg-amber-950 dark:text-amber-300">
                    <BellIcon className="h-3 w-3" /> 09:00 20/09
                  </span>
                </div>

                {isPinUnlocked ? (
                  <div className="text-xs text-gray-700 dark:text-gray-200 space-y-1.5 bg-green-50/50 p-2.5 rounded-lg border border-green-200 dark:bg-red-950/20 dark:border-red-900/40">
                    <p>✅ <strong>Nội dung bí mật:</strong> Kế hoạch tiết kiệm và đầu tư quý 4.</p>
                    <p>🎯 Mục tiêu hoàn thành trước ngày 20/09/2026.</p>
                    <p className="text-[11px] text-green-700 dark:text-red-400 font-semibold mt-1">
                      → Tùy chỉnh sự kiện & nội dung thành công!
                    </p>
                  </div>
                ) : (
                  <div className="flex flex-col items-center justify-center p-6 bg-gray-100/70 rounded-lg text-center dark:bg-neutral-800/60">
                    <LockClosedIcon className="h-6 w-6 text-gray-400 mb-1" />
                    <span className="text-xs font-semibold text-gray-500">
                      Nội dung được che chắn an toàn
                    </span>
                  </div>
                )}
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
