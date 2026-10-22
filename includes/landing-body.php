                    <main className="nz2-main">
                        <section id="home" className="nz2-hero" aria-label="Giới thiệu Notezy">
                            <div className="nz2-hero-copy">
                                <span className="nz2-kicker">✦ NOTEZY · YOUR MAGICAL NOTEBOOK</span>
                                <h1>Mỗi ý tưởng xứng đáng có <em>một nơi thật diệu kỳ.</em></h1>
                                <p>Viết tự do, sắp xếp dễ dàng và tìm lại mọi điều quan trọng trong thế giới sổ tay đầy màu sắc của bạn.</p>
                                <div className="nz2-actions">
                                    <button type="button" onClick={() => { setAuthMode('register'); setIsAuthModalOpen(true); }}>Bắt đầu cùng Notezy <span>↗</span></button>
                                </div>
                                <div className="nz2-hero-points"><span>✦ Ghi chú thông minh</span><span>✦ Không gian riêng</span><span>✦ Cộng tác dễ dàng</span></div>
                            </div>
                            <div className="nz2-stage" aria-label="Video giới thiệu Notezy 20 giây">
                                <div className="nz2-stage-glow" aria-hidden="true" />
                                <video autoPlay muted loop playsInline controls preload="metadata" poster="assets/notezy-magic-hero-2026.png" aria-label="Video giới thiệu Notezy dài 20 giây"><source src="assets/notezy-study-loop-20s.mp4" type="video/mp4" />Trình duyệt của bạn không hỗ trợ video.</video>
                            </div>
                        </section>

                        <section className="nz2-collection" aria-labelledby="nz2-collection-title"><div className="nz2-section-heading"><span>THE NOTEBOOK COLLECTION</span><h2 id="nz2-collection-title">Chọn cuốn sổ <em>hợp với bạn.</em></h2><p>Mỗi cuốn sổ có một sắc màu và một câu chuyện riêng.</p></div><div className="nz2-collection-grid">
                            {[
                                ['Creative Journal','Cho mọi ý tưởng bất chợt','assets/notezy-magic-hero-2026.png','rose'],
                                ['Study Notebook','Học tập đầy cảm hứng','assets/notezy-study-3d.webp','blue'],
                                ['Daily Planner','Một ngày thật gọn gàng','assets/notezy-planner-3d.webp','peach'],
                                ['Personal Diary','Giữ những điều của riêng bạn','assets/notezy-mascot-2026.png','lilac'],
                                ['Idea Book','Biến ý tưởng thành điều thật','assets/notezy-journal-3d.webp','mint'],
                                ['Work Notes','Tập trung vào việc quan trọng','assets/notezy-notebooks-2026.png','butter'],
                            ].map(([title,description,image,tone])=><article key={title} className={`nz2-card nz2-card--${tone}`}><div className="nz2-card-media"><img src={image} alt={`Minh họa 3D ${title}`} loading="lazy" /><span aria-hidden="true">✦</span></div><div className="nz2-card-copy"><h3>{title}</h3><p>{description}</p></div></article>)}
                        </div></section>


                        <section className="nz2-benefits" aria-labelledby="nz2-benefits-title"><div className="nz2-section-heading"><span>MADE FOR YOUR IDEAS</span><h2 id="nz2-benefits-title">Đẹp để mở ra. <em>Hữu ích mỗi ngày.</em></h2></div><div className="nz2-benefits-grid"><article><span>✎</span><h3>Viết không ngắt quãng</h3><p>Ghi chú và tự động lưu trong không gian của bạn.</p></article><article><span>⌕</span><h3>Tìm lại nhanh chóng</h3><p>Tìm kiếm, ghim và gắn nhãn để mọi ý tưởng luôn trong tầm tay.</p></article><article><span>✦</span><h3>Học cùng AI</h3><p>Hỏi đáp và tóm tắt khi bạn cần nhìn rõ điều quan trọng.</p></article><article><span>♡</span><h3>Chia sẻ an tâm</h3><p>Cộng tác trên ghi chú với quyền truy cập rõ ràng.</p></article></div></section>

                        <section id="about" className="nz2-about" aria-labelledby="nz2-about-title"><div className="nz2-about-visual"><img src="assets/notezy-mascot-2026.png" alt="Linh vật sổ tay Notezy" loading="lazy" /><span>✦</span></div><div><span className="nz2-kicker">ABOUT NOTEZY</span><h2 id="nz2-about-title">Một người bạn nhỏ cho <em>mọi ý tưởng lớn.</em></h2><p>Notezy lấy cảm hứng từ cảm giác mở một cuốn sổ mới: nhẹ nhàng, vui vẻ và đầy khả năng. Bên dưới thế giới hoạt hình là công cụ để bạn thật sự viết, sắp xếp, bảo vệ và chia sẻ ghi chú.</p><a href="#home">Về đầu trang →</a></div></section>

                        <section id="contact" className="nz2-contact" aria-labelledby="nz2-contact-title"><div><span className="nz2-kicker">CONTACT</span><h2 id="nz2-contact-title">Một lời nhắn có thể <em>mở đầu điều hay.</em></h2><p>Gửi câu hỏi hoặc góp ý cho nhóm Notezy.</p><a href="contact.php">Mở trang liên hệ →</a></div><div className="nz2-contact-art" aria-hidden="true"><span>✉</span><span>✦</span><span>✈</span></div></section>

                        <section className="nz2-final"><img src="assets/notezy-magic-hero-2026.png" alt="Sổ tay Notezy chào đón bạn" loading="lazy" /><div><span>YOUR STORY STARTS HERE</span><h2>Sẵn sàng viết trang đầu tiên?</h2><p>Tạo một không gian ghi chú thật riêng cho bạn.</p></div><button type="button" onClick={() => { setAuthMode('register'); setIsAuthModalOpen(true); }}>Đăng ký miễn phí ↗</button></section>
                    </main>
                    <footer className="nz2-footer"><div><strong>Notezy <span>AI</span></strong><p>Một nơi diệu kỳ cho ý tưởng mỗi ngày.</p></div><nav aria-label="Liên kết giới thiệu"><a href="#home">Trang chủ</a><a href="#about">Câu chuyện</a><a href="#contact">Liên hệ</a></nav><nav aria-label="Hỗ trợ"><a href="contact.php">Trung tâm hỗ trợ</a><a href="privacy.php">Chính sách bảo mật</a></nav><div className="nz2-footer-contact"><strong>Kết nối</strong><div className="nz2-social"><a href="mailto:info@notezy.com" aria-label="Gửi email">✉</a><a href="#contact" aria-label="Instagram">◎</a><a href="#contact" aria-label="Facebook">f</a><a href="#home" aria-label="Video Notezy">▶</a></div><span>TP. Hồ Chí Minh · hello@notezy.com</span></div><small>© 2026 Notezy</small></footer>
