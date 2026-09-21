(function () {
  'use strict';
  var productModal = document.getElementById('product-modal');
  if (productModal) {
    var projectItems = Array.prototype.map.call(document.querySelectorAll('[data-modal-trigger="projeto"] .priority-card__slide'), function (slide) {
      return {
        type: 'Projeto hidráulico',
        brand: 'Veneza Piscinas',
        name: slide.querySelector('figcaption').textContent,
        image: slide.querySelector('img').getAttribute('src')
      };
    });
    var productLines = {
      filtracao: {
        title: 'Filtros e Motobombas', intro: 'Conheça exemplos das marcas com que trabalhamos. Filtro e motobomba são escolhidos em conjunto, conforme o volume, a vazão e a rotina de uso da piscina.', items: [
          { type: 'Filtro', brand: 'Nautilus', name: 'Filtro F950P', image: 'assets/images/products/filtro-nautilus.jpg' },
          { type: 'Filtro', brand: 'Syllent', name: 'Filtro SYL200', image: 'assets/images/products/filtro-syllent.png' },
          { type: 'Filtro', brand: 'Albacete', name: 'Filtro AP50', image: 'assets/images/products/filtro-albacete.webp' },
          { type: 'Motobomba', brand: 'Nautilus', name: 'Motobomba NBFC', image: 'assets/images/products/motobomba-nautilus.jpg' },
          { type: 'Motobomba', brand: 'Syllent', name: 'Pré-filtro autoescorvante', image: 'assets/images/products/motobomba-syllent.png' },
          { type: 'Motobomba', brand: 'Albacete', name: 'Motobomba APP-2', image: 'assets/images/products/motobomba-albacete.webp' }
        ]
      },
      aquecedores: {
        title: 'Aquecedores de Piscinas', intro: 'O aquecimento ideal considera volume, temperatura desejada, exposição ao vento e frequência de uso. Veja algumas das marcas que podemos indicar para seu projeto.', items: [
          { type: 'Aquecedor', brand: 'Nautilus', name: 'Bomba de calor Terma', image: 'assets/images/products/aquecedor-nautilus.jpg' },
          { type: 'Aquecedor', brand: 'Tholz', name: 'Trocador de calor X23', image: 'assets/images/products/aquecedor-tholz.webp' },
          { type: 'Aquecedor', brand: 'Fromtherm', name: 'Bomba de calor FTi', image: 'assets/images/products/aquecedor-fromtherm.png' }
        ]
      },
      cloro: {
        title: 'Geradores de Cloro', intro: 'A eletrólise salina automatiza a produção de cloro durante a circulação da água. Indicamos a linha conforme o volume da piscina e as condições de instalação.', items: [
          { type: 'Gerador de cloro', brand: 'Nautilus', name: 'EasyClor Home G5-04', image: 'assets/images/products/cloro-nautilus-easyclor-home-g5-04.webp' },
          { type: 'Gerador de cloro', brand: 'Syllent', name: 'EcoChlor', image: 'assets/images/products/cloro-syllent.jpg' },
          { type: 'Gerador de cloro', brand: 'Tholz', name: 'Gerador de Cloro', image: 'assets/images/products/cloro-tholz.webp' }
        ]
      },
      ozonio: {
        title: 'Geradores de Ozônio', intro: 'O ozônio complementa o tratamento da água. A indicação considera o volume, a circulação e o sistema de desinfecção da piscina.', items: [
          { type: 'Gerador de ozônio', brand: 'Panozon', name: 'Blue Star', image: 'assets/images/products/ozonio-panozon.png' },
          { type: 'Gerador de ozônio', brand: 'Ozon3', name: 'OZ60', image: 'assets/images/products/ozonio-ozon3.jpg' }
        ]
      },
      iluminacao: {
        title: 'Iluminação LED e Refletores', intro: 'Refletores bem posicionados valorizam a piscina e melhoram a visibilidade à noite. Nossa equipe orienta quantidade, potência e acabamento.', items: [
          { type: 'Refletor LED', brand: 'Tholz', name: 'RGBW Premium', image: 'assets/images/products/led-tholz.webp' },
          { type: 'Refletor LED', brand: 'Syllent', name: 'Linhas Classic e Premium', image: 'assets/images/products/led-syllent.png' },
          { type: 'Iluminação LED', brand: 'Veneza Piscinas', name: 'Piscina residencial com iluminação noturna', image: 'assets/images/products/piscina-residencial-iluminacao-noturna.jpg' }
        ]
      },
      revestimento: {
        title: 'Revestimento em Manta Armada',
        intro: 'Veja a instalação, a soldagem e o acabamento da manta armada. Para saber se essa solução atende à sua piscina, fale com um especialista e peça um orçamento.',
        quoteMessage: 'Olá, Veneza Piscinas! Quero falar com um especialista e pedir orçamento de revestimento em manta armada para minha piscina.',
        items: [
          { type: 'Vídeo do processo', brand: 'Veneza Piscinas', name: 'Soldagem da manta armada', image: 'assets/images/projects/manta-armada-soldagem-poster.jpg', video: 'assets/videos/projects/manta-armada-soldagem.mp4' },
          { type: 'Vídeo do processo', brand: 'Veneza Piscinas', name: 'Instalação do revestimento', image: 'assets/images/projects/manta-armada-instalacao-poster.jpg', video: 'assets/videos/projects/manta-armada-instalacao.mp4' },
          { type: 'Vídeo do resultado', brand: 'Veneza Piscinas', name: 'Manta armada aplicada', image: 'assets/images/projects/manta-armada-aplicada-poster.jpg', video: 'assets/videos/projects/manta-armada-aplicada.mp4' },
          { type: 'Vídeo do resultado', brand: 'Veneza Piscinas', name: 'Exemplo com borda infinita', image: 'assets/images/projects/manta-armada-borda-infinita-exemplo-poster.jpg', video: 'assets/videos/projects/manta-armada-borda-infinita-exemplo.mp4' },
          { type: 'Foto do resultado', brand: 'Veneza Piscinas', name: 'Detalhe do revestimento e da borda', image: 'assets/images/projects/piscina-manta-armada-borda-elevada.jpg' }
        ]
      },
      projeto: {
        title: 'Projeto Hidráulico',
        intro: 'Planejamento técnico desenvolvido pela Veneza Piscinas para integrar todos os equipamentos à sua piscina.',
        quoteMessage: 'Olá, Veneza Piscinas! Quero falar com um especialista e pedir orçamento de projeto hidráulico em BIM/Revit, DWG e PDF para minha piscina.',
        items: projectItems
      }
    };
    var modalTitle = document.getElementById('product-modal-title'), modalIntro = document.getElementById('product-modal-intro'), modalEyebrow = document.getElementById('product-modal-eyebrow'), modalGallery = document.getElementById('product-modal-gallery'), modalImage = document.getElementById('product-modal-image'), modalVideo = document.getElementById('product-modal-video'), modalBrand = document.getElementById('product-modal-brand'), modalImageName = document.getElementById('product-modal-image-name'), modalImageCount = document.getElementById('product-modal-image-count'), modalThumbs = document.getElementById('product-modal-thumbs'), modalWhatsapp = document.getElementById('product-modal-whatsapp'), modalDisclaimer = document.getElementById('product-modal-disclaimer'), activeLine = null, activeImage = 0, previousFocus = null;
    function whatsappUrl(message) { return 'https://wa.me/5581982983545?text=' + encodeURIComponent(message) }
    function showProductImage(index) {
      activeImage = (index + activeLine.items.length) % activeLine.items.length;
      var item = activeLine.items[activeImage];
      modalVideo.pause();
      modalVideo.removeAttribute('src');
      modalVideo.load();
      modalVideo.hidden = !item.video;
      modalImage.hidden = !!item.video;
      if (item.video) {
        modalVideo.poster = item.image;
        modalVideo.src = item.video;
        modalVideo.setAttribute('aria-label', item.name);
        modalVideo.loop = true;
        modalVideo.muted = true;
        var videoPlayback = modalVideo.play();
        if (videoPlayback && typeof videoPlayback.catch === 'function') videoPlayback.catch(function () { });
      } else {
        modalImage.src = item.image;
        modalImage.alt = item.name + ' da marca ' + item.brand;
      }
      modalBrand.textContent = item.brand;
      modalImageName.textContent = item.name + ' · ' + item.brand;
      modalImageCount.textContent = (activeImage + 1) + ' de ' + activeLine.items.length;
      modalThumbs.querySelectorAll('button').forEach(function (button, i) { button.setAttribute('aria-current', String(i === activeImage)) });
      modalWhatsapp.href = whatsappUrl(activeLine.quoteMessage || 'Olá, Veneza Piscinas! Quero falar com um especialista e pedir orçamento para ' + item.type.toLowerCase() + ' da marca ' + item.brand + '.');
    }
    function openProductModal(key, trigger) {
      activeLine = productLines[key]; if (!activeLine) return;
      previousFocus = trigger;
      modalTitle.textContent = activeLine.title;
      modalIntro.textContent = activeLine.intro;
      modalEyebrow.textContent = key === 'projeto' ? 'Solução técnica' : 'Nossa linha';
      modalGallery.hidden = !activeLine.items.length;
      modalDisclaimer.textContent = key === 'revestimento' ? 'Vídeos e fotos de exemplos de revestimento. Conte sobre sua piscina para receber uma avaliação e orçamento.' : 'Fotos de referência das marcas. Consulte modelos e disponibilidade no atendimento.';
      modalThumbs.replaceChildren();
      if (activeLine.items.length) {
        activeLine.items.forEach(function (item, index) {
          var button = document.createElement('button'), photo = document.createElement('img'), label = document.createElement('span'), brand = document.createElement('b');
          button.type = 'button'; button.className = 'product-modal__thumb'; button.setAttribute('aria-label', 'Ver ' + item.name + ' da marca ' + item.brand);
          photo.src = item.image; photo.alt = ''; photo.loading = 'lazy';
          brand.textContent = item.brand; label.appendChild(brand); label.appendChild(document.createTextNode(item.type));
          button.appendChild(photo); button.appendChild(label); button.addEventListener('click', function () { showProductImage(index) }); modalThumbs.appendChild(button);
        });
        showProductImage(0);
      } else {
        modalWhatsapp.href = whatsappUrl('Olá, Veneza Piscinas! Quero falar com um especialista e pedir orçamento de revestimento em manta armada para minha piscina.');
      }
      productModal.showModal(); document.body.classList.add('modal-open');
      if (activeLine.items.length && activeLine.items[activeImage].video) {
        var modalPlayback = modalVideo.play();
        if (modalPlayback && typeof modalPlayback.catch === 'function') modalPlayback.catch(function () { });
      }
      productModal.querySelector('.product-modal__close').focus();
    }
    document.querySelectorAll('[data-modal-trigger]').forEach(function (card) {
      card.addEventListener('click', function () { openProductModal(card.dataset.modalTrigger, card) });
      card.addEventListener('keydown', function (event) { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); openProductModal(card.dataset.modalTrigger, card) } });
    });
    document.querySelectorAll('[data-modal-link]').forEach(function (link) {
      link.addEventListener('click', function (event) { event.preventDefault(); openProductModal(link.dataset.modalLink, link) });
    });
    productModal.querySelector('.product-modal__close').addEventListener('click', function () { productModal.close() });
    productModal.querySelector('.product-modal__back').addEventListener('click', function () { productModal.close() });
    productModal.querySelector('.product-modal__prev').addEventListener('click', function () { showProductImage(activeImage - 1) });
    productModal.querySelector('.product-modal__next').addEventListener('click', function () { showProductImage(activeImage + 1) });
    productModal.addEventListener('keydown', function (event) { if (modalGallery.hidden) return; if (event.key === 'ArrowLeft') { event.preventDefault(); showProductImage(activeImage - 1) } else if (event.key === 'ArrowRight') { event.preventDefault(); showProductImage(activeImage + 1) } });
    productModal.addEventListener('click', function (event) { var box = productModal.getBoundingClientRect(); if (event.target === productModal && (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom)) { productModal.close() } });
    productModal.addEventListener('close', function () { modalVideo.pause(); modalVideo.removeAttribute('src'); modalVideo.load(); document.body.classList.remove('modal-open'); if (previousFocus) previousFocus.focus() });
  }
}());
