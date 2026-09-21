(function () {
  'use strict';
  var galleryViewer = document.getElementById('gallery-viewer');
  if (galleryViewer) {
    var viewerStage = document.getElementById('gallery-viewer-stage');
    var viewerMedia = document.getElementById('gallery-viewer-media');
    var viewerClose = galleryViewer.querySelector('.gallery-viewer__close');
    var viewerPrev = galleryViewer.querySelector('.gallery-viewer__prev');
    var viewerNext = galleryViewer.querySelector('.gallery-viewer__next');
    var viewerCount = document.getElementById('gallery-viewer-count');
    var cardStates = [], viewerItems = [], viewerIndex = 0, viewerTimer = 0, closeIntroTimer = 0, viewerTrigger = null, dragStart = null;
    function playSilent(video) {
      video.muted = true;
      var playback = video.play();
      if (playback && typeof playback.catch === 'function') playback.catch(function () { });
    }
    function stopCard(state) {
      window.clearTimeout(state.timer);
      state.items.forEach(function (item) { if (item.tagName === 'VIDEO') item.pause() });
    }
    function playCard(state) {
      stopCard(state);
      if (!state.visible || document.hidden || galleryViewer.open) return;
      var item = state.items[state.index];
      if (item.tagName === 'VIDEO') playSilent(item);
      else if (state.items.length > 1) state.timer = window.setTimeout(function () { showCardItem(state, state.index + 1) }, 5000);
    }
    function showCardItem(state, index) {
      stopCard(state);
      state.index = (index + state.items.length) % state.items.length;
      state.items.forEach(function (item, i) {
        item.classList.toggle('is-active', i === state.index);
        if (state.items.length > 1) item.setAttribute('aria-hidden', String(i !== state.index));
      });
      var current = state.items[state.index];
      if (current.tagName === 'VIDEO') current.currentTime = 0;
      playCard(state);
    }
    document.querySelectorAll('.gallery-card').forEach(function (card) {
      var items = Array.prototype.slice.call(card.querySelectorAll('img, video'));
      if (!items.length) return;
      var state = { card: card, items: items, index: 0, visible: false, timer: 0 };
      cardStates.push(state);
      items.forEach(function (item) {
        if (item.tagName === 'VIDEO') {
          item.muted = true;
          item.loop = items.length === 1;
          if (items.length > 1) item.addEventListener('ended', function () {
            if (state.items[state.index] === item) showCardItem(state, state.index + 1);
          });
        }
      });
      var openButton = document.createElement('button');
      openButton.type = 'button';
      openButton.className = 'gallery-card__open';
      openButton.setAttribute('aria-label', 'Ampliar ' + card.querySelector('figcaption b').textContent);
      openButton.setAttribute('aria-haspopup', 'dialog');
      openButton.setAttribute('aria-controls', 'gallery-viewer');
      openButton.addEventListener('click', function () { openGalleryViewer(state, openButton) });
      card.appendChild(openButton);
    });
    if ('IntersectionObserver' in window) {
      var cardObserver = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          var state = cardStates.find(function (candidate) { return candidate.card === entry.target });
          if (!state) return;
          state.visible = entry.isIntersecting;
          playCard(state);
        });
      }, { rootMargin: '100px 0px', threshold: .01 });
      cardStates.forEach(function (state) { cardObserver.observe(state.card) });
    } else {
      cardStates.forEach(function (state) { state.visible = true; playCard(state) });
    }
    document.addEventListener('visibilitychange', function () {
      cardStates.forEach(playCard);
      if (galleryViewer.open) {
        var media = viewerMedia.querySelector('video');
        if (media) { if (document.hidden) media.pause(); else playSilent(media) }
      }
    });
    function showViewerItem(index) {
      window.clearTimeout(viewerTimer);
      var previous = viewerMedia.querySelector('video');
      if (previous) previous.pause();
      viewerIndex = (index + viewerItems.length) % viewerItems.length;
      var source = viewerItems[viewerIndex], media;
      if (source.tagName === 'VIDEO') {
        media = document.createElement('video');
        media.src = source.querySelector('source').getAttribute('src');
        media.poster = source.getAttribute('poster') || '';
        media.muted = true;
        media.defaultMuted = true;
        media.autoplay = true;
        media.playsInline = true;
        media.setAttribute('webkit-playsinline', '');
        media.setAttribute('disablepictureinpicture', '');
        media.loop = viewerItems.length === 1;
        if (viewerItems.length > 1) media.addEventListener('ended', function () { showViewerItem(viewerIndex + 1) });
      } else {
        media = document.createElement('img');
        media.src = source.getAttribute('src');
        media.alt = source.alt;
      }
      viewerMedia.replaceChildren(media);
      viewerCount.textContent = viewerItems.length > 1 ? (viewerIndex + 1) + ' de ' + viewerItems.length : '1 de 1';
      if (source.tagName === 'VIDEO') playSilent(media);
      else if (viewerItems.length > 1) viewerTimer = window.setTimeout(function () { showViewerItem(viewerIndex + 1) }, 5000);
    }
    function openGalleryViewer(state, trigger) {
      viewerItems = state.items;
      viewerTrigger = trigger;
      document.getElementById('gallery-viewer-title').textContent = state.card.querySelector('figcaption b').textContent;
      viewerPrev.hidden = viewerItems.length < 2;
      viewerNext.hidden = viewerItems.length < 2;
      galleryViewer.showModal();
      document.body.classList.add('modal-open');
      cardStates.forEach(stopCard);
      viewerClose.classList.add('is-intro');
      window.clearTimeout(closeIntroTimer);
      closeIntroTimer = window.setTimeout(function () { viewerClose.classList.remove('is-intro') }, 3000);
      showViewerItem(state.index);
      viewerClose.focus();
    }
    viewerClose.addEventListener('click', function () { galleryViewer.close() });
    viewerPrev.addEventListener('click', function () { showViewerItem(viewerIndex - 1) });
    viewerNext.addEventListener('click', function () { showViewerItem(viewerIndex + 1) });
    galleryViewer.addEventListener('keydown', function (event) {
      if (viewerItems.length < 2) return;
      if (event.key === 'ArrowLeft') { event.preventDefault(); showViewerItem(viewerIndex - 1) }
      if (event.key === 'ArrowRight') { event.preventDefault(); showViewerItem(viewerIndex + 1) }
    });
    galleryViewer.addEventListener('close', function () {
      window.clearTimeout(viewerTimer);
      window.clearTimeout(closeIntroTimer);
      viewerClose.classList.remove('is-intro');
      var media = viewerMedia.querySelector('video');
      if (media) media.pause();
      viewerMedia.replaceChildren();
      viewerItems = [];
      dragStart = null;
      viewerMedia.style.transform = '';
      viewerMedia.style.opacity = '';
      document.body.classList.remove('modal-open');
      cardStates.forEach(playCard);
      if (viewerTrigger) viewerTrigger.focus();
    });
    viewerStage.addEventListener('pointerdown', function (event) {
      if (event.target.closest('button')) return;
      dragStart = { x: event.clientX, y: event.clientY, id: event.pointerId };
      viewerStage.setPointerCapture(event.pointerId);
    });
    viewerStage.addEventListener('pointermove', function (event) {
      if (!dragStart || event.pointerId !== dragStart.id) return;
      var distance = event.clientY - dragStart.y;
      viewerMedia.style.transform = 'translateY(' + Math.max(-140, Math.min(140, distance * .45)) + 'px)';
      viewerMedia.style.opacity = String(Math.max(.5, 1 - Math.abs(distance) / 450));
    });
    function finishViewerDrag(event) {
      if (!dragStart || event.pointerId !== dragStart.id) return;
      var dx = event.clientX - dragStart.x, dy = event.clientY - dragStart.y;
      dragStart = null;
      viewerMedia.style.transform = '';
      viewerMedia.style.opacity = '';
      if (Math.abs(dy) > 85 && Math.abs(dy) > Math.abs(dx) * 1.15) galleryViewer.close();
      else if (viewerItems.length > 1 && Math.abs(dx) > 70 && Math.abs(dx) > Math.abs(dy)) showViewerItem(viewerIndex + (dx < 0 ? 1 : -1));
    }
    viewerStage.addEventListener('pointerup', finishViewerDrag);
    viewerStage.addEventListener('pointercancel', function () {
      dragStart = null;
      viewerMedia.style.transform = '';
      viewerMedia.style.opacity = '';
    });
  }
}());
