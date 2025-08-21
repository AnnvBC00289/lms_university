(function () {
	function onScroll() {
		var items = document.querySelectorAll('.timeline-item');
		var trigger = window.innerHeight * 0.9;
		for (var i = 0; i < items.length; i++) {
			var rect = items[i].getBoundingClientRect();
			if (rect.top < trigger) items[i].classList.add('show');
		}
	}
	window.addEventListener('scroll', onScroll, { passive: true });
	document.addEventListener('DOMContentLoaded', onScroll);

	// Ripple effect for .btn-primary
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.btn-primary');
		if (!btn) return;
		var circle = document.createElement('span');
		var d = Math.max(btn.clientWidth, btn.clientHeight);
		circle.style.width = circle.style.height = d + 'px';
		circle.style.position = 'absolute';
		circle.style.left = (e.clientX - btn.getBoundingClientRect().left - d / 2) + 'px';
		circle.style.top = (e.clientY - btn.getBoundingClientRect().top - d / 2) + 'px';
		circle.style.background = 'rgba(255,255,255,.35)';
		circle.style.borderRadius = '50%';
		circle.style.pointerEvents = 'none';
		circle.style.transform = 'scale(0)';
		circle.style.transition = 'transform .4s ease, opacity .6s ease';
		circle.className = 'btn-ripple';
		btn.style.position = 'relative';
		btn.style.overflow = 'hidden';
		btn.appendChild(circle);
		requestAnimationFrame(function(){ circle.style.transform = 'scale(2)'; circle.style.opacity = '0'; });
		setTimeout(function(){ circle.remove(); }, 600);
	});

	// Sidebar drag-to-resize (desktop)
	(function setupSidebarResizer(){
		var sidebar = document.querySelector('.sidebar');
		if (!sidebar) return;
		var resizer = document.createElement('div');
		resizer.className = 'sidebar-resizer';
		sidebar.appendChild(resizer);
		var startX, startWidth;
		var minW = 160, maxW = 420;
		function onMove(e){
			var dx = e.clientX - startX;
			var w = Math.min(maxW, Math.max(minW, startWidth + dx));
			document.documentElement.style.setProperty('--sidebar-width', w + 'px');
		}
		function onUp(){
			document.body.classList.remove('resizing');
			document.removeEventListener('mousemove', onMove);
			document.removeEventListener('mouseup', onUp);
			try { localStorage.setItem('lms_sidebar_width', getComputedStyle(document.documentElement).getPropertyValue('--sidebar-width').trim()); } catch(_) {}
		}
		resizer.addEventListener('mousedown', function(e){
			startX = e.clientX; startWidth = sidebar.getBoundingClientRect().width;
			document.body.classList.add('resizing');
			document.addEventListener('mousemove', onMove);
			document.addEventListener('mouseup', onUp);
		});
		try {
			var saved = localStorage.getItem('lms_sidebar_width');
			if (saved) document.documentElement.style.setProperty('--sidebar-width', saved);
		} catch(_) {}
	})();
})();
