window.addEventListener('load', function () {
    document.querySelectorAll('.loader').forEach(function (el) {
        el.classList.remove('loader');
    });
});

document.addEventListener('DOMContentLoaded', function () {

    // new post drawer
    var newForm   = document.getElementById('new-post');
    var toggleBtn = document.getElementById('toggle-new');
    if (newForm && toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            var open = newForm.classList.toggle('open');
            toggleBtn.textContent = open ? '× close' : '+ new post';
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && newForm.classList.contains('open')) {
                newForm.classList.remove('open');
                toggleBtn.textContent = '+ new post';
            }
        });
    }

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
                li.innerHTML = '<b>' + res.name + '</b><p>' + res.comment + '</p>';
                ul.appendChild(li);
                var counter = details.querySelector('summary var');
                counter.textContent = parseInt(counter.textContent) + 1;
                counter.classList.add('active');
                form.reset();
            })
            .catch(function () {});
    });

    // relative timestamps
    function timeAgo(dtStr) {
        var d    = new Date(dtStr);
        var diff = (Date.now() - d) / 1000;
        if (diff < 60)     return 'just now';
        if (diff < 3600)   return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400)  return Math.floor(diff / 3600) + 'h ago';
        if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

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
