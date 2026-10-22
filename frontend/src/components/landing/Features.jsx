import React from 'react';
import { PencilSquareIcon, MagnifyingGlassIcon, SparklesIcon, LockClosedIcon, UsersIcon, TagIcon, UserCircleIcon, CloudArrowDownIcon } from '@heroicons/react/24/outline';

const features = [
  { icon: PencilSquareIcon, title: 'Viết theo cách của bạn', desc: 'Tạo và tự động lưu ghi chú, thêm ảnh, tùy chỉnh màu sắc và ghim điều quan trọng.', tone: 'paper', id: 'notes' },
  { icon: SparklesIcon, title: 'Một người bạn AI', desc: 'Hỏi đáp, tóm tắt và phát triển ý tưởng ngay khi bạn cần thêm một góc nhìn.', tone: 'lavender', id: 'ai' },
  { icon: MagnifyingGlassIcon, title: 'Tìm lại trong tích tắc', desc: 'Tìm theo từ khóa và chuyển đổi giữa chế độ lưới hoặc danh sách.', tone: 'sage', id: 'search' },
  { icon: TagIcon, title: 'Mọi thứ đúng chỗ', desc: 'Gắn nhãn, lọc theo chủ đề và sắp xếp những cuốn sổ theo cách bạn muốn.', tone: 'peach', id: 'labels' },
  { icon: LockClosedIcon, title: 'Không gian riêng tư', desc: 'Đặt mật khẩu cho ghi chú hoặc bảo vệ sổ bằng mã PIN 6 số.', tone: 'lavender', id: 'security' },
  { icon: UsersIcon, title: 'Cùng nhau hoàn thiện', desc: 'Chia sẻ ghi chú và cộng tác với người được bạn cấp quyền.', tone: 'sage', id: 'collaboration' },
  { icon: UserCircleIcon, title: 'Tài khoản của riêng bạn', desc: 'Quản lý hồ sơ, avatar, mật khẩu và những tùy chọn cá nhân trong một nơi.', tone: 'peach', id: 'account' },
  { icon: CloudArrowDownIcon, title: 'Mang theo cả khi ngoại tuyến', desc: 'Giao diện PWA hỗ trợ mở lại nội dung đã lưu và đồng bộ những thay đổi khi có mạng.', tone: 'paper', id: 'offline' },
];

export default function Features() {
  return <section id="features" className="notezy-features" aria-labelledby="features-title">
    <div className="notezy-section-intro"><span className="notezy-eyebrow">THIẾT KẾ CHO MỌI Ý TƯỞNG</span><h2 id="features-title">Đơn giản để bắt đầu.<br /><em>Đủ sâu để đi xa.</em></h2><p>Những công cụ hữu ích ở đúng nơi bạn cần, để việc ghi chú luôn là phần nhẹ nhàng nhất trong ngày.</p></div>
    <div className="notezy-features__grid">{features.map(feature => {
      const Icon = feature.icon;
      return <article id={feature.id} key={feature.title} className={`notezy-feature-card notezy-feature-card--${feature.tone}`}>
        <span className="notezy-feature-card__icon"><Icon aria-hidden="true" /></span>
        <h3>{feature.title}</h3><p>{feature.desc}</p>
      </article>;
    })}</div>
  </section>;
}
