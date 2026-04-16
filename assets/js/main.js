// ============================================================
// Gyansetu Library Management System
// Main JavaScript File
// ============================================================

document.addEventListener('DOMContentLoaded', function () {

    // ── Mobile Nav Toggle ──────────────────────────────────
    const navToggle = document.getElementById('navToggle');
    const navLinks  = document.getElementById('navLinks');

    if (navToggle && navLinks) {
        navToggle.addEventListener('click', function () {
            navLinks.classList.toggle('open');
        });

        // Close nav when clicking outside
        document.addEventListener('click', function (e) {
            if (!navToggle.contains(e.target) && !navLinks.contains(e.target)) {
                navLinks.classList.remove('open');
            }
        });
    }

    // ── Auto-dismiss alerts after 5 seconds ───────────────
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-8px)';
            alert.style.transition = 'all .4s ease';
            setTimeout(() => alert.remove(), 400);
        }, 5000);
    });

    // ── Modal Open/Close ───────────────────────────────────
    // Open modal: data-modal-target="#modalId"
    document.querySelectorAll('[data-modal-target]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const modalId = this.getAttribute('data-modal-target');
            const modal   = document.querySelector(modalId);
            if (modal) modal.classList.add('active');
        });
    });

    // Close modal: .modal-close or clicking overlay
    document.querySelectorAll('.modal-close, .modal-overlay').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (e.target === this || this.classList.contains('modal-close')) {
                const overlay = this.closest('.modal-overlay') || this.querySelector('.modal-overlay') || this;
                if (overlay.classList) overlay.classList.remove('active');
            }
        });
    });

    // ── Client-Side Form Validation ────────────────────────
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(function (form) {
        form.addEventListener('submit', function (e) {
            let valid = true;

            // Clear previous errors
            form.querySelectorAll('.form-error').forEach(el => el.remove());
            form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

            // Validate required fields
            form.querySelectorAll('[required]').forEach(function (field) {
                if (!field.value.trim()) {
                    showFieldError(field, 'This field is required.');
                    valid = false;
                }
            });

            // Validate email fields
            form.querySelectorAll('[type="email"]').forEach(function (field) {
                if (field.value && !isValidEmail(field.value)) {
                    showFieldError(field, 'Please enter a valid email address.');
                    valid = false;
                }
            });

            // Validate password confirmation
            const pass = form.querySelector('#password');
            const conf = form.querySelector('#confirm_password');
            if (pass && conf && pass.value !== conf.value) {
                showFieldError(conf, 'Passwords do not match.');
                valid = false;
            }

            // Validate password length
            if (pass && pass.value && pass.value.length < 6) {
                showFieldError(pass, 'Password must be at least 6 characters.');
                valid = false;
            }

            if (!valid) e.preventDefault();
        });
    });

    function showFieldError(field, msg) {
        field.classList.add('is-invalid');
        const err = document.createElement('div');
        err.className = 'form-error';
        err.textContent = msg;
        field.parentNode.appendChild(err);
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    // ── Image Preview for Book Upload ─────────────────────
    const imgInput  = document.getElementById('book_image');
    const imgPreview = document.getElementById('imagePreview');
    if (imgInput && imgPreview) {
        imgInput.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = e => { imgPreview.src = e.target.result; imgPreview.style.display = 'block'; };
                reader.readAsDataURL(file);
            }
        });
    }

    // ── Confirm Delete Dialogs ─────────────────────────────
    document.querySelectorAll('[data-confirm]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            const msg = this.getAttribute('data-confirm') || 'Are you sure?';
            if (!confirm(msg)) e.preventDefault();
        });
    });

    // ── Search: highlight matching terms ──────────────────
    const highlighted = document.querySelectorAll('[data-highlight]');
    const searchQuery = document.getElementById('searchQuery');
    if (searchQuery && highlighted.length) {
        const term = searchQuery.value.trim();
        if (term) {
            highlighted.forEach(function (el) {
                el.innerHTML = el.textContent.replace(
                    new RegExp('(' + escapeRegExp(term) + ')', 'gi'),
                    '<mark>$1</mark>'
                );
            });
        }
    }

    function escapeRegExp(str) {
        return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    // ── Password toggle visibility ─────────────────────────
    document.querySelectorAll('.toggle-password').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const input = document.querySelector(this.getAttribute('data-target'));
            if (input) {
                const isText = input.type === 'text';
                input.type = isText ? 'password' : 'text';
                this.querySelector('i').className = isText ? 'fas fa-eye' : 'fas fa-eye-slash';
            }
        });
    });

});