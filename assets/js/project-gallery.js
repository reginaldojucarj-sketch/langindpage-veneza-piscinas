(function () {
  'use strict';

  const IMAGE_DURATION_MS = 5000;
  const CLOSE_INTRO_DURATION_MS = 3000;
  const VERTICAL_CLOSE_DISTANCE_PX = 85;
  const HORIZONTAL_NAV_DISTANCE_PX = 70;
  const galleryViewer = document.getElementById('gallery-viewer');

  if (!galleryViewer) return;

  const viewerStage = document.getElementById('gallery-viewer-stage');
  const viewerMedia = document.getElementById('gallery-viewer-media');
  const viewerTitle = document.getElementById('gallery-viewer-title');
  const viewerCount = document.getElementById('gallery-viewer-count');
  const viewerCloseButton = galleryViewer.querySelector('.gallery-viewer__close');
  const viewerPreviousButton = galleryViewer.querySelector('.gallery-viewer__prev');
  const viewerNextButton = galleryViewer.querySelector('.gallery-viewer__next');

  if (!viewerStage || !viewerMedia || !viewerTitle || !viewerCount ||
    !viewerCloseButton || !viewerPreviousButton || !viewerNextButton) return;

  const cardStates = [];
  const stateByCard = new Map();
  let viewerItems = [];
  let viewerIndex = 0;
  let viewerTimer;
  let closeIntroTimer;
  let viewerTrigger = null;
  let dragStart = null;

  function playMuted(video) {
    video.muted = true;
    const playback = video.play();
    if (playback && typeof playback.catch === 'function') playback.catch(function () { });
  }

  function stopCard(state) {
    window.clearTimeout(state.timer);
    state.items.forEach(function (item) {
      if (item.tagName === 'VIDEO') item.pause();
    });
  }

  function playCard(state) {
    stopCard(state);
    if (!state.visible || document.hidden || galleryViewer.open) return;

    const currentItem = state.items[state.index];
    if (currentItem.tagName === 'VIDEO') {
      playMuted(currentItem);
    } else if (state.items.length > 1) {
      state.timer = window.setTimeout(function () {
        showCardItem(state, state.index + 1);
      }, IMAGE_DURATION_MS);
    }
  }

  function showCardItem(state, index) {
    stopCard(state);
    state.index = (index + state.items.length) % state.items.length;

    state.items.forEach(function (item, itemIndex) {
      const isActive = itemIndex === state.index;
      item.classList.toggle('is-active', isActive);
      if (state.items.length > 1) item.setAttribute('aria-hidden', String(!isActive));
    });

    const currentItem = state.items[state.index];
    if (currentItem.tagName === 'VIDEO') currentItem.currentTime = 0;
    playCard(state);
  }

  function createViewerMedia(source) {
    if (source.tagName !== 'VIDEO') {
      const image = document.createElement('img');
      image.src = source.getAttribute('src');
      image.alt = source.alt;
      return image;
    }

    const video = document.createElement('video');
    const sourceElement = source.querySelector('source');
    video.src = sourceElement ? sourceElement.getAttribute('src') : source.getAttribute('src');
    video.poster = source.getAttribute('poster') || '';
    video.muted = true;
    video.defaultMuted = true;
    video.autoplay = true;
    video.playsInline = true;
    video.preload = 'metadata';
    video.setAttribute('webkit-playsinline', '');
    video.setAttribute('disablepictureinpicture', '');
    video.loop = viewerItems.length === 1;

    if (viewerItems.length > 1) {
      video.addEventListener('ended', function () {
        showViewerItem(viewerIndex + 1);
      });
    }

    return video;
  }

  function showViewerItem(index) {
    window.clearTimeout(viewerTimer);
    const previousVideo = viewerMedia.querySelector('video');
    if (previousVideo) previousVideo.pause();

    viewerIndex = (index + viewerItems.length) % viewerItems.length;
    const source = viewerItems[viewerIndex];
    const media = createViewerMedia(source);

    viewerMedia.replaceChildren(media);
    viewerCount.textContent = viewerItems.length > 1
      ? (viewerIndex + 1) + ' de ' + viewerItems.length
      : '1 de 1';

    if (source.tagName === 'VIDEO') {
      playMuted(media);
    } else if (viewerItems.length > 1) {
      viewerTimer = window.setTimeout(function () {
        showViewerItem(viewerIndex + 1);
      }, IMAGE_DURATION_MS);
    }
  }

  function openGalleryViewer(state, trigger) {
    const title = state.card.querySelector('figcaption b');
    viewerItems = state.items;
    viewerTrigger = trigger;
    viewerTitle.textContent = title ? title.textContent : 'Galeria de projetos';
    viewerPreviousButton.hidden = viewerItems.length < 2;
    viewerNextButton.hidden = viewerItems.length < 2;

    galleryViewer.showModal();
    document.body.classList.add('modal-open');
    cardStates.forEach(stopCard);

    viewerCloseButton.classList.add('is-intro');
    window.clearTimeout(closeIntroTimer);
    closeIntroTimer = window.setTimeout(function () {
      viewerCloseButton.classList.remove('is-intro');
    }, CLOSE_INTRO_DURATION_MS);

    showViewerItem(state.index);
    viewerCloseButton.focus();
  }

  document.querySelectorAll('.gallery-card').forEach(function (card) {
    const items = Array.from(card.querySelectorAll('img, video'));
    const title = card.querySelector('figcaption b');
    if (!items.length) return;

    const state = { card: card, items: items, index: 0, visible: false, timer: 0 };
    cardStates.push(state);
    stateByCard.set(card, state);

    items.forEach(function (item) {
      if (item.tagName !== 'VIDEO') return;
      item.muted = true;
      item.loop = items.length === 1;

      if (items.length > 1) {
        item.addEventListener('ended', function () {
          if (state.items[state.index] === item) showCardItem(state, state.index + 1);
        });
      }
    });

    const openButton = document.createElement('button');
    openButton.type = 'button';
    openButton.className = 'gallery-card__open';
    openButton.setAttribute('aria-label', 'Ampliar ' + (title ? title.textContent : 'projeto'));
    openButton.setAttribute('aria-haspopup', 'dialog');
    openButton.setAttribute('aria-controls', 'gallery-viewer');
    openButton.addEventListener('click', function () {
      openGalleryViewer(state, openButton);
    });
    card.appendChild(openButton);
  });

  if ('IntersectionObserver' in window) {
    const cardObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        const state = stateByCard.get(entry.target);
        if (!state) return;
        state.visible = entry.isIntersecting;
        playCard(state);
      });
    }, { rootMargin: '100px 0px', threshold: 0.01 });

    cardStates.forEach(function (state) {
      cardObserver.observe(state.card);
    });
  } else {
    cardStates.forEach(function (state) {
      state.visible = true;
      playCard(state);
    });
  }

  document.addEventListener('visibilitychange', function () {
    cardStates.forEach(playCard);
    if (!galleryViewer.open) return;

    const video = viewerMedia.querySelector('video');
    if (!video) return;
    if (document.hidden) video.pause();
    else playMuted(video);
  });

  viewerCloseButton.addEventListener('click', function () {
    galleryViewer.close();
  });
  viewerPreviousButton.addEventListener('click', function () {
    showViewerItem(viewerIndex - 1);
  });
  viewerNextButton.addEventListener('click', function () {
    showViewerItem(viewerIndex + 1);
  });

  galleryViewer.addEventListener('keydown', function (event) {
    if (viewerItems.length < 2) return;
    if (event.key === 'ArrowLeft') {
      event.preventDefault();
      showViewerItem(viewerIndex - 1);
    }
    if (event.key === 'ArrowRight') {
      event.preventDefault();
      showViewerItem(viewerIndex + 1);
    }
  });

  galleryViewer.addEventListener('close', function () {
    window.clearTimeout(viewerTimer);
    window.clearTimeout(closeIntroTimer);
    viewerCloseButton.classList.remove('is-intro');

    const video = viewerMedia.querySelector('video');
    if (video) video.pause();

    viewerMedia.replaceChildren();
    viewerItems = [];
    dragStart = null;
    viewerMedia.style.transform = '';
    viewerMedia.style.opacity = '';
    document.body.classList.remove('modal-open');
    cardStates.forEach(playCard);
    if (viewerTrigger) viewerTrigger.focus();
  });

  function resetViewerDrag() {
    dragStart = null;
    viewerMedia.style.transform = '';
    viewerMedia.style.opacity = '';
  }

  function finishViewerDrag(event) {
    if (!dragStart || event.pointerId !== dragStart.id) return;

    const horizontalDistance = event.clientX - dragStart.x;
    const verticalDistance = event.clientY - dragStart.y;
    resetViewerDrag();

    const isVerticalClose = Math.abs(verticalDistance) > VERTICAL_CLOSE_DISTANCE_PX &&
      Math.abs(verticalDistance) > Math.abs(horizontalDistance) * 1.15;
    const isHorizontalNavigation = viewerItems.length > 1 &&
      Math.abs(horizontalDistance) > HORIZONTAL_NAV_DISTANCE_PX &&
      Math.abs(horizontalDistance) > Math.abs(verticalDistance);

    if (isVerticalClose) galleryViewer.close();
    else if (isHorizontalNavigation) showViewerItem(viewerIndex + (horizontalDistance < 0 ? 1 : -1));
  }

  viewerStage.addEventListener('pointerdown', function (event) {
    if (event.target.closest('button')) return;
    dragStart = { x: event.clientX, y: event.clientY, id: event.pointerId };
    viewerStage.setPointerCapture(event.pointerId);
  });
  viewerStage.addEventListener('pointermove', function (event) {
    if (!dragStart || event.pointerId !== dragStart.id) return;
    const distance = event.clientY - dragStart.y;
    viewerMedia.style.transform = 'translateY(' + Math.max(-140, Math.min(140, distance * 0.45)) + 'px)';
    viewerMedia.style.opacity = String(Math.max(0.5, 1 - Math.abs(distance) / 450));
  });
  viewerStage.addEventListener('pointerup', finishViewerDrag);
  viewerStage.addEventListener('pointercancel', resetViewerDrag);
}());
