<?php
require_once __DIR__ . '/includes/session.php';
require_once('db.php');
notezy_session_start();

if (!isset($_SESSION['id'])) {
    header('Location: index.php');
    exit();
}

$user_id = (int) $_SESSION['id'];  // 🛠️ Thêm dòng này để gán giá trị user_id

$sql1 = "SELECT avatar FROM users WHERE id = ?";
$stm1 = $conn->prepare($sql1);

if (!$stm1) {
    die("Prepare failed: " . $conn->error);
}

$stm1->bind_param('i', $user_id);

if (!$stm1->execute()) {
    die("Execute failed: " . $stm1->error);
}

$result1 = $stm1->get_result();

if (!$result1) {
    die("Get result failed: " . $stm1->error);
}

$kq = $result1->fetch_assoc();
$avatar = $kq && isset($kq['avatar']) ? $kq['avatar'] : 'default.png'; // fallback nếu không có avatar
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notezy-Introduction</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="CSS/main.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
</head>
<body>
    <header>
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <img src="logo.png" alt="Notezy" width="50" height="50" class="me-2" />
            <a class="navbar-brand logo" href="index_notezy.php">Notezy</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index_notezy.php"><i class="fas fa-home me-1"></i>Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="introduce.php"><i class="fas fa-info-circle me-1"></i>About Us</a></li>
                    <li class="nav-item"><a class="nav-link" href="khampha.php"><i class="fas fa-star me-1"></i>Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="privacy.php"><i class="fas fa-shield-alt me-1"></i>Privacy</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php"><i class="fas fa-envelope me-1"></i>Contact</a></li>
                </ul>
            </div>
        </div>
    </nav>
