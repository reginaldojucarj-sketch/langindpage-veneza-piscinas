(function () {
  'use strict';

  const testimonialVideos = Array.from(document.querySelectorAll('.testimonial-video'));

  if (!testimonialVideos.length) return;

  function pauseAllExcept(activeVideo) {
    testimonialVideos.forEach(function (video) {
      if (video !== activeVideo) video.pause();
    });
  }

  testimonialVideos.forEach(function (video) {
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
