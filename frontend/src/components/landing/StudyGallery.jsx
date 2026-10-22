import React from 'react';
import { Link } from 'react-router-dom';
import { ArrowRightIcon } from '@heroicons/react/24/outline';

export default function StudyGallery() {
  return <section className="notezy-gallery" aria-labelledby="gallery-title">
    <div className="notezy-gallery__inner">
      <div className="notezy-gallery__story">
        <span className="notezy-eyebrow">MANG CẢ GÓC HỌC TẬP THEO BẠN</span>
        <h2 id="gallery-title">Cảm hứng của một cuốn sổ.<br /><em>Sự tiện lợi của không gian số.</em></h2>
        <p>Không cần chọn giữa trang giấy thân quen và công cụ thông minh. Notezy mang nhịp học tự nhiên vào một nơi để bạn viết, tìm kiếm, bảo vệ và chia sẻ kiến thức.</p>
        <Link to="/login" className="notezy-button notezy-button--primary">Đăng nhập vào sổ của bạn <ArrowRightIcon aria-hidden="true" /></Link>
      </div>
      <div className="notezy-gallery__visuals">
        <div className="notezy-gallery__desk"><img src="/assets/notezy-study-desk-2026.png" width="1792" height="1024" alt="Minh họa bàn học với cuốn sổ mở, thẻ ôn tập và trợ lý AI nhỏ" loading="lazy" /></div>
        <div className="notezy-gallery__collection"><img src="/assets/notezy-study-collection-2026.png" width="1792" height="1024" alt="Ba cuốn sổ học tập màu tím, xanh sage và kem cho ghi chép, ôn tập và học nhóm" loading="lazy" /><span>Ghi bài · Ôn tập · Học nhóm</span></div>
      </div>
    </div>
  </section>;
}
