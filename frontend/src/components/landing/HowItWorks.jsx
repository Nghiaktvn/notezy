import React from 'react';
import { UserPlusIcon, PencilIcon, TagIcon } from '@heroicons/react/24/outline';

const steps = [
  { number: '01', icon: UserPlusIcon, title: 'Tạo không gian của bạn', desc: 'Đăng ký bằng email và bắt đầu với một cuốn sổ trống.' },
  { number: '02', icon: PencilIcon, title: 'Viết điều đầu tiên', desc: 'Ghi lại ý tưởng, thêm ảnh và để Notezy tự lưu khi bạn viết.' },
  { number: '03', icon: TagIcon, title: 'Giữ mọi thứ gọn gàng', desc: 'Gắn nhãn, ghim sổ quan trọng và tìm lại khi cần.' },
];

export default function HowItWorks() {
  return <section className="notezy-steps" aria-labelledby="steps-title">
    <div className="notezy-section-intro"><span className="notezy-eyebrow">THẬT DỄ ĐỂ BẮT ĐẦU</span><h2 id="steps-title">Ba bước cho một<br /><em>đầu óc thảnh thơi.</em></h2></div>
    <div className="notezy-steps__grid">{steps.map(step => {
      const Icon = step.icon;
      return <article key={step.number} className="notezy-step"><span className="notezy-step__number">{step.number}</span><Icon aria-hidden="true" /><h3>{step.title}</h3><p>{step.desc}</p></article>;
    })}</div>
  </section>;
}