</header>

    <section class="artist-detail py-5" style="margin-top: 40px;">
        <div class="container">
            <h1 class="section-title">Introduction</h1>
            <div class="row align-items-center">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <div class="artist-video-container">
                        <div class="ratio ratio-16x9">
                            <video controls muted playsinline preload="metadata" src="introduce.mp4"></video>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="artist-description">
                        <h3 style="color: black;font-weight: bold;">Introduce:</h3>
                        <p style="color: black;"> Notezy là một ứng dụng ghi chú hiện đại được thiết kế để giúp người dùng sắp xếp công việc, học tập và ý tưởng sáng tạo một cách khoa học và hiệu quả. Với giao diện tối giản nhưng tinh tế, Notezy mang lại trải nghiệm ghi chú mượt mà, dễ dùng, phù hợp cho mọi đối tượng từ học sinh, sinh viên đến người đi làm.
                            <br><br>
                            Ứng dụng hỗ trợ đồng bộ hóa dữ liệu trên nhiều thiết bị, cho phép bạn ghi chú mọi lúc mọi nơi. Bên cạnh đó, Notezy còn tích hợp các tính năng mạnh mẽ như nhắc việc, gắn nhãn, chia nhóm, mã hóa ghi chú và hỗ trợ Markdown – tất cả đều nhằm mục tiêu nâng cao năng suất cá nhân.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <main>
            <section class="exhibition" id="features">
                <div class="container">
                  <h2 class="section-title">Why Choose Notezy?</h2>
                  <div class="row exhibition-grid">
                    
                    <section class="post">
                      <img src="../imageltw/image1.jpg" alt="Secure Notes" loading="lazy" class="lazy-slide"/>
                      <div class="lazy-fade">
                        <h2>Secure and Private</h2>
                        <p class="meta">Updated April 2025</p>
                        <p class="content">
                          All your notes are protected with top-level encryption. Notezy respects your privacy and ensures your content is only yours — anytime, anywhere.
                        </p>
                        <a href="#" class="read-more">LEARN MORE</a>
                      </div>
                    </section>
              
                    <section class="post">
                      <img src="../imageltw/image2.jpg" alt="Beautiful UI" loading="lazy" class="lazy-slide"/>
                      <div class="lazy-fade">
                        <h2>Elegant Interface</h2>
                        <p class="meta">Updated April 2025</p>
                        <p class="content">
                          Designed with clarity in mind, Notezy features a clean, minimal interface with light/dark themes to keep you focused — not distracted.
                        </p>
                        <a href="#" class="read-more">LEARN MORE</a>
                        <a href="register.php" class="fw-bold text-dark">Register now</a>
                      </div>
                    </section>
              
                    <section class="post">
                      <img src="../imageltw/image3.jpg" alt="Fast sync" loading="lazy" class="lazy-slide"/>
                      <div class="lazy-fade">
                        <h2>Sync Across Devices</h2>
                        <p class="meta">Updated April 2025</p>
                        <p class="content">
                          Whether on phone, tablet, or PC — your notes sync instantly with your account. Just log in, and you’re ready to go.
                        </p>
                        <a href="#" class="read-more">LEARN MORE</a>
                      </div>
                    </section>
              
                    <section class="post">
                      <img src="../imageltw/image4.jpg" alt="Organized notes" loading="lazy" class="lazy-slide"/>
                      <div class="lazy-fade">
                        <h2>Smart Organization</h2>
                        <p class="meta">Updated April 2025</p>
                        <p class="content">
                          Tag your notes, sort by folder, or search instantly. With Notezy, everything is where you expect it — and easy to find.
                        </p>
                        <a href="#" class="read-more">LEARN MORE</a>
                      </div>
                    </section>
              
                    <section class="post">
                      <img src="../imageltw/image5.jpg" alt="Collaborate" loading="lazy" class="lazy-slide"/>
                      <div class="lazy-fade">
                        <h2>Collaborate in Real-time</h2>
                        <p class="meta">Updated April 2025</p>
                        <p class="content">
                          Share notes with your team, study group, or family. Real-time collaboration makes group work seamless.
                        </p>
                        <a href="#" class="read-more">LEARN MORE</a>
                      </div>
                    </section>
              
                    <section class="post">
                      <img src="../imageltw/image6.jpg" alt="Themes and Customization" loading="lazy" class="lazy-slide"/>
                      <div class="lazy-fade">
                        <h2>Customize Your Style</h2>
                        <p class="meta">Updated April 2025</p>
                        <p class="content">
                          Choose your favorite color scheme, font, and layout. Notezy lets you write the way you love.
                        </p>
                        <a href="#" class="read-more">LEARN MORE</a>
                      </div>
                    </section>
              
                    <section class="post">
                      <img src="../imageltw/image7.jpg" alt="Offline Mode" loading="lazy" class="lazy-slide"/>
                      <div class="lazy-fade">
                        <h2>Work Offline</h2>
                        <p class="meta">Updated April 2025</p>
                        <p class="content">
                          No internet? No problem. Notezy works offline and syncs your data once you’re back online — hassle-free.
                        </p>
                        <a href="#" class="read-more">LEARN MORE</a>
                      </div>
                    </section>
              
                    <section class="post">
                      <img src="../imageltw/image8.jpg" alt="Productivity Focus" loading="lazy" class="lazy-slide"/>
                      <div class="lazy-fade">
                        <h2>Focus Mode</h2>
                        <p class="meta">Updated April 2025</p>
                        <p class="content">
                          Turn off distractions with Focus Mode. Clean layout, zero clutter — just you and your thoughts.
                        </p>
                        <a href="#" class="read-more">LEARN MORE</a>
                      </div>
                    </section>
              
                  </div>
                </div>
              </section>
              
        

    <footer id="contact" class="text-white-50">
        <div class="container">
            <div class="row footer-content">
                <div class="col-md-4 footer-about">
                    <div class="footer-logo text-white">Notezy</div>
                    <p></p>
                    <div class="social-links">
                        <a href="#" class="text-white-50"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white-50"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-white-50"><i class="fab fa-youtube"></i></a>
                        <a href="#" class="text-white-50"><i class="fab fa-twitter"></i></a>
                    </div>
                </div>
                <div class="col-md-4 footer-links">
                    <h3 class="text-white">Link</h3>
                    <ul class="list-unstyled">
                        <li><a href="index_notezy.php" class="text-white-50">Home</a></li>
                        <li><a href="introduce.php" class="text-white-50">About Us</a></li>
                        <li><a href="khampha.php" class="text-white-50">Explore Features</a></li>
                        <li><a href="privacy.php" class="text-white-50">Privacy Policy</a></li>
                        <li><a href="contact.php" class="text-white-50">Contact Us</a></li> 
                    </ul>
                </div>
                <div class="col-md-4 footer-contact">
                    <h3 class="text-white">Contact us</h3>
                    <p><i class="fas fa-map-marker-alt"></i>..........</p>
                    <p><i class="fas fa-phone"></i>..........</p>
                    <p><i class="fas fa-envelope"></i>..........</p>
                    <p><i class="fas fa-clock"></i>..........</p>
                </div>
            </div>
            <div class="copyright text-center mt-5 pt-3 border-top">
                <p>© 2024 ART. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
        const images = document.querySelectorAll(".lazy-slide");
        const contents = document.querySelectorAll(".lazy-fade");
    
        const observerOptions = {
            threshold: 0.3
        };
    
        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add("slide-in");
                observer.unobserve(entry.target);
            }
            });
        }, observerOptions);
    
        const contentObserver = new IntersectionObserver(entries => {
            entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add("fade-in");
                contentObserver.unobserve(entry.target);
            }
            });
        }, observerOptions);
    
        images.forEach(img => observer.observe(img));
        contents.forEach(el => contentObserver.observe(el));
        });
        
      
    
    
    </script>
</body>
</html>