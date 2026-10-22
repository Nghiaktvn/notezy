import React from 'react';
import { Link } from 'react-router-dom';
import { ArrowRightIcon } from '@heroicons/react/24/outline';

export default function CTASection() {
  return <section className="notezy-final-cta" aria-labelledby="final-cta-title">
    <div className="notezy-final-cta__panel">
      <img src="/assets/notezy-mascot-2026.png" width="1302" height="1208" alt="Linh vật Notezy chào bạn" loading="lazy" />
      <div><span className="notezy-eyebrow">BẮT ĐẦU TỪ MỘT DÒNG</span><h2 id="final-cta-title">Cuốn sổ tiếp theo<br />là của bạn.</h2><p>Tạo một không gian riêng cho những điều bạn chưa muốn quên.</p></div>
      <Link to="/login" className="notezy-button notezy-button--light">Đăng nhập để tiếp tục <ArrowRightIcon aria-hidden="true" /></Link>
    </div>
  </section>;
}
