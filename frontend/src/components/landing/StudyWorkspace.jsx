import React, { useState } from 'react';
import { AcademicCapIcon, BookOpenIcon, CheckCircleIcon, UserGroupIcon } from '@heroicons/react/24/outline';

const notebooks = [
  {
    key: 'lecture',
    icon: BookOpenIcon,
    tab: 'Bài giảng',
    subject: 'SINH HỌC · CHƯƠNG 03',
    title: 'Một bài học, một trang sổ rõ ràng.',
    summary: 'Ghi lại ý chính ngay trên lớp, thêm hình minh họa và gắn nhãn theo môn. Khi cần ôn lại, tìm kiếm đưa bạn về đúng trang.',
    noteTitle: 'Cấu trúc tế bào',
    lines: ['Màng sinh chất · kiểm soát trao đổi chất', 'Nhân tế bào · lưu giữ thông tin di truyền', 'Ti thể · cung cấp năng lượng'],
    tag: 'Sinh học',
    tone: 'sage',
  },
  {
    key: 'review',
    icon: AcademicCapIcon,
    tab: 'Ôn tập',
    subject: 'KẾ HOẠCH · TUẦN NÀY',
    title: 'Ôn đúng phần quan trọng, đúng lúc.',
    summary: 'Ghim những trang cần nhớ, xem ghi chú theo dạng lưới hoặc danh sách và nhờ AI tóm tắt khi bạn muốn nhìn lại bức tranh lớn.',
    noteTitle: 'Ôn tập trước kiểm tra',
    lines: ['Đọc lại chương 01–03', 'Tóm tắt công thức và thuật ngữ', 'Đánh dấu nội dung cần hỏi thêm'],
    tag: 'Ôn thi',
    tone: 'lavender',
  },
  {
    key: 'team',
    icon: UserGroupIcon,
    tab: 'Học nhóm',
    subject: 'DỰ ÁN · CÙNG LỚP',
    title: 'Chung một cuốn sổ, cùng tiến bộ.',
    summary: 'Chia sẻ ghi chú cho bạn học, cùng cập nhật nội dung và quản lý những trang riêng tư bằng mật khẩu khi cần.',
    noteTitle: 'Dàn ý thuyết trình',
    lines: ['Mở đầu · câu hỏi dẫn nhập', 'Nội dung · phân chia từng phần', 'Kết luận · ví dụ minh họa'],
    tag: 'Học nhóm',
    tone: 'peach',
  },
];

export default function StudyWorkspace() {
  const [selected, setSelected] = useState(0);
  const current = notebooks[selected];

  return (
    <section id="study" className="notezy-study" aria-labelledby="study-title">
      <div className="notezy-study__intro">
        <span className="notezy-eyebrow">NOTEZY CHO VIỆC HỌC</span>
        <h2 id="study-title">Một chiếc bàn học nhỏ.<br /><em>Cả hành trình ở bên trong.</em></h2>
        <p>Từ buổi học đầu tiên đến ngày ôn thi, mỗi ghi chú đều có một chỗ thật dễ tìm.</p>
      </div>
      <div className="notezy-study__surface">
        <div className="notezy-study__tabs" role="tablist" aria-label="Tình huống dùng sổ học tập">
          {notebooks.map((item, index) => {
            const Icon = item.icon;
            return <button key={item.key} id={`study-tab-${item.key}`} type="button" role="tab" aria-selected={selected === index} aria-controls="study-panel" className={selected === index ? 'is-active' : ''} onClick={() => setSelected(index)}><Icon aria-hidden="true" />{item.tab}</button>;
          })}
        </div>
        <div id="study-panel" role="tabpanel" aria-labelledby={`study-tab-${current.key}`} className="notezy-study__panel" key={current.key}>
          <div className="notezy-study__copy">
            <span className="notezy-study__subject">{current.subject}</span>
            <h3>{current.title}</h3>
            <p>{current.summary}</p>
            <a href="#features" className="notezy-study__explore">Khám phá tính năng <span aria-hidden="true">→</span></a>
          </div>
          <div className={`notezy-study__illustration notezy-study__illustration--${current.tone}`} aria-label={`Minh họa sổ ${current.tab.toLowerCase()}`}>
            <div className="notezy-study__orbit notezy-study__orbit--one" aria-hidden="true" />
            <div className="notezy-study__orbit notezy-study__orbit--two" aria-hidden="true" />
            <div className="notezy-study__notebook">
              <div className="notezy-study__spiral" aria-hidden="true">••••••••</div>
              <span className="notezy-study__note-tag">{current.tag}</span>
              <h4>{current.noteTitle}</h4>
              <ul>{current.lines.map(line => <li key={line}><CheckCircleIcon aria-hidden="true" />{line}</li>)}</ul>
              <span className="notezy-study__page-number">NOTEZY / 01</span>
            </div>
            <span className="notezy-study__sparkle notezy-study__sparkle--one" aria-hidden="true">✦</span>
            <span className="notezy-study__sparkle notezy-study__sparkle--two" aria-hidden="true">✳</span>
          </div>
        </div>
      </div>
    </section>
  );
}
