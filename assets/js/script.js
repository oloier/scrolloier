window.addEventListener('load', function () {
    document.querySelectorAll('.loader').forEach(function (el) {
        el.classList.remove('loader');
    });
});

document.addEventListener('DOMContentLoaded', function () {
    var newDlg   = document.getElementById('new');
    var lightbox = document.getElementById('lightbox');

    if (newDlg) {
        document.getElementById('add').addEventListener('click', function () {
            newDlg.showModal();
        });
        var closeBtn = document.getElementById('close-new');
        if (closeBtn) closeBtn.addEventListener('click', function () { newDlg.close(); });
        newDlg.addEventListener('click', function (e) {
            if (e.target === newDlg) newDlg.close();
        });
    }

    if (lightbox) {
        var lbImg = lightbox.querySelector('img');
        document.addEventListener('click', function (e) {
            var a = e.target.closest('article a[rel="lightbox"]');
            if (!a) return;
            e.preventDefault();
            lbImg.src = a.href;
            lightbox.showModal();
        });
        lightbox.addEventListener('click', function () { lightbox.close(); });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('article img[data-embed] + a');
        if (!btn) return;
        e.preventDefault();
        var img = btn.previousElementSibling;
        var wrapper = document.createElement('div');
        wrapper.innerHTML = img.getAttribute('data-embed');
        img.parentNode.replaceChild(wrapper.firstElementChild || wrapper, img);
        btn.remove();
    });
});
