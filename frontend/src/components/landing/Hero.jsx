import React from 'react';
import { Link } from 'react-router-dom';
import { ArrowRightIcon, ArrowRightOnRectangleIcon, ShieldCheckIcon } from '@heroicons/react/24/outline';

export default function Hero() {
  return (
    <section className="notezy-hero-react" aria-labelledby="hero-title">
      <div className="notezy-hero-react__inner">
        <div className="notezy-hero-react__copy">
          <span className="notezy-eyebrow"><span className="notezy-eyebrow__dot" /> CUỐN SỔ HỌC TẬP CỦA BẠN</span>
          <h1 id="hero-title">Học có hệ thống.<br /><em>Nhớ theo cách riêng.</em></h1>
          <p>Biến bài giảng, ý tưởng và kế hoạch học tập thành những trang sổ dễ tìm lại. Notezy giúp bạn viết, sắp xếp, chia sẻ và học cùng trợ lý AI trong một không gian dịu mắt.</p>
          <div className="notezy-hero-react__actions">
            <Link to="/login" className="notezy-button notezy-button--primary"><ArrowRightOnRectangleIcon aria-hidden="true" /> Đăng nhập</Link>
            <Link to="/register" className="notezy-button notezy-button--ghost">Đăng ký miễn phí <ArrowRightIcon aria-hidden="true" /></Link>
          </div>
          <div className="notezy-hero-react__assurance"><ShieldCheckIcon aria-hidden="true" /> Ghi chú cá nhân · Sổ học nhóm · Xem <a href="#demo">bản demo</a></div>
        </div>
        <div className="notezy-hero-react__art">
          <div className="notezy-hero-react__halo" aria-hidden="true" />
          <img src="/assets/notezy-magic-hero-2026.png" width="1536" height="1024" alt="Linh vật sổ tay 3D màu tím với bút và những ngôi sao bay quanh" fetchPriority="high" />
          <span className="notezy-art-note notezy-art-note--top">✦ Mỗi môn học, một góc riêng</span>
          <span className="notezy-art-note notezy-art-note--bottom">Bài giảng · Nhãn · Trợ lý AI</span>
        </div>
      </div>
    </section>
  );
}
