/**
 * Поддержка tooltip на мобильных устройствах
 * Обрабатывает клики/тапы для показа/скрытия tooltip
 */
(function() {
  'use strict';

  let tooltipsInitialized = false;
  let clickHandler = null;

  function initTooltips() {
    // Предотвращаем повторную инициализацию
    if (tooltipsInitialized) {
      return;
    }

    const tooltipWrappers = document.querySelectorAll('.gl-tooltip-wrapper');
    
    if (tooltipWrappers.length === 0) {
      return;
    }

    // Определяем, является ли устройство мобильным
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) || 
                     (window.innerWidth <= 768);

    if (!isMobile) {
      return; // На десктопе используем только CSS hover
    }

    // Глобальный обработчик для закрытия tooltip при клике вне его
    if (!clickHandler) {
      clickHandler = function(e) {
        const target = e.target;
        const activeTooltip = document.querySelector('.gl-tooltip-wrapper.is-active');
        
        if (!activeTooltip) {
          return;
        }

        // Проверяем, кликнули ли мы на затемненный фон (::before элемент)
        // На мобильных ::before создает затемненный фон
        const isOverlay = target === document.body || 
                         target === document.documentElement ||
                         (target.classList && target.classList.contains('gl-tooltip-wrapper') && 
                          !target.querySelector('.gl-tooltip-icon').contains(e.target) &&
                          !target.querySelector('.gl-tooltip-content').contains(e.target));

        // Проверяем, кликнули ли мы вне tooltip
        const icon = activeTooltip.querySelector('.gl-tooltip-icon');
        const content = activeTooltip.querySelector('.gl-tooltip-content');
        
        // Если клик не на иконку и не внутри контента tooltip
        const clickedOnIcon = icon && (target === icon || icon.contains(target));
        const clickedOnContent = content && content.contains(target);
        
        if (!clickedOnIcon && !clickedOnContent) {
          activeTooltip.classList.remove('is-active');
        }
      };
      
      // Используем capture phase для раннего перехвата
      document.addEventListener('click', clickHandler, true);
      document.addEventListener('touchend', clickHandler, true);
    }

    tooltipWrappers.forEach(function(wrapper) {
      // Пропускаем, если уже инициализирован
      if (wrapper.dataset.tooltipInitialized === 'true') {
        return;
      }
      
      const icon = wrapper.querySelector('.gl-tooltip-icon');
      const content = wrapper.querySelector('.gl-tooltip-content');
      
      if (!icon || !content) {
        return;
      }

      // Обработчик клика для показа/скрытия tooltip
      icon.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const isActive = wrapper.classList.contains('is-active');
        
        // Закрываем все другие tooltip
        document.querySelectorAll('.gl-tooltip-wrapper.is-active').forEach(function(activeWrapper) {
          if (activeWrapper !== wrapper) {
            activeWrapper.classList.remove('is-active');
          }
        });
        
        // Переключаем текущий tooltip
        if (isActive) {
          wrapper.classList.remove('is-active');
        } else {
          wrapper.classList.add('is-active');
        }
      }, true);

      // Закрываем tooltip при скролле
      let scrollTimeout;
      const scrollHandler = function() {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(function() {
          wrapper.classList.remove('is-active');
        }, 100);
      };
      
      window.addEventListener('scroll', scrollHandler, { passive: true });
      
      // Помечаем как инициализированный
      wrapper.dataset.tooltipInitialized = 'true';
    });

    tooltipsInitialized = true;
  }

  // Инициализация при загрузке DOM
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTooltips);
  } else {
    initTooltips();
  }

  // Повторная инициализация для динамически добавленного контента
  setTimeout(function() {
    tooltipsInitialized = false;
    initTooltips();
  }, 100);
  
  // Обработка изменения размера окна (переключение мобильный/десктоп)
  window.addEventListener('resize', function() {
    tooltipsInitialized = false;
    setTimeout(initTooltips, 100);
  });
})();

