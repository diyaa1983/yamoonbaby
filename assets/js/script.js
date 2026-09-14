// Enhanced Mobile Menu Toggle
const hamburger = document.querySelector('.hamburger');
const navMenu = document.querySelector('.nav-menu');

if (hamburger && navMenu) {
    hamburger.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        hamburger.classList.toggle('active');
        navMenu.classList.toggle('active');
        if (navMenu.classList.contains('active')) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = 'auto';
        }
    });

    // Close mobile menu when clicking on a link
    document.querySelectorAll('.nav-link').forEach(n => n.addEventListener('click', () => {
        hamburger.classList.remove('active');
        navMenu.classList.remove('active');
        document.body.style.overflow = 'auto';
    }));

    // Close mobile menu when clicking outside
    document.addEventListener('click', (e) => {
        if (!hamburger.contains(e.target) && !navMenu.contains(e.target) && navMenu.classList.contains('active')) {
            hamburger.classList.remove('active');
            navMenu.classList.remove('active');
            document.body.style.overflow = 'auto';
        }
    });

    // Close mobile menu on window resize
    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) {
            hamburger.classList.remove('active');
            navMenu.classList.remove('active');
            document.body.style.overflow = 'auto';
        }
    });
}

// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// Products Slider functionality
function initProductsSlider() {
    const slides = document.querySelectorAll('.product-slide');
    const dots = document.querySelectorAll('.dot');
    let currentSlide = 0;
    let slideInterval;

    function showSlide(index) {
        // Hide all slides
        slides.forEach(slide => slide.classList.remove('active'));
        dots.forEach(dot => dot.classList.remove('active'));
        
        // Show current slide
        slides[index].classList.add('active');
        dots[index].classList.add('active');
    }

    function nextSlide() {
        currentSlide = (currentSlide + 1) % slides.length;
        showSlide(currentSlide);
    }

    function startAutoSlide() {
        slideInterval = setInterval(nextSlide, 3000); // Change slide every 3 seconds
    }

    function stopAutoSlide() {
        clearInterval(slideInterval);
    }

    // Add click event to dots
    dots.forEach((dot, index) => {
        dot.addEventListener('click', () => {
            currentSlide = index;
            showSlide(currentSlide);
            stopAutoSlide();
            startAutoSlide(); // Restart auto slide
        });
    });

    // Pause auto slide on hover
    const slider = document.querySelector('.products-slider');
    if (slider) {
        slider.addEventListener('mouseenter', stopAutoSlide);
        slider.addEventListener('mouseleave', startAutoSlide);
    }

    // Start auto slide
    startAutoSlide();
}

// Video Controls
function initVideoControls() {
    const video = document.getElementById('heroVideo');
    const playPauseBtn = document.getElementById('playPauseBtn');
    const muteBtn = document.getElementById('muteBtn');
    const progressBar = document.querySelector('.progress-bar');
    
    if (!video || !playPauseBtn || !muteBtn) return;
    
    // Play/Pause functionality
    playPauseBtn.addEventListener('click', () => {
        if (video.paused) {
            video.play();
            playPauseBtn.innerHTML = '<i class="fas fa-pause"></i>';
        } else {
            video.pause();
            playPauseBtn.innerHTML = '<i class="fas fa-play"></i>';
        }
    });
    
    // Mute/Unmute functionality
    muteBtn.addEventListener('click', () => {
        if (video.muted) {
            video.muted = false;
            muteBtn.innerHTML = '<i class="fas fa-volume-up"></i>';
        } else {
            video.muted = true;
            muteBtn.innerHTML = '<i class="fas fa-volume-mute"></i>';
        }
    });
    
    // Initialize mute button state based on video muted status
    if (video.muted) {
        muteBtn.innerHTML = '<i class="fas fa-volume-mute"></i>';
    } else {
        muteBtn.innerHTML = '<i class="fas fa-volume-up"></i>';
    }
    
    // Progress bar update
    video.addEventListener('timeupdate', () => {
        const progress = (video.currentTime / video.duration) * 100;
        progressBar.style.width = progress + '%';
    });
    
    // Click on progress bar to seek
    const videoProgress = document.querySelector('.video-progress');
    videoProgress.addEventListener('click', (e) => {
        const rect = videoProgress.getBoundingClientRect();
        const clickX = e.clientX - rect.left;
        const progressWidth = rect.width;
        const seekTime = (clickX / progressWidth) * video.duration;
        video.currentTime = seekTime;
    });
    
    // Video ended event
    video.addEventListener('ended', () => {
        playPauseBtn.innerHTML = '<i class="fas fa-play"></i>';
    });
    
    // Auto-hide controls after 3 seconds
    let controlsTimeout;
    const videoContainer = document.querySelector('.hero-video');
    
    const showControls = () => {
        const controls = document.querySelector('.video-controls');
        controls.style.opacity = '1';
        controls.style.transform = 'translateY(0)';
        
        clearTimeout(controlsTimeout);
        controlsTimeout = setTimeout(() => {
            controls.style.opacity = '0';
            controls.style.transform = 'translateY(20px)';
        }, 3000);
    };
    
    videoContainer.addEventListener('mouseenter', showControls);
    videoContainer.addEventListener('mousemove', showControls);
    
    // Initialize progress bar
    progressBar.style.width = '0%';
}

