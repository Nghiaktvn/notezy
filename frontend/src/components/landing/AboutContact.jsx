import React from 'react';
import { Link } from 'react-router-dom';
import { ChatBubbleLeftRightIcon, HeartIcon, ShieldCheckIcon } from '@heroicons/react/24/outline';

export default function AboutContact() {
  return <>
    <section id="about" className="notezy-about" aria-labelledby="about-title">
      <div className="notezy-about__visual"><img src="/assets/notezy-study-collection-2026.png" width="1792" height="1024" alt="Bộ ba sổ học tập Notezy cho ghi bài, ôn thi và học nhóm" loading="lazy" /></div>
      <div className="notezy-about__copy">
        <span className="notezy-eyebrow">ABOUT NOTEZY</span>
        <h2 id="about-title">Làm cho việc học<br /><em>nhẹ nhàng hơn.</em></h2>
        <p>Notezy được xây dựng như một cuốn sổ học tập số: dễ viết, dễ tìm lại, có không gian riêng tư và hỗ trợ cộng tác. Giao diện gọn để bạn tập trung vào bài học, không phải học cách dùng công cụ.</p>
        <ul className="notezy-about__highlights">
          <li><strong>Tài khoản cá nhân</strong><span>Hồ sơ, avatar, mật khẩu và tùy chọn riêng.</span></li>
          <li><strong>Sổ ghi chú học tập</strong><span>Viết, gắn nhãn, tìm kiếm, ghim và thêm hình ảnh.</span></li>
          <li><strong>Học cùng nhau</strong><span>Chia sẻ, cộng tác và bảo vệ ghi chú quan trọng.</span></li>
          <li><strong>Trợ lý thông minh</strong><span>AI hỗ trợ hỏi đáp, tóm tắt; PWA hỗ trợ ngoại tuyến.</span></li>
        </ul>
        <div className="notezy-about__values"><span><HeartIcon aria-hidden="true" /> Dễ dùng mỗi ngày</span><span><ShieldCheckIcon aria-hidden="true" /> Tôn trọng riêng tư</span><span><ChatBubbleLeftRightIcon aria-hidden="true" /> Học cùng AI</span></div>
      </div>
    </section>
    <section id="contact" className="notezy-contact" aria-labelledby="contact-title"><div><span className="notezy-eyebrow">CONNECT</span><h2 id="contact-title">Kết nối với Notezy</h2><p>Bạn có câu hỏi hoặc góp ý về cuốn sổ học tập số? Hãy gửi email cho chúng tôi.</p><a className="notezy-contact__email" href="mailto:trungnghia282006@gmail.com">trungnghia282006@gmail.com</a></div><div className="notezy-contact__actions"><Link to="/login" className="notezy-button notezy-button--primary">Đăng nhập</Link><Link to="/register" className="notezy-button notezy-button--ghost">Đăng ký</Link><a href="#demo" className="notezy-contact__demo">Xem video giới thiệu →</a></div></section>
  </>;
}
