import React, { useState, useEffect } from 'react';
import {
  PlayIcon,
  PauseIcon,
  ArrowPathIcon,
  SparklesIcon,
  LockClosedIcon,
  DocumentTextIcon,
  CheckCircleIcon,
} from '@heroicons/react/24/outline';

export default function VideoDemo() {
  const [playing, setPlaying] = useState(false);
  const [progress, setProgress] = useState(0); // 0 to 100%
  const [step, setStep] = useState(1); // 1: Note, 2: 4-digit PIN, 3: AI Copilot

  useEffect(() => {
    let timer;
    if (playing) {
      timer = setInterval(() => {
        setProgress((prev) => {
          if (prev >= 100) {
            setPlaying(false);
            return 100;
          }
          const next = prev + 2.5;
          if (next < 35) setStep(1);
          else if (next < 70) setStep(2);
          else setStep(3);
          return next;
        });
      }, 500);
    }
    return () => clearInterval(timer);
  }, [playing]);

  const restart = () => {
    setProgress(0);
    setStep(1);
    setPlaying(true);
  };

  return (
    <section id="demo" className="py-20 sm:py-28 bg-white dark:bg-black transition-colors duration-300">
      <div className="mx-auto max-w-5xl px-5 text-center sm:px-8">
        <span className="text-xs font-bold uppercase tracking-widest text-green-700 bg-green-100 px-3 py-1 rounded-full dark:text-red-400 dark:bg-red-950/60 border border-green-200 dark:border-red-900/60">
          Demo trực quan
        </span>
        <h2 className="mt-4 font-[var(--font-display)] text-3xl sm:text-4xl md:text-5xl font-extrabold tracking-tight text-gray-950 dark:text-white uppercase">
          See Notezy in action
        </h2>
        <p className="mx-auto mt-3 max-w-xl text-sm sm:text-base text-gray-600 dark:text-gray-400">
          Trải nghiệm mô phỏng cách ghi chú, bảo mật mã 4 số và trợ lý AI phối hợp trong 30–60 giây.
        </p>

        {/* Video Frame */}
        <div className="relative mx-auto mt-10 aspect-video max-w-3xl overflow-hidden rounded-3xl border border-black/10 bg-neutral-950 shadow-2xl shadow-green-950/10 dark:border-red-900/40 dark:shadow-red-950/40">
          {!playing && progress === 0 ? (
            <button
              type="button"
              onClick={() => setPlaying(true)}
              className="group absolute inset-0 flex flex-col items-center justify-center gap-4 bg-[linear-gradient(135deg,rgba(16,185,129,0.9),rgba(5,5,5,0.95))] dark:bg-[linear-gradient(135deg,rgba(220,38,38,0.85),rgba(5,5,5,0.95))] transition-all"
              aria-label="Phát video demo Notezy"
            >
              <span className="flex h-20 w-20 items-center justify-center rounded-full bg-white text-green-700 shadow-2xl transition-transform duration-300 group-hover:scale-110 dark:text-red-600">
                <PlayIcon className="ml-1 h-9 w-9 fill-current" />
              </span>
              <div className="text-center">
                <span className="block text-base font-bold text-white">
                  ▶ Xem Video Demo Notezy (45s)
                </span>
                <span className="text-xs text-white/80">
                  Bấm để khám phá quy trình làm việc thông minh
                </span>
              </div>
            </button>
          ) : (
            <div className="absolute inset-0 flex flex-col justify-between p-5 text-left text-white bg-neutral-900/90">
              {/* Top status bar */}
              <div className="flex items-center justify-between border-b border-white/10 pb-3">
                <div className="flex items-center gap-2">
                  <span className="inline-block h-2.5 w-2.5 rounded-full bg-red-500 animate-pulse" />
                  <span className="text-xs font-bold uppercase tracking-wider text-gray-300">
                    {step === 1 && 'Bước 1/3: Ghi chú nhanh & Nhãn màu'}
                    {step === 2 && 'Bước 2/3: Khóa bảo mật sổ bằng mã 4 số'}
                    {step === 3 && 'Bước 3/3: Trợ lý AI tóm tắt & lập danh sách việc'}
                  </span>
                </div>
                <div className="text-xs font-mono text-gray-400">
                  {Math.round((progress / 100) * 45)}s / 45s
                </div>
              </div>

              {/* Animated content demo */}
              <div className="my-auto max-w-xl mx-auto w-full">
                {step === 1 && (
                  <div className="bg-neutral-800 p-5 rounded-2xl border border-white/10 shadow-lg animate-fade-in">
                    <div className="flex items-center justify-between mb-2">
                      <span className="text-xs font-bold text-green-400 dark:text-red-400">
                        📝 Tạo sổ mới trên trang chủ
                      </span>
                      <span className="text-[10px] bg-green-900/60 text-green-300 px-2 py-0.5 rounded-full">
                        Tự động lưu
                      </span>
                    </div>
                    <h4 className="text-sm font-bold text-white">Họp chiến lược Quý 4</h4>
                    <p className="text-xs text-gray-300 mt-2 leading-relaxed">
                      "Thảo luận về nâng cấp hệ sinh thái Notezy với chế độ Sáng (Trắng-Xanh) và Tối (Đen-Đỏ), kết hợp khóa bảo mật số an toàn..."
                    </p>
                  </div>
                )}

                {step === 2 && (
                  <div className="bg-neutral-800 p-5 rounded-2xl border border-amber-500/40 shadow-lg animate-fade-in text-center">
                    <div className="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-amber-500/20 text-amber-400">
                      <LockClosedIcon className="h-5 w-5" />
                    </div>
                    <h4 className="text-sm font-bold text-white">Đã kích hoạt bảo mật 4 số</h4>
                    <div className="my-3 flex justify-center gap-2">
                      {['2', '0', '2', '6'].map((num, i) => (
                        <span key={i} className="h-8 w-8 rounded-lg bg-black border border-white/20 flex items-center justify-center font-bold text-base text-green-400 dark:text-red-400">
                          {num}
                        </span>
                      ))}
                    </div>
                    <p className="text-xs text-gray-400">
                      Nội dung được che chắn an toàn. Nhập đúng 4 số để xem và tùy chỉnh sự kiện.
                    </p>
                  </div>
                )}

                {step === 3 && (
                  <div className="bg-neutral-800 p-5 rounded-2xl border border-green-500/40 shadow-lg animate-fade-in dark:border-red-500/40">
                    <div className="flex items-center gap-2 text-xs font-bold text-green-400 dark:text-red-400 mb-2">
                      <SparklesIcon className="h-4 w-4" />
                      <span>AI đã tạo 3 đầu việc quan trọng</span>
                    </div>
                    <div className="space-y-1.5 text-xs text-gray-200">
                      <div className="flex items-center gap-2">
                        <CheckCircleIcon className="h-4 w-4 text-green-500 dark:text-red-500" />
                        <span>Triển khai giao diện Sáng & Tối theo yêu cầu</span>
                      </div>
                      <div className="flex items-center gap-2">
                        <CheckCircleIcon className="h-4 w-4 text-green-500 dark:text-red-500" />
                        <span>Kích hoạt nút Bảo mật 4 số trên toàn bộ sổ</span>
                      </div>
                      <div className="flex items-center gap-2">
                        <CheckCircleIcon className="h-4 w-4 text-green-500 dark:text-red-500" />
                        <span>Sẵn sàng cho người dùng trải nghiệm ngay</span>
                      </div>
                    </div>
                  </div>
                )}
              </div>

              {/* Bottom controls */}
              <div className="flex flex-col gap-2 pt-3 border-t border-white/10">
                <div className="w-full bg-white/10 h-1.5 rounded-full overflow-hidden">
                  <div
                    className="h-full bg-green-500 transition-all duration-300 dark:bg-red-500"
                    style={{ width: `${progress}%` }}
                  />
                </div>
                <div className="flex items-center justify-between text-xs text-gray-400">
                  <div className="flex items-center gap-3">
                    <button
                      type="button"
                      onClick={() => setPlaying(!playing)}
                      className="hover:text-white"
                    >
                      {playing ? <PauseIcon className="h-4 w-4" /> : <PlayIcon className="h-4 w-4" />}
                    </button>
                    <button
                      type="button"
                      onClick={restart}
                      className="hover:text-white flex items-center gap-1"
                    >
                      <ArrowPathIcon className="h-3.5 w-3.5" />
                      <span>Xem lại</span>
                    </button>
                  </div>
                  <span>{progress >= 100 ? 'Hoàn thành demo!' : 'Đang phát mô phỏng...'}</span>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
    </section>
  );
}
