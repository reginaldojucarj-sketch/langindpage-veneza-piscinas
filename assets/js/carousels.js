(function () {
  'use strict';

  const HERO_INTERVAL_MS = 12000;
  const PRODUCT_INTERVAL_MS = 5000;
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const heroCarousel = document.querySelector('.hero-carousel');

  if (heroCarousel) {
    let heroSlides = heroCarousel.querySelectorAll('.hero-photo');
    const heroDots = heroCarousel.querySelectorAll('.carousel-dot');
    const previousButton = heroCarousel.querySelector('.carousel-prev');
    const nextButton = heroCarousel.querySelector('.carousel-next');
    let activeSlideIndex = 0;
    let heroTimer;

    function replaceVideoWithPoster(video) {
      if (!video.isConnected) return;

      const poster = document.createElement('img');
      poster.className = video.className;
      poster.src = video.poster;
      poster.alt = video.getAttribute('aria-label') || 'Piscina Veneza Piscinas';
      poster.setAttribute('aria-hidden', video.getAttribute('aria-hidden'));
      video.replaceWith(poster);
      heroSlides = heroCarousel.querySelectorAll('.hero-photo');
    }

    function playHeroVideo(video) {
      video.currentTime = 0;
      const playback = video.play();

      if (playback && typeof playback.catch === 'function') {
        playback.catch(function (error) {
          if (error.name === 'NotSupportedError') replaceVideoWithPoster(video);
        });
      }
    }

    function showHeroSlide(index) {
      activeSlideIndex = (index + heroSlides.length) % heroSlides.length;

      heroSlides.forEach(function (slide, slideIndex) {
        const isActive = slideIndex === activeSlideIndex;
        slide.classList.toggle('is-active', isActive);
        slide.setAttribute('aria-hidden', String(!isActive));

        if (slide.tagName !== 'VIDEO') return;
        if (isActive) playHeroVideo(slide);
        else slide.pause();
      });

      heroDots.forEach(function (dot, dotIndex) {
        dot.setAttribute('aria-current', String(dotIndex === activeSlideIndex));
      });
    }

    function stopHeroCarousel() {
      window.clearTimeout(heroTimer);
    }

    function startHeroCarousel() {
      stopHeroCarousel();
      if (document.hidden || prefersReducedMotion) return;

      heroTimer = window.setTimeout(function () {
        showHeroSlide(activeSlideIndex + 1);
        startHeroCarousel();
      }, HERO_INTERVAL_MS);
    }

    heroSlides.forEach(function (slide) {
      if (slide.tagName === 'VIDEO') {
        slide.addEventListener('error', function () {
          replaceVideoWithPoster(slide);
        });
      }
    });

    if (previousButton) {
      previousButton.addEventListener('click', function () {
        showHeroSlide(activeSlideIndex - 1);
        startHeroCarousel();
      });
    }

    if (nextButton) {
      nextButton.addEventListener('click', function () {
        showHeroSlide(activeSlideIndex + 1);
        startHeroCarousel();
      });
    }

    heroDots.forEach(function (dot, dotIndex) {
      dot.addEventListener('click', function () {
        showHeroSlide(dotIndex);
        startHeroCarousel();
      });
    });

    document.addEventListener('visibilitychange', function () {
      if (document.hidden) stopHeroCarousel();
      else startHeroCarousel();
    });

    heroCarousel.classList.add('is-ready');
    showHeroSlide(0);
    startHeroCarousel();
  }

  const productCarousels = document.querySelectorAll('[data-card-carousel]');

  productCarousels.forEach(function (carousel, carouselIndex) {
    const slides = carousel.querySelectorAll('.priority-card__slide');
    let activeIndex = 0;
    let timer;

    if (slides.length < 2 || prefersReducedMotion) return;

    function showProductSlide(index) {
      activeIndex = (index + slides.length) % slides.length;
      slides.forEach(function (slide, slideIndex) {
        const isActive = slideIndex === activeIndex;
        slide.classList.toggle('is-active', isActive);
        slide.setAttribute('aria-hidden', String(!isActive));
      });
    }

    function startProductCarousel() {
      window.clearInterval(timer);
      if (document.hidden) return;

      timer = window.setInterval(function () {
        showProductSlide(activeIndex + 1);
      }, PRODUCT_INTERVAL_MS);
    }

    slides.forEach(function (slide, slideIndex) {
      slide.setAttribute('aria-hidden', String(slideIndex !== 0));
    });

    window.setTimeout(startProductCarousel, carouselIndex * 350);
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) window.clearInterval(timer);
      else startProductCarousel();
    });
  });
}());
