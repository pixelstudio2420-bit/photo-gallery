// ============================================
// Photo Gallery — App JS (Modern)
// ============================================

// ============================================
// Navbar scroll effect
// ============================================
(function() {
    const nav = document.getElementById('mainNav');
    if (!nav) return;
    const onScroll = () => {
        nav.classList.toggle('scrolled', window.scrollY > 50);
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
})();

// ============================================
// Scroll animations (IntersectionObserver)
// ============================================
(function() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.05 });

    // Use requestAnimationFrame to ensure layout is complete before observing
    requestAnimationFrame(() => {
        document.querySelectorAll('.fade-up').forEach(el => observer.observe(el));
    });
})();

// ============================================
// Auto-dismiss alerts (legacy fallback)
// ============================================
document.querySelectorAll('.alert-dismissible').forEach(el => {
    setTimeout(() => {
        const alert = bootstrap.Alert.getOrCreateInstance(el);
        alert?.close();
    }, 5000);
});

// ============================================
// SweetAlert2 — Global helpers
// ============================================

/**
 * SweetAlert2 confirm dialog for forms.
 * Usage: <form data-confirm="ยืนยันลบ?" ...>
 */
document.addEventListener('submit', function(e) {
    const form = e.target;
    const message = form.dataset.confirm;
    if (!message) return;
    if (form._swalConfirmed) { delete form._swalConfirmed; return; }
    e.preventDefault();
    Swal.fire({
        title: 'ยืนยัน',
        text: message,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'ยืนยัน',
        cancelButtonText: 'ยกเลิก',
        reverseButtons: true
    }).then(result => {
        if (result.isConfirmed) {
            form._swalConfirmed = true;
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        }
    });
});

/**
 * Global swalAlert — replacement for native alert()
 */
function swalAlert(message, icon = 'warning') {
    return Swal.fire({ text: message, icon: icon, confirmButtonText: 'ตกลง' });
}

/**
 * Global swalToast — quick toast notification
 */
function swalToast(message, icon = 'success') {
    Swal.fire({
        toast: true, position: 'top-end', showConfirmButton: false,
        timer: 3000, timerProgressBar: true, icon: icon, title: message
    });
}

// ============================================
// Cart
// ============================================
const Cart = {
    items: JSON.parse(localStorage.getItem('cart_items') || '[]'),

    add(photoId, photoData) {
        if (!this.items.find(i => i.id === photoId)) {
            this.items.push({ id: photoId, ...photoData });
            this.save();
            this.updateBadge();
            this.showToast('เพิ่มรูปลงตะกร้าแล้ว', 'success');
        }
    },

    remove(photoId) {
        this.items = this.items.filter(i => i.id !== photoId);
        this.save();
        this.updateBadge();
    },

    has(photoId) {
        return !!this.items.find(i => i.id === photoId);
    },

    clear() {
        this.items = [];
        this.save();
        this.updateBadge();
    },

    save() {
        localStorage.setItem('cart_items', JSON.stringify(this.items));
    },

    updateBadge() {
        const badge = document.querySelector('.cart-badge');
        if (badge) {
            badge.textContent = this.items.length;
            badge.style.display = this.items.length > 0 ? 'inline' : 'none';
        }
    },

    showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-bg-${type} border-0 show`;
        toast.style.cssText = 'position:fixed;bottom:80px;right:20px;z-index:9999;min-width:240px;border-radius:12px;animation:fadeIn 0.3s ease';
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body"><i class="bi bi-check-circle me-2"></i>${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="this.closest('.toast').remove()"></button>
            </div>`;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
};

Cart.updateBadge();

// ============================================
// Gallery — Photo selection
// ============================================
document.querySelectorAll('.gallery-item[data-photo-id]:not([data-cart-bound])').forEach(item => {
    item.setAttribute('data-cart-bound', '1');
    const photoId = item.dataset.photoId;

    if (Cart.has(photoId)) {
        item.classList.add('selected');
    }

    item.querySelector('.btn-select')?.addEventListener('click', e => {
        e.stopPropagation();
        if (Cart.has(photoId)) {
            Cart.remove(photoId);
            item.classList.remove('selected');
        } else {
            Cart.add(photoId, {
                thumbnail: item.dataset.thumbnail,
                price: item.dataset.price,
            });
            item.classList.add('selected');
        }
    });
});

// ============================================
// Lightbox
// ============================================
document.querySelectorAll('.btn-preview:not([data-preview-bound])').forEach(btn => {
    btn.setAttribute('data-preview-bound', '1');
    btn.addEventListener('click', e => {
        e.stopPropagation();
        const src = btn.dataset.src || btn.closest('[data-src]')?.dataset.src;
        if (!src) return;

        // XSS protection — only allow http(s) URLs
        try {
            const url = new URL(src, window.location.origin);
            if (url.protocol !== 'http:' && url.protocol !== 'https:') return;
        } catch { return; }

        const overlay = document.createElement('div');
        overlay.className = 'lightbox-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-label', 'Image Preview');

        const closeBtn = document.createElement('button');
        closeBtn.className = 'btn btn-light position-absolute top-0 end-0 m-3 rounded-circle shadow';
        closeBtn.style.cssText = 'z-index:1;width:44px;height:44px';
        closeBtn.setAttribute('aria-label', 'Close');
        closeBtn.innerHTML = '<i class="bi bi-x-lg"></i>';

        const img = document.createElement('img');
        img.src = src;
        img.alt = 'Preview';
        img.loading = 'lazy';

        overlay.appendChild(closeBtn);
        overlay.appendChild(img);

        function closeLightbox() {
            overlay.style.opacity = '0';
            overlay.style.transition = 'opacity 0.2s ease';
            setTimeout(() => overlay.remove(), 200);
            document.removeEventListener('keydown', onKeyDown);
        }

        function onKeyDown(ev) {
            if (ev.key === 'Escape') closeLightbox();
        }

        overlay.addEventListener('click', ev => {
            if (ev.target === overlay || ev.target.closest('button')) closeLightbox();
        });

        document.addEventListener('keydown', onKeyDown);
        document.body.appendChild(overlay);
        closeBtn.focus();
    });
});