function initProductZoom() {
    var images = document.querySelectorAll('.product-img');
    if (!images.length) return;

    var box = document.createElement('div');
    box.className = 'product-zoom';
    box.innerHTML = '<button type="button" class="product-zoom-close" aria-label="إغلاق">&times;</button><img alt="">';
    document.body.appendChild(box);
    var big = box.querySelector('img');

    function closeZoom() {
        box.classList.remove('show');
        document.body.style.overflow = '';
    }

    function openZoom(el) {
        big.src = el.currentSrc || el.src;
        big.alt = el.alt || '';
        box.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    images.forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            openZoom(el);
        });
    });

    box.addEventListener('click', function (e) {
        if (e.target === box || e.target.classList.contains('product-zoom-close')) {
            closeZoom();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeZoom();
    });
}

// Initialize products slider when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initProductsSlider();
    initVideoControls();
    initProductZoom();
    
    // Enhanced video initialization
    const heroVideo = document.getElementById('heroVideo');
    if (heroVideo) {
        console.log('Hero video found, initializing...');
        
        // Set video properties for better compatibility
        heroVideo.muted = true;
        heroVideo.loop = true;
        heroVideo.playsInline = true;
        
        // Try to play video with error handling
        const playVideo = async () => {
            try {
                await heroVideo.play();
                console.log('Video started playing successfully');
            } catch (error) {
                console.log('Video autoplay failed:', error);
                
                // Show play button to user
                const playBtn = document.getElementById('playPauseBtn');
                if (playBtn) {
                    playBtn.style.display = 'flex';
                    playBtn.innerHTML = '<i class="fas fa-play"></i>';
                }
                
                // Try to play on first user interaction
                const enableVideo = () => {
                    heroVideo.play().then(() => {
                        console.log('Video started on user interaction');
                        if (playBtn) {
                            playBtn.innerHTML = '<i class="fas fa-pause"></i>';
                        }
                    }).catch(console.log);
                    
                    // Remove event listeners after first interaction
                    document.removeEventListener('click', enableVideo);
                    document.removeEventListener('touchstart', enableVideo);
                    document.removeEventListener('keydown', enableVideo);
                };
                
                document.addEventListener('click', enableVideo);
                document.addEventListener('touchstart', enableVideo);
                document.addEventListener('keydown', enableVideo);
            }
        };
        
        // Wait for video to be ready
        if (heroVideo.readyState >= 2) {
            playVideo();
        } else {
            heroVideo.addEventListener('loadeddata', playVideo);
        }
        
        // Fallback if video fails to load
        heroVideo.addEventListener('error', (e) => {
            console.error('Video failed to load:', e);
            // Show fallback content
            const videoContainer = heroVideo.parentElement;
            if (videoContainer) {
                videoContainer.innerHTML = `
                    <div style="display: flex; align-items: center; justify-content: center; height: 100%; background: linear-gradient(135deg, #ff6b9d, #4ecdc4); color: white; border-radius: 23px; background-image: url('assets/img/home.jpg'); background-size: cover; background-position: center;">
                        <div style="text-align: center; background: rgba(0,0,0,0.7); padding: 2rem; border-radius: 15px;">
                            <i class="fas fa-image" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                            <p>صورة بديلة</p>
                        </div>
                    </div>
                `;
            }
        });
    } else {
        console.log('Hero video not found');
    }
});

