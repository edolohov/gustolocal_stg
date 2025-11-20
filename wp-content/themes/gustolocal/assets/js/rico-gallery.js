(function() {
  'use strict';

  const images = [
    'https://gustolocal.es/wp-content/uploads/2025/10/P1004044.jpg',
    'https://gustolocal.es/wp-content/uploads/2025/10/P1004071.jpg',
    'https://gustolocal.es/wp-content/uploads/2025/10/P1003750-1.jpg',
    'https://gustolocal.es/wp-content/uploads/2025/10/P1004096.jpg',
    'https://gustolocal.es/wp-content/uploads/2025/10/P1003861.jpg',
    'https://gustolocal.es/wp-content/uploads/2025/10/P1003922.jpg',
    'https://gustolocal.es/wp-content/uploads/2025/10/P1004029.jpg',
    'https://gustolocal.es/wp-content/uploads/2025/10/P1003880.jpg',
    'https://gustolocal.es/wp-content/uploads/2025/10/P1004011.jpg',
    'https://gustolocal.es/wp-content/uploads/2025/10/P1003769-1.jpg',
    'https://gustolocal.es/wp-content/uploads/2025/10/P1003721.jpg',
    'https://gustolocal.es/wp-content/uploads/2025/10/P1003873.jpg',
    'https://gustolocal.es/wp-content/uploads/2025/10/P1003824.jpg',
    'https://gustolocal.es/wp-content/uploads/2025/10/P1003892.jpg',
  ];

  let currentImageIndex = 0;

  function openModal(index) {
    currentImageIndex = index;
    const modal = document.getElementById('imageModal');
    const modalImage = document.getElementById('modalImage');
    
    if (modal && modalImage) {
      modal.style.display = 'block';
      modalImage.src = images[currentImageIndex];
      document.body.style.overflow = 'hidden'; // Prevent background scroll
    }
  }

  function closeModal() {
    const modal = document.getElementById('imageModal');
    if (modal) {
      modal.style.display = 'none';
      document.body.style.overflow = 'auto'; // Restore scroll
    }
  }

  function changeImage(direction) {
    currentImageIndex += direction;
    if (currentImageIndex >= images.length) currentImageIndex = 0;
    if (currentImageIndex < 0) currentImageIndex = images.length - 1;
    
    const modalImage = document.getElementById('modalImage');
    if (modalImage) {
      modalImage.src = images[currentImageIndex];
    }
  }

  // Initialize gallery when DOM is ready
  document.addEventListener('DOMContentLoaded', function() {
    // Attach click handlers to gallery items
    const galleryItems = document.querySelectorAll('.rico .gallery-item');
    galleryItems.forEach(function(item) {
      const index = parseInt(item.getAttribute('data-index')) || 0;
      item.addEventListener('click', function() {
        openModal(index);
      });
    });

    // Close button
    const closeBtn = document.querySelector('.rico .close');
    if (closeBtn) {
      closeBtn.addEventListener('click', closeModal);
    }

    // Modal background click to close
    const modal = document.getElementById('imageModal');
    if (modal) {
      modal.addEventListener('click', function(event) {
        if (event.target === modal) {
          closeModal();
        }
      });
    }

    // Navigation buttons
    const prevBtn = document.querySelector('.rico .modal-nav .prev');
    const nextBtn = document.querySelector('.rico .modal-nav .next');
    
    if (prevBtn) {
      prevBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        changeImage(-1);
      });
    }
    
    if (nextBtn) {
      nextBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        changeImage(1);
      });
    }

    // Keyboard navigation
    document.addEventListener('keydown', function(event) {
      if (modal && modal.style.display === 'block') {
        if (event.key === 'Escape') {
          closeModal();
        }
        if (event.key === 'ArrowLeft') {
          changeImage(-1);
        }
        if (event.key === 'ArrowRight') {
          changeImage(1);
        }
      }
    });
  });

  // Expose functions globally for backward compatibility
  window.openModal = openModal;
  window.closeModal = closeModal;
  window.changeImage = changeImage;
})();

