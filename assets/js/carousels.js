(function () {
  'use strict';
  var carousel = document.querySelector('.hero-carousel');
  if (carousel) {
    var slides = carousel.querySelectorAll('.hero-photo'), dots = carousel.querySelectorAll('.carousel-dot'), activeSlide = 0, carouselTimer;
    function fallbackToPoster(video) {
      if (!video.isConnected) return;
      var photo = document.createElement('img');
      photo.className = video.className;
      photo.src = video.poster;
      photo.alt = video.getAttribute('aria-label') || 'Piscina Veneza Piscinas';
      photo.setAttribute('aria-hidden', video.getAttribute('aria-hidden'));
      video.replaceWith(photo);
      slides = carousel.querySelectorAll('.hero-photo');
    }
    slides.forEach(function (slide) {
      if (slide.tagName === 'VIDEO') slide.addEventListener('error', function () { fallbackToPoster(slide) });
    });
    function showSlide(index) {
      activeSlide = (index + slides.length) % slides.length;
      slides.forEach(function (slide, i) {
        var active = i === activeSlide;
        slide.classList.toggle('is-active', active);
        slide.setAttribute('aria-hidden', String(!active));
        if (slide.tagName === 'VIDEO') {
          if (active) {
            slide.currentTime = 0;
            var playback = slide.play();
            if (playback && typeof playback.catch === 'function') playback.catch(function (error) {
              if (error.name !== 'AbortError') fallbackToPoster(slide);
            });
          }
          else { slide.pause() }
        }
      });
      dots.forEach(function (dot, i) { dot.setAttribute('aria-current', String(i === activeSlide)) });
    }
    function stopCarousel() { window.clearTimeout(carouselTimer) }
    function startCarousel() { stopCarousel(); if (!document.hidden) { carouselTimer = window.setTimeout(function () { showSlide(activeSlide + 1); startCarousel() }, 12000) } }
    carousel.querySelector('.carousel-prev').addEventListener('click', function () { showSlide(activeSlide - 1); startCarousel() });
    carousel.querySelector('.carousel-next').addEventListener('click', function () { showSlide(activeSlide + 1); startCarousel() });
    dots.forEach(function (dot, i) { dot.addEventListener('click', function () { showSlide(i); startCarousel() }) });
    document.addEventListener('visibilitychange', function () { if (document.hidden) { stopCarousel() } else { startCarousel() } });
    carousel.classList.add('is-ready');
    showSlide(0);
    startCarousel();
  }
  var productCardCarousels = document.querySelectorAll('[data-card-carousel]');
  if (productCardCarousels.length) {
    var reduceCardMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    productCardCarousels.forEach(function (cardCarousel, carouselIndex) {
      var cardSlides = cardCarousel.querySelectorAll('.priority-card__slide'), cardSlideIndex = 0, cardTimer;
      if (cardSlides.length < 2 || reduceCardMotion) return;
      function showCardSlide(index) {
        cardSlideIndex = (index + cardSlides.length) % cardSlides.length;
        cardSlides.forEach(function (slide, slideIndex) {
          var active = slideIndex === cardSlideIndex;
          slide.classList.toggle('is-active', active);
          slide.setAttribute('aria-hidden', String(!active));
        });
      }
      function startCardCarousel() {
        window.clearInterval(cardTimer);
        if (!document.hidden) cardTimer = window.setInterval(function () { showCardSlide(cardSlideIndex + 1) }, 5000);
      }
      cardSlides.forEach(function (slide, slideIndex) { slide.setAttribute('aria-hidden', String(slideIndex !== 0)) });
      window.setTimeout(startCardCarousel, carouselIndex * 350);
      document.addEventListener('visibilitychange', function () {
        if (document.hidden) window.clearInterval(cardTimer);
        else startCardCarousel();
      });
    });
  }
}());