// Header scroll effect with hide/show functionality
let lastScrollTop = 0;
const header = document.querySelector('.header');

window.addEventListener('scroll', () => {
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
    
    if (scrollTop > lastScrollTop && scrollTop > 100) {
        // Scrolling down - hide header
        header.classList.remove('show');
    } else {
        // Scrolling up - show header
        header.classList.add('show');
    }
    
    lastScrollTop = scrollTop;
});

// Show header on page load
window.addEventListener('load', () => {
    header.classList.add('show');
});

// Enable video sound on first user interaction
let soundEnabled = false;
function enableVideoSound() {
    if (!soundEnabled) {
        const video = document.getElementById('heroVideo');
        if (video) {
            video.muted = false;
            soundEnabled = true;
            console.log('Video sound enabled on user interaction');
        }
    }
}

// Listen for user interactions to enable sound
document.addEventListener('click', enableVideoSound);
document.addEventListener('touchstart', enableVideoSound);
document.addEventListener('keydown', enableVideoSound);

// Add animation on scroll
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
        }
    });
}, observerOptions);

// Observe elements for animation
document.addEventListener('DOMContentLoaded', () => {
    const animatedElements = document.querySelectorAll('.feature-card, .hero-content, .hero-image');
    
    animatedElements.forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(30px)';
        el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(el);
    });
});

// Add loading animation
window.addEventListener('load', () => {
    document.body.style.opacity = '1';
});

// Mobile-specific optimizations
document.addEventListener('DOMContentLoaded', () => {
    // Add touch-friendly classes for mobile devices
    if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
        document.body.classList.add('touch-device');
    }
    
    // Optimize images for mobile
    const images = document.querySelectorAll('img');
    images.forEach(img => {
        img.loading = 'lazy';
        img.decoding = 'async';
    });
    
    // Add mobile-specific event listeners
    if (window.innerWidth <= 768) {
        // Prevent zoom on double tap for iOS
        let lastTouchEnd = 0;
        document.addEventListener('touchend', function (event) {
            const now = (new Date()).getTime();
            if (now - lastTouchEnd <= 300) {
                event.preventDefault();
            }
            lastTouchEnd = now;
        }, false);
        
        // Improve touch scrolling
        document.body.style.webkitOverflowScrolling = 'touch';
    }
});

// Initialize page
document.addEventListener('DOMContentLoaded', () => {
    document.body.style.opacity = '0';
    document.body.style.transition = 'opacity 0.5s ease';
    
    setTimeout(() => {
        document.body.style.opacity = '1';
    }, 100);
});

// Image Modal Functions
function openImageModal(imageSrc) {
    const modal = document.getElementById('imageModal');
    const modalImage = document.getElementById('modalImage');
    
    modalImage.src = imageSrc;
    modal.style.display = 'block';
    
    // Prevent body scroll when modal is open
    document.body.style.overflow = 'hidden';
}

function closeImageModal() {
    const modal = document.getElementById('imageModal');
    modal.style.display = 'none';
    
    // Restore body scroll
    document.body.style.overflow = 'auto';
}

// Close modal when clicking outside the image
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('imageModal');
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeImageModal();
            }
        });
    }
    
    // Close modal with Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeImageModal();
        }
    });
});
