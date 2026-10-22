import React, { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { PauseIcon, PlayIcon } from '@heroicons/react/24/solid';

export default function VideoBanner() {
  const videoRef = useRef(null);
  const [playing, setPlaying] = useState(true);

  useEffect(() => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      videoRef.current?.pause();
    }
  }, []);

  const togglePlayback = () => {
    const video = videoRef.current;
    if (!video) return;
    if (video.paused) video.play().catch(() => setPlaying(false));
    else video.pause();
  };

  return <section id="demo" className="notezy-video-banner" aria-label="Giới thiệu Notezy bằng video 20 giây">
    <div className="notezy-video-banner__frame">
      <video ref={videoRef} autoPlay muted loop playsInline preload="metadata" poster="/assets/notezy-study-desk-2026.png" onPlay={() => setPlaying(true)} onPause={() => setPlaying(false)} aria-label="Minh họa những cuốn sổ và góc học tập Notezy">
        <source src="/assets/notezy-study-loop-20s.mp4" type="video/mp4" />
      </video>
      <div className="notezy-video-banner__shade" aria-hidden="true" />
      <div className="notezy-video-banner__content">
        <span>NOTEZY · SỔ HỌC TẬP SỐ</span>
        <h2>Ghi bài hôm nay.<br />Mở ra ngày mai.</h2>
        <p>Một không gian dịu mắt để viết, ôn tập và chia sẻ kiến thức.</p>
        <Link to="/login" className="notezy-button notezy-button--light">Đăng nhập để tiếp tục</Link>
      </div>
      <button type="button" className="notezy-video-banner__control" onClick={togglePlayback} aria-label={playing ? 'Tạm dừng video giới thiệu' : 'Phát video giới thiệu'}>{playing ? <PauseIcon aria-hidden="true" /> : <PlayIcon aria-hidden="true" />}<span>20 giây · Lặp lại</span></button>
    </div>
  </section>;
}
