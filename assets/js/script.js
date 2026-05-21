window.addEventListener('load', function () {
    document.querySelectorAll('.loader').forEach(function (el) {
        el.classList.remove('loader');
    });
});

function timeAgo(dtStr) {
    var d    = new Date(dtStr);
    var diff = (Date.now() - d) / 1000;
    if (diff < 60)     return 'just now';
    if (diff < 3600)   return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400)  return Math.floor(diff / 3600) + 'h ago';
    if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

document.addEventListener('DOMContentLoaded', function () {

    // new post drawer
    var newForm   = document.getElementById('new-post');
    var toggleBtn = document.getElementById('toggle-new');
    function closeDrawer() {
        newForm.classList.remove('open');
        toggleBtn.textContent = '+ new post';
    }
    if (newForm && toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            var open = newForm.classList.toggle('open');
            toggleBtn.textContent = open ? '× close' : '+ new post';
        });
        document.addEventListener('click', function (e) {
            if (newForm.classList.contains('open') && !newForm.contains(e.target) && !toggleBtn.contains(e.target)) {
                closeDrawer();
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && newForm.classList.contains('open')) closeDrawer();
        });

        newForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var urlVal  = newForm.querySelector('[name="url"]');
            var fileVal = newForm.querySelector('[name="file"]');
            if (!urlVal.value.trim() && !fileVal.files.length) {
                urlVal.classList.add('shake');
                urlVal.addEventListener('animationend', function () { urlVal.classList.remove('shake'); }, { once: true });
                return;
            }
            var data = new FormData(newForm);
            data.append('_ajax', '1');
            fetch(newForm.action, { method: 'POST', body: data })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.ok || !res.html) return;
                    var posts = document.getElementById('posts');
                    if (posts) {
                        posts.insertAdjacentHTML('afterbegin', res.html);
                        var newTime = posts.firstElementChild.querySelector('time[datetime]');
                        if (newTime) { newTime.textContent = timeAgo(newTime.getAttribute('datetime')); }
                    }
                    newForm.reset();
                    closeDrawer();
                })
                .catch(function () {});
        });
    }

    // dead image detection
    document.querySelectorAll('article img').forEach(function (img) {
        img.addEventListener('error', function () {
            var art = this.closest('article');
            if (art) art.classList.add('img-dead');
            var a = this.closest('a[href]');
            if (a) {
                var link = document.createElement('a');
                link.href = a.href;
                link.className = 'regular';
                link.textContent = a.href;
                link.target = '_blank';
                link.rel = 'noopener';
                art.appendChild(link);
            }
        });
    });

    // image lightbox
    var lightbox = document.getElementById('lightbox');
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

    // embed play button
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

    // ajax comment submit
    document.addEventListener('submit', function (e) {
        var form = e.target.closest('dd form');
        if (!form) return;
        e.preventDefault();

        var data = new URLSearchParams(new FormData(form));
        data.append('_ajax', '1');

        fetch(form.action, { method: 'POST', body: data })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.ok) return;
                var details = form.closest('details');
                var ul  = details.querySelector('ul');
                var li  = document.createElement('li');
                li.innerHTML = (res.name ? '<b>' + res.name + '</b>' : '') + res.comment;
                ul.appendChild(li);
                var counter = details.querySelector('summary var');
                counter.textContent = parseInt(counter.textContent) + 1;
                counter.classList.add('active');
                form.reset();
            })
            .catch(function () {});
    });

    document.querySelectorAll('time[datetime]').forEach(function (el) {
        var dt = el.getAttribute('datetime');
        el.textContent = timeAgo(dt);
        el.title = dt;
    });

    // new-since-last-visit badges
    var lastVisit = parseInt(localStorage.getItem('lastVisit') || '0');
    document.querySelectorAll('.item').forEach(function (dl) {
        var time = dl.querySelector('time[datetime]');
        if (!time || !lastVisit) return;
        var postDate = new Date(time.getAttribute('datetime'));
        if (postDate.getTime() > lastVisit) dl.classList.add('is-new');
    });
    localStorage.setItem('lastVisit', Date.now());

});
