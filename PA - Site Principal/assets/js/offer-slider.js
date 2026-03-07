(function() {
  'use strict';

  var slider = document.getElementById('offer-image-slider');
  if (!slider) return;

  var images = slider.querySelectorAll('.slider-img');
  var leftBtn = document.getElementById('slider-arrow-left');
  var rightBtn = document.getElementById('slider-arrow-right');
  var dotsContainer = document.getElementById('slider-dots');
  var current = 0;

  function showImage(idx) {
    images.forEach(function(img, i) {
      img.classList.toggle('active', i === idx);
    });
    if (dotsContainer) {
      var dots = dotsContainer.querySelectorAll('.slider-dot');
      dots.forEach(function(dot, i) {
        dot.classList.toggle('active', i === idx);
      });
    }
    current = idx;
  }

  function next() {
    showImage((current + 1) % images.length);
  }
  function prev() {
    showImage((current - 1 + images.length) % images.length);
  }

  if (dotsContainer) {
    dotsContainer.innerHTML = '';
    images.forEach(function(_, i) {
      var dot = document.createElement('span');
      dot.className = 'slider-dot' + (i === 0 ? ' active' : '');
      dot.addEventListener('click', function() { showImage(i); });
      dotsContainer.appendChild(dot);
    });
  }

  if (leftBtn) leftBtn.addEventListener('click', prev);
  if (rightBtn) rightBtn.addEventListener('click', next);

  showImage(0);

  var modal = document.getElementById('image-modal');
  var modalImg = document.getElementById('modal-img');
  var modalClose = document.getElementById('modal-close');
  var modalLeft = document.getElementById('modal-modal-arrow-left');
  var modalRight = document.getElementById('modal-modal-arrow-right');
  var modalCaption = document.getElementById('modal-caption');
  var modalCurrent = 0;

  function openModal(idx) {
    if (!modal) return;
    modal.style.display = 'block';
    setModalImage(idx);
    document.body.classList.add('modal-open');
  }
  function closeModal() {
    if (!modal) return;
    modal.style.display = 'none';
    document.body.classList.remove('modal-open');
  }
  function setModalImage(idx) {
    if (!modalImg) return;
    var img = images[idx];
    if (!img) return;
    modalImg.src = img.src;
    modalCaption.textContent = img.alt || '';
    modalCurrent = idx;
  }
  function modalNext() {
    setModalImage((modalCurrent + 1) % images.length);
  }
  function modalPrev() {
    setModalImage((modalCurrent - 1 + images.length) % images.length);
  }

  images.forEach(function(img, i) {
    img.style.cursor = 'pointer';
    img.addEventListener('click', function() { openModal(i); });
  });
  if (modalClose) modalClose.addEventListener('click', closeModal);
  if (modalLeft) modalLeft.addEventListener('click', modalPrev);
  if (modalRight) modalRight.addEventListener('click', modalNext);
  if (modal) {
    modal.addEventListener('click', function(e) {
      if (e.target === modal) closeModal();
    });
    document.addEventListener('keydown', function(e) {
      if (modal.style.display === 'block') {
        if (e.key === 'Escape') closeModal();
        if (e.key === 'ArrowLeft') modalPrev();
        if (e.key === 'ArrowRight') modalNext();
      }
    });
  }
})();
