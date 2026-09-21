(function () {
  'use strict';

  const testimonialVideos = Array.from(document.querySelectorAll('.testimonial-video'));

  if (!testimonialVideos.length) return;

  function pauseAllExcept(activeVideo) {
    testimonialVideos.forEach(function (video) {
      if (video !== activeVideo) video.pause();
    });
  }

  function formatTime(seconds) {
    if (!Number.isFinite(seconds)) return '0:00';
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = Math.floor(seconds % 60).toString().padStart(2, '0');
    return minutes + ':' + remainingSeconds;
  }

  function createControl(label, symbol, className) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'testimonial-control ' + className;
    button.setAttribute('aria-label', label);
    button.textContent = symbol;
    return button;
  }

  function createVideoControls(video) {
    const media = video.closest('.testimonial-media');
    if (!media) return;

    const controls = document.createElement('div');
    const playButton = createControl('Reproduzir vídeo', '▶', 'testimonial-control--play');
    const progress = document.createElement('input');
    const muteButton = createControl('Desativar som', '🔊', 'testimonial-control--mute');
    const fullscreenButton = createControl('Assistir em tela cheia', '⛶', 'testimonial-control--fullscreen');

    controls.className = 'testimonial-controls';
    controls.setAttribute('role', 'group');
    controls.setAttribute('aria-label', 'Controles do vídeo');
    progress.className = 'testimonial-progress';
    progress.type = 'range';
    progress.min = '0';
    progress.max = '1';
    progress.step = '0.1';
    progress.value = '0';
    progress.setAttribute('aria-label', 'Progresso do vídeo');
    progress.setAttribute('aria-valuetext', '0:00 de 0:00');

    controls.append(playButton, progress, muteButton, fullscreenButton);
    media.appendChild(controls);
    video.controls = false;

    function updatePlayButton() {
      const isPlaying = !video.paused && !video.ended;
      playButton.textContent = isPlaying ? '❚❚' : '▶';
      playButton.setAttribute('aria-label', isPlaying ? 'Pausar vídeo' : 'Reproduzir vídeo');
    }

    function updateMuteButton() {
      const hasSound = !video.muted && video.volume > 0;
      muteButton.textContent = hasSound ? '🔊' : '🔇';
      muteButton.setAttribute('aria-label', hasSound ? 'Desativar som' : 'Ativar som');
    }

    function updateProgress() {
      const duration = Number.isFinite(video.duration) ? video.duration : 0;
      progress.max = String(duration || 1);
      progress.value = String(video.currentTime || 0);
      progress.setAttribute('aria-valuetext', formatTime(video.currentTime) + ' de ' + formatTime(duration));
    }

    function togglePlayback() {
      if (!video.paused && !video.ended) {
        video.pause();
        return;
      }

      const playback = video.play();
      if (playback && typeof playback.catch === 'function') playback.catch(function () { });
    }

    playButton.addEventListener('click', togglePlayback);
    video.addEventListener('click', togglePlayback);
    video.addEventListener('play', updatePlayButton);
    video.addEventListener('pause', updatePlayButton);
    video.addEventListener('ended', updatePlayButton);
    video.addEventListener('loadedmetadata', updateProgress);
    video.addEventListener('durationchange', updateProgress);
    video.addEventListener('timeupdate', updateProgress);
    video.addEventListener('volumechange', updateMuteButton);

    progress.addEventListener('input', function () {
      if (Number.isFinite(video.duration)) video.currentTime = Number(progress.value);
    });

    muteButton.addEventListener('click', function () {
      video.muted = !video.muted;
    });

    fullscreenButton.addEventListener('click', function () {
      if (media.requestFullscreen) {
        const request = media.requestFullscreen();
        if (request && typeof request.catch === 'function') request.catch(function () { });
      } else if (video.webkitEnterFullscreen) {
        video.webkitEnterFullscreen();
      }
    });

    updatePlayButton();
    updateMuteButton();
    updateProgress();
  }

  testimonialVideos.forEach(function (video) {
    createVideoControls(video);
    video.addEventListener('play', function () {
      pauseAllExcept(video);
    });
  });

  if ('IntersectionObserver' in window) {
    const visibilityObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) entry.target.pause();
      });
    }, { threshold: 0.2 });

    testimonialVideos.forEach(function (video) {
      visibilityObserver.observe(video);
    });
  }

  document.addEventListener('visibilitychange', function () {
    if (document.hidden) pauseAllExcept(null);
  });

}());
