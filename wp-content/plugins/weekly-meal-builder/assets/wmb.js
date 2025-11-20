(function () {
  "use strict";

  var CFG = window.WMB || {};
  var MENU_URL = CFG.menu_url || "/wp-json/wmb/v1/menu";
  var LS_KEY = "wmb_state_v3";

  function el(s, r){return (r||document).querySelector(s)}
  function els(s, r){return Array.from((r||document).querySelectorAll(s))}
  function money(n){return (Math.round(n*100)/100).toFixed(2) + "€"}
  function escapeHtml(s){return String(s||"").replace(/[&<>"']/g, function(m){return({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[m])})}
  function pad2(n){ return (n<10?'0':'')+n; }
  function formatFriendlyDate(d){ 
    var months = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 
                  'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
    return d.getDate() + ' ' + months[d.getMonth()];
  }

  function normalizeTags(tags){
    if (!tags) return [];
    if (Array.isArray(tags)) return tags.map(function(t){return String(t).trim()}).filter(Boolean);
    return String(tags).split(",").map(function(x){return x.trim()}).filter(Boolean);
  }

  var menu = null;
  var state = { week:"", qty:{}, filters:{ sections:[], tags:[] } };
  var countdownTimer = null;

  function flatItems(){
    return (menu && menu.sections ? menu.sections : [])
      .flatMap(function(s){
        return (s.items||[]).map(function(it){
          return Object.assign({_sectionTitle:s.title}, it);
        });
      });
  }
  function byId(id){ return flatItems().find(function(i){ return String(i.id)===String(id); }); }
  function totalPortions(){ return Object.values(state.qty).reduce(function(a,b){return a+b},0); }
  function totalPrice(){
    return Object.entries(state.qty).reduce(function(sum, kv){
      var it=byId(kv[0]); return sum + (it ? (Number(it.price)||0)*kv[1] : 0);
    }, 0);
  }
  function persist(){ try{ localStorage.setItem(LS_KEY, JSON.stringify(state)); }catch(e){} }
  function restore(){
    try{
      var raw=localStorage.getItem(LS_KEY); if(!raw) return;
      var saved=JSON.parse(raw);
      if(saved && typeof saved==="object"){
        state.qty=saved.qty||{};
        state.filters=saved.filters||{sections:[],tags:[]};
      }
    }catch(e){}
  }

  // Функция для синхронизации с WooCommerce корзиной
  async function syncWithCart(){
    try{
      // Получаем содержимое корзины через AJAX
      var response = await fetch(CFG.ajax_url, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
        body: 'action=wmb_get_cart_contents&nonce=' + CFG.nonce,
        credentials: 'same-origin'
      });
      
      if (!response.ok) return;
      
      var result = await response.json();
      if (!result.success || !result.data) return;
      
      var cartItems = result.data.items || [];
      var cartQty = {};
      
      // Собираем товары из корзины
      cartItems.forEach(function(item){
        if (item.wmb_payload) {
          try{
            var payload = JSON.parse(item.wmb_payload);
            if (payload.items) {
              Object.entries(payload.items).forEach(function([id, qty]){
                if (qty > 0) {
                  cartQty[id] = qty;
                }
              });
            }
          }catch(e){
            console.error('Error parsing cart payload:', e);
          }
        }
      });
      
      // ВСЕГДА синхронизируем с корзиной
      state.qty = cartQty;
      persist();
      
    }catch(e){
      console.error('Error syncing with cart:', e);
    }
  }

  /* ======== DELIVERY ======== */
  var RU_WEEKDAYS = ['Вск','Пн','Вт','Ср','Чт','Пт','Сб'];
  var RU_WEEKDAYS_FULL = ['Воскресенье','Понедельник','Вторник','Среда','Четверг','Пятница','Суббота'];

  function dateAtTZ(date, tz){
    try{ var s = date.toLocaleString('sv-SE', { timeZone: tz }); return new Date(s.replace(' ', 'T')); }
    catch(e){ return new Date(date); }
  }
  function nextWeekday(from, weekday){
    var d = new Date(from.getFullYear(), from.getMonth(), from.getDate());
    while (d.getDay() !== weekday) d.setDate(d.getDate()+1);
    if (d < from) d.setDate(d.getDate()+7);
    return d;
  }
  function sameWeekPrevWeekday(baseDate, weekday){
    var d = new Date(baseDate.getFullYear(), baseDate.getMonth(), baseDate.getDate());
    while (d.getDay() !== weekday) d.setDate(d.getDate()-1);
    return d;
  }
  function parseHHMM(s){ var m=/^(\d{1,2}):(\d{2})$/.exec(String(s||'')); return m?{h:+m[1],m:+m[2]}:{h:14,m:0}; }
  function formatISODate(d){ return d.getFullYear()+'-'+pad2(d.getMonth()+1)+'-'+pad2(d.getDate()); }
  function humanDeadline(deadline){ return RU_WEEKDAYS[deadline.getDay()]+' '+pad2(deadline.getHours())+':'+pad2(deadline.getMinutes()); }

  function computeDelivery(config){
    var tz = (config && config.timezone) || 'UTC';
    var now = dateAtTZ(new Date(), tz);
    var targets = [];
    if (config.tuesday && config.tuesday.enabled) targets.push({name:'tuesday', weekday:2, dl:config.tuesday.deadline});
    if (config.friday  && config.friday.enabled)  targets.push({name:'friday',  weekday:5, dl:config.friday.deadline});
    if (!targets.length) return null;
    var blackout = Array.isArray(config.blackout)? config.blackout : [];

    function candidate(afterDate, target){
      var deliver = nextWeekday(afterDate, target.weekday);
      var dlday = sameWeekPrevWeekday(deliver, target.dl && Number.isInteger(target.dl.dow) ? target.dl.dow : 0);
      var hm = parseHHMM(target.dl && target.dl.time);
      var deadline = new Date(dlday.getFullYear(), dlday.getMonth(), dlday.getDate(), hm.h, hm.m, 0);
      return { name:target.name, deliver:deliver, deadline:deadline };
    }

    var pointer = new Date(now.getTime());
    var current = null;
    for (var step=0; step<14; step++){
      var best=null;
      for (var i=0;i<targets.length;i++){
        var c = candidate(pointer, targets[i]);
        if (!best || c.deliver < best.deliver) best = c;
      }
      var iso = formatISODate(best.deliver);
      var blocked = blackout.indexOf(iso)!==-1;
      if (!blocked){
        if (now <= best.deadline){ current = best; break; }
        pointer = new Date(best.deliver.getTime()); pointer.setDate(pointer.getDate()+1);
      } else {
        pointer = new Date(best.deliver.getTime()); pointer.setDate(pointer.getDate()+1);
      }
    }
    if (!current) current = candidate(pointer, targets[0]);

    return {
      target: current.name,
      tz: tz,
      date: formatFriendlyDate(current.deliver),
      weekday: RU_WEEKDAYS[current.deliver.getDay()],
      weekday_full: RU_WEEKDAYS_FULL[current.deliver.getDay()],
      deadline_iso: current.deadline.toISOString(),
      deadline_human: humanDeadline(current.deadline)
    };
  }

  function renderBanner(node, cfg, delivery){
    if (!node || !cfg || !delivery) return;
    var tpl = (cfg.banner || 'Доставка {weekday_short}, {delivery_date}. Дедлайн {deadline}. Осталось {countdown}');
    function fill(countdown){
      var text = tpl
        .replace('{delivery_date}', delivery.date)
        .replace('{weekday}', delivery.weekday_full)
        .replace('{weekday_short}', delivery.weekday)
        .replace('{deadline}', delivery.deadline_human)
        .replace('{countdown}', countdown || '');
      
      // Сначала выделяем обратный отсчёт (чтобы не перехватывался другими правилами)
      text = text.replace(/(\d+д \d{2}:\d{2})/g, '<em>$1</em>'); // обратный отсчёт
      
      // Затем выделяем даты и время цветом
      text = text.replace(/(\d{1,2} [а-я]+)/g, '<strong>$1</strong>'); // даты в формате "7 октября"
      
      // Выделяем время, но только если оно не внутри тега <em>
      var parts = text.split(/(<em>.*?<\/em>)/);
      for (var i = 0; i < parts.length; i += 2) { // обрабатываем только не-<em> части
        parts[i] = parts[i].replace(/(\d{1,2}:\d{2})/g, '<strong>$1</strong>');
      }
      text = parts.join('');
      
      // Дни недели
      text = text.replace(/([А-Я][а-я]+)/g, function(match) {
        if (['Вск', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Воскресенье', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота'].includes(match)) {
          return '<strong>' + match + '</strong>';
        }
        return match;
      }); // дни недели
      
      // Выделяем запятые после дат и дней недели
      text = text.replace(/(<strong>[^<]+<\/strong>),/g, '$1<strong>,</strong>');
      
      node.innerHTML = '<div class="wmb-delivery">'+text+'</div>';
    }
    function tick(){
      var now = new Date();
      var ms = new Date(delivery.deadline_iso).getTime() - now.getTime();
      if (ms <= 0){ fill('0д 00:00'); clearInterval(countdownTimer); return; }
      var sec = Math.floor(ms/1000);
      var days = Math.floor(sec/86400);
      var h = Math.floor((sec%86400)/3600);
      var m = Math.floor((sec%3600)/60);
      fill(days+'д '+pad2(h)+':'+pad2(m));
    }
    if (countdownTimer) clearInterval(countdownTimer);
    tick();
    countdownTimer = setInterval(tick, 1000);
  }

  /* ======== INGREDIENTS MODAL ======== */
  function ensureIngredientsModal(){
    if (document.getElementById('wmb-ing-modal')) return;
    document.body.insertAdjacentHTML('beforeend', [
      '<div id="wmb-ing-modal" aria-hidden="true">',
        '<div class="back" tabindex="-1"></div>',
        '<div class="dialog" role="dialog" aria-modal="true" aria-labelledby="wmb-ing-title">',
          '<button class="close" aria-label="Закрыть">×</button>',
          '<h3 id="wmb-ing-title">Состав</h3>',
          '<pre id="wmb-ing-text"></pre>',
        '</div>',
      '</div>'
    ].join(''));
    var modal = el('#wmb-ing-modal');
    var close = function(){ modal.classList.remove('open'); modal.setAttribute('aria-hidden','true'); };
    modal.querySelector('.back').addEventListener('click', close);
    modal.querySelector('.close').addEventListener('click', close);
    document.addEventListener('keydown', function(e){ if(e.key==='Escape') close(); });
  }

  /* ======== ALLERGENS MODAL ======== */
  function ensureAllergensModal(){
    if (document.getElementById('wmb-allergens-modal')) return;
    document.body.insertAdjacentHTML('beforeend', [
      '<div id="wmb-allergens-modal" aria-hidden="true">',
        '<div class="back" tabindex="-1"></div>',
        '<div class="dialog" role="dialog" aria-modal="true" aria-labelledby="wmb-allergens-title">',
          '<button class="close" aria-label="Закрыть">×</button>',
          '<h3 id="wmb-allergens-title">Аллергены</h3>',
          '<div id="wmb-allergens-content"></div>',
        '</div>',
      '</div>'
    ].join(''));
    var modal = el('#wmb-allergens-modal');
    var close = function(){ modal.classList.remove('open'); modal.setAttribute('aria-hidden','true'); };
    modal.querySelector('.back').addEventListener('click', close);
    modal.querySelector('.close').addEventListener('click', close);
    document.addEventListener('keydown', function(e){ if(e.key==='Escape') close(); });
  }

  function openIngredientsFor(id){
    var item = byId(id);
    if (!item) return;
    ensureIngredientsModal(); // Создаем модальное окно при первом использовании
    var modal = el('#wmb-ing-modal');
    el('#wmb-ing-title').textContent = item.name || 'Состав';
    el('#wmb-ing-text').textContent  = (item.ingredients || '').trim();
    modal.classList.add('open');
    modal.setAttribute('aria-hidden','false');
  }

  function openAllergensFor(id){
    var item = byId(id);
    if (!item) return;
    ensureAllergensModal(); // Создаем модальное окно при первом использовании
    var modal = el('#wmb-allergens-modal');
    el('#wmb-allergens-title').textContent = item.name || 'Аллергены';
    
    var arr = Array.isArray(item.allergens) ? item.allergens : [];
    var content = el('#wmb-allergens-content');
    if (arr.length){
      content.innerHTML = '<div class="allergens-list">' + arr.map(function(a){
        return '<span class="allergen-pill">'+escapeHtml(a)+'</span>';
      }).join('') + '</div>';
    } else {
      content.innerHTML = '<p>Аллергены не указаны</p>';
    }
    modal.classList.add('open');
    modal.setAttribute('aria-hidden','false');
  }

  /* ======== FILTERS ======== */
  function allSectionTitles(){ return (menu.sections||[]).map(function(s){return s.title}); }
  function allTags(){
    var set = new Set();
    (menu.sections||[]).forEach(function(s){ (s.items||[]).forEach(function(it){ normalizeTags(it.tags).forEach(function(t){ set.add(t) }) }) });
    return Array.from(set).sort(function(a,b){return a.localeCompare(b)});
  }
  function itemPasses(item, sectionTitle){
    var sOk = true, tOk = true;
    if (state.filters.sections && state.filters.sections.length){
      sOk = state.filters.sections.indexOf(sectionTitle) !== -1;
    }
    if (state.filters.tags && state.filters.tags.length){
      var itemTags = normalizeTags(item.tags);
      tOk = state.filters.tags.every(function(t){ return itemTags.indexOf(t)!==-1; });
    }
    return sOk && tOk;
  }

  /* ======== UI RENDER ======== */
  function render(root){
    if (!menu) { root.innerHTML = '<div class="wmb-loading">Загрузка меню…</div>'; return; }

    // Модальные окна создаем лениво (только при первом использовании)
    // ensureIngredientsModal() вызывается при первом клике

    var secTitles = allSectionTitles();
    var tags = allTags();
    var dcfg = menu.delivery_config || null;
    var delivery = dcfg ? computeDelivery(dcfg) : null;

    root.innerHTML = [
      '<div class="wmb-wrapper">',
        '<div class="wmb-header">',
          '<div>',
            // описание убрано — текст вводится на странице
            '<div id="wmb-banner" class="wmb-banner" aria-live="polite"></div>',
          '</div>',
        '</div>',

        '<div class="wmb-filters">',
          (secTitles.length ? [
            '<div class="wmb-filter-group">',
              '<div class="wmb-filter-title">Категории</div>',
              '<div class="wmb-chips">',
                secTitles.map(function(t){
                  var act = state.filters.sections.indexOf(t)!==-1 ? 'is-active' : '';
                  return '<button class="wmb-chip '+act+'" data-type="section" data-value="'+escapeHtml(t)+'">'+escapeHtml(t)+'</button>';
                }).join(''),
                '<button class="wmb-chip wmb-chip-reset" data-type="section" data-reset="1" '+(state.filters.sections.length?'':'disabled')+'>Сбросить</button>',
              '</div>',
            '</div>'
          ].join('') : ''),
          (tags.length ? [
            '<div class="wmb-filter-group">',
              '<div class="wmb-filter-title">Теги</div>',
              '<div class="wmb-chips">',
                tags.map(function(t){
                  var act = state.filters.tags.indexOf(t)!==-1 ? 'is-active' : '';
                  return '<button class="wmb-chip '+act+'" data-type="tag" data-value="'+escapeHtml(t)+'">'+escapeHtml(t)+'</button>';
                }).join(''),
                '<button class="wmb-chip wmb-chip-reset" data-type="tag" data-reset="1" '+(state.filters.tags.length?'':'disabled')+'>Сбросить</button>',
              '</div>',
            '</div>'
          ].join('') : ''),
        '</div>',

        '<div class="wmb-body">',
          '<div class="wmb-catalog">',
            (menu.sections||[]).map(renderSection).join('') || '<div class="wmb-empty">Прямо сейчас мы разрабатываем новое меню для вас, скоро оно появится здесь.</div>',
          '</div>',
          '<aside class="wmb-sidebar">',
            '<div class="wmb-summary">',
              '<div class="wmb-summary-row"><span>Порций</span><strong id="wmb-total-portions">'+totalPortions()+'</strong></div>',
              '<div class="wmb-summary-row"><span>Итого</span><strong id="wmb-total-price">'+money(totalPrice())+'</strong></div>',
              '<button id="wmb-checkout" class="wmb-checkout-btn" '+(totalPortions()===0?'disabled':'')+'>Перейти к оформлению</button>',
            '</div>',
          '</aside>',
        '</div>',
        // Mobile sticky bar
        '<div class="wmb-sticky">',
          '<div style="display:flex;flex-direction:column;gap:2px">',
            '<div style="font-size:12px;color:#666">Итого</div>',
            '<div style="font-weight:700" id="wmb-total-price-mobile">'+money(totalPrice())+'</div>',
          '</div>',
          '<button id="wmb-checkout-mobile" class="wmb-checkout-btn" '+(totalPortions()===0?'disabled':'')+'>Перейти к оформлению</button>',
        '</div>',
      '</div>'
    ].join("");

    if (delivery && dcfg){ renderBanner(el('#wmb-banner', root), dcfg, delivery); }

    // фильтры
    els('.wmb-chip', root).forEach(function(chip){
      var type = chip.getAttribute('data-type');
      var val  = chip.getAttribute('data-value');
      var reset= chip.getAttribute('data-reset')==='1';
      chip.addEventListener('click', function(){
        if (reset){
          if (type==='section') state.filters.sections = [];
          if (type==='tag')     state.filters.tags = [];
          persist(); render(root); return;
        }
        if (type==='section'){
          var arr = state.filters.sections;
          var i = arr.indexOf(val);
          if (i===-1) arr.push(val); else arr.splice(i,1);
        } else if (type==='tag'){
          var arr = state.filters.tags;
          var i = arr.indexOf(val);
          if (i===-1) arr.push(val); else arr.splice(i,1);
        }
        persist(); render(root);
      });
    });

    // Используем один глобальный обработчик для всех кликов (делегирование событий)
    // Это предотвращает дублирование обработчиков при каждом render()

    // Кнопки checkout - используем делегирование через глобальный обработчик
    // delivery передается через замыкание в onCheckout
    var checkoutBtn = el('#wmb-checkout', root);
    var checkoutBtnMobile = el('#wmb-checkout-mobile', document);
    if (checkoutBtn) {
      checkoutBtn.setAttribute('data-delivery', JSON.stringify(delivery));
      checkoutBtn.addEventListener('click', function(e){
        e.preventDefault();
        e.stopPropagation();
        onCheckout(delivery);
      });
    }
    if (checkoutBtnMobile) {
      checkoutBtnMobile.setAttribute('data-delivery', JSON.stringify(delivery));
      checkoutBtnMobile.addEventListener('click', function(e){
        e.preventDefault();
        e.stopPropagation();
        onCheckout(delivery);
      });
    }
  }

  function renderSection(section){
    var items = (section.items||[])
      .map(function(it){ return Object.assign({_sectionTitle: section.title}, it); })
      .filter(function(it){ return itemPasses(it, section.title); });

    if (!items.length) return "";
    return [
      '<section class="wmb-section">',
        '<h2 class="wmb-section-title">'+escapeHtml(section.title)+'</h2>',
        '<div class="wmb-grid">',
          items.map(renderCard).join(''),
        '</div>',
      '</section>'
    ].join('');
  }

  function renderCard(item){
    var q = state.qty[item.id] || 0;
    var unit = item.unit || item.unit_text || item.unit_label || "";
    var tags = normalizeTags(item.tags);
    var allergens = Array.isArray(item.allergens) ? item.allergens : [];
    var hasIngredients = !!(item.ingredients && String(item.ingredients).trim().length);
    var hasAllergens = allergens.length > 0;
    var inCart = q > 0; // Индикатор что товар уже в корзине

    return [
      '<div class="wmb-card' + (inCart ? ' wmb-card-in-cart' : '') + '" data-item-id="' + escapeHtml(item.id) + '">',
        '<div class="wmb-card-title">',
          escapeHtml(item.name),
          (inCart ? '<span class="wmb-in-cart-badge">В корзине</span>' : ''),
          '<div class="wmb-card-buttons">',
            (hasIngredients ? '<button class="wmb-ing-btn" data-id="'+item.id+'" aria-label="Состав блюда '+escapeHtml(item.name)+'">Состав</button>' : ''),
            (hasAllergens ? '<button class="wmb-allergens-btn" data-id="'+item.id+'" aria-label="Аллергены блюда '+escapeHtml(item.name)+'">Аллергены</button>' : ''),
          '</div>',
        '</div>',
        '<div class="wmb-card-meta">',
          '<span>'+money(Number(item.price)||0)+'</span>',
          (unit ? '<span class="wmb-unit">'+escapeHtml(unit)+'</span>' : ''),
        '</div>',
        (tags.length? '<div class="wmb-card-tags">'+tags.map(function(t){return '<span class="wmb-tag">'+escapeHtml(t)+'</span>'}).join('')+'</div>' : ''),
        '<div class="wmb-qty">',
          '<button class="wmb-qty-dec" data-id="'+item.id+'" '+(q===0?'disabled':'')+' aria-label="Уменьшить">–</button>',
          '<span class="wmb-qty-value" aria-live="polite">'+q+'</span>',
          '<button class="wmb-qty-inc" data-id="'+item.id+'" aria-label="Увеличить">+</button>',
        '</div>',
      '</div>'
    ].join('');
  }

  // Оптимизированное обновление UI без полного перерендера
  function updateSummary(){
    var p = el('#wmb-total-price');
    var pm = el('#wmb-total-price-mobile');
    var pp = el('#wmb-total-portions');
    if(p) p.textContent = money(totalPrice());
    if(pm) pm.textContent = money(totalPrice());
    if(pp) pp.textContent = totalPortions();
    
    var checkout = el('#wmb-checkout');
    var checkoutMobile = el('#wmb-checkout-mobile');
    var hasItems = totalPortions() > 0;
    if(checkout) checkout.disabled = !hasItems;
    if(checkoutMobile) checkoutMobile.disabled = !hasItems;
  }

  function updateCardQty(cardEl, id, qty){
    var qtyValue = cardEl.querySelector('.wmb-qty-value');
    var qtyDec = cardEl.querySelector('.wmb-qty-dec');
    var inCartBadge = cardEl.querySelector('.wmb-in-cart-badge');
    
    if(qtyValue) qtyValue.textContent = qty;
    if(qtyDec) qtyDec.disabled = qty === 0;
    
    if(qty > 0) {
      cardEl.classList.add('wmb-card-in-cart');
      if(!inCartBadge) {
        var badge = document.createElement('span');
        badge.className = 'wmb-in-cart-badge';
        badge.textContent = 'В корзине';
        var title = cardEl.querySelector('.wmb-card-title');
        if(title) title.appendChild(badge);
      }
    } else {
      cardEl.classList.remove('wmb-card-in-cart');
      if(inCartBadge) inCartBadge.remove();
    }
  }

  function changeQty(id, delta, root){
    var cur = state.qty[id] || 0;
    var next = cur + delta;
    if (next < 0) next = 0;
    if (next !== cur) {
      state.qty[id] = next;
      if (next === 0) delete state.qty[id];
      persist();
      
      // Обновляем только нужную карточку и summary, без полного перерендера
      var cardEl = root.querySelector('[data-item-id="' + id + '"]');
      if(cardEl) {
        updateCardQty(cardEl, id, next);
      } else {
        // Если карточка не найдена, делаем полный перерендер
        render(root);
        return;
      }
      updateSummary();
    }
  }

  /* ======== DESKTOP STICKY ======== */
  // Принудительное применение sticky через JavaScript для надежности
  function setupDesktopSticky(root){
    if (window.innerWidth < 769) return;
    
    var sidebar = root.querySelector('.wmb-sidebar');
    if (!sidebar) return;
    
    var bodyEl = root.querySelector('.wmb-body');
    if (!bodyEl) return;
    
    // Принудительно применяем sticky стили
    function applySticky(){
      if (window.innerWidth < 769) return;
      
      // Убираем ограничения от всех родителей, включая WordPress контейнеры
      var parents = [
        root,
        root.querySelector('.wmb-wrapper'),
        bodyEl,
        root.querySelector('.wmb-catalog'),
        document.body,
        document.documentElement
      ];
      
      // Также проверяем WordPress контейнеры
      var wpContainers = [
        document.querySelector('.wp-site-blocks'),
        document.querySelector('main'),
        document.querySelector('.gl-container'),
        root.closest('.wp-block-group'),
        root.closest('.wp-block-post-content'),
        root.closest('main')
      ];
      
      parents = parents.concat(wpContainers);
      
      parents.forEach(function(parent){
        if (parent) {
          parent.style.overflow = 'visible';
          parent.style.overflowX = 'visible';
          parent.style.overflowY = 'visible';
          parent.style.transform = 'none';
          parent.style.contain = 'none';
          parent.style.isolation = 'auto';
          // Убеждаемся что нет height: 100% или других ограничений
          if (parent.style.height && parent.style.height === '100%') {
            parent.style.height = 'auto';
          }
        }
      });
      
      // КРИТИЧНО: Убеждаемся что родительский grid контейнер имеет достаточную высоту
      // Высота должна быть больше высоты viewport для работы sticky
      var bodyHeight = bodyEl.scrollHeight;
      var viewportHeight = window.innerHeight;
      if (bodyHeight < viewportHeight) {
        // Если контент короче экрана, увеличиваем min-height
        bodyEl.style.minHeight = (viewportHeight + 100) + 'px';
      } else {
        bodyEl.style.minHeight = '100vh';
      }
      
      bodyEl.style.display = 'grid';
      bodyEl.style.gridTemplateColumns = '1fr 320px';
      bodyEl.style.alignItems = 'start';
      bodyEl.style.gap = '24px';
      bodyEl.style.position = 'relative'; // Важно для sticky
      
      // Принудительно применяем sticky к sidebar
      if (sidebar) {
        sidebar.style.position = 'sticky';
        sidebar.style.top = '24px';
        sidebar.style.zIndex = '100';
        sidebar.style.alignSelf = 'start';
        sidebar.style.height = 'fit-content';
        sidebar.style.maxHeight = 'calc(100vh - 48px)';
        sidebar.style.overflowY = 'auto';
        sidebar.style.display = 'block';
        sidebar.style.visibility = 'visible';
        sidebar.style.willChange = 'transform';
      }
      
      // Убеждаемся что wrapper тоже не ограничивает
      var wrapperEl = root.querySelector('.wmb-wrapper');
      if (wrapperEl) {
        wrapperEl.style.minHeight = '100vh';
        wrapperEl.style.position = 'relative';
      }
      
      // КРИТИЧНО: Проверяем все WordPress контейнеры до body
      var wpMain = root.closest('main');
      if (wpMain) {
        wpMain.style.overflow = 'visible';
        wpMain.style.minHeight = '100vh';
      }
      
      var wpSiteBlocks = document.querySelector('.wp-site-blocks');
      if (wpSiteBlocks) {
        wpSiteBlocks.style.overflow = 'visible';
        wpSiteBlocks.style.minHeight = '100vh';
      }
      
      // Убеждаемся что body и html не ограничивают
      document.body.style.overflowY = 'auto';
      document.body.style.overflowX = 'hidden';
      document.documentElement.style.overflowY = 'auto';
      document.documentElement.style.overflowX = 'hidden';
    }
    
    // Применяем сразу
    applySticky();
    
    // Применяем после небольшой задержки (когда контент загрузится)
    setTimeout(applySticky, 100);
    setTimeout(applySticky, 500);
    
    // Применяем при изменении размера окна
    var resizeTimeout;
    window.addEventListener('resize', function(){
      clearTimeout(resizeTimeout);
      resizeTimeout = setTimeout(applySticky, 100);
    });
    
    // Применяем при прокрутке (на случай если что-то сбрасывает стили)
    var scrollTimeout;
    window.addEventListener('scroll', function(){
      clearTimeout(scrollTimeout);
      scrollTimeout = setTimeout(applySticky, 50);
    });
    
    // ДОПОЛНИТЕЛЬНО: Fallback с position: fixed если sticky не работает
    var fixedFallback = false;
    var sidebarRect = null;
    var bodyElRect = null;
    
    function checkAndApplyFixedFallback(){
      if (window.innerWidth < 769) return;
      if (!sidebar || !bodyEl) return;
      
      var computed = window.getComputedStyle(sidebar);
      if (computed.position === 'sticky') {
        // Sticky работает, проверяем что он действительно "прилипает"
        var currentRect = sidebar.getBoundingClientRect();
        if (!sidebarRect) {
          sidebarRect = currentRect;
          bodyElRect = bodyEl.getBoundingClientRect();
        }
        
        // Если sidebar не двигается при прокрутке, значит sticky не работает
        window.addEventListener('scroll', function checkSticky(){
          var newRect = sidebar.getBoundingClientRect();
          var scrollY = window.scrollY || window.pageYOffset;
          
          // Если sidebar не "прилипает" (его top меняется при прокрутке), используем fixed
          if (scrollY > 100 && newRect.top > 50) {
            // Sticky не работает, переключаемся на fixed
            if (!fixedFallback) {
              fixedFallback = true;
              console.log('Sticky не работает, переключаемся на position: fixed');
              
              var initialTop = bodyEl.getBoundingClientRect().top + window.scrollY;
              var sidebarWidth = sidebar.offsetWidth;
              
              function updateFixedPosition(){
                if (window.innerWidth < 769) return;
                var scrollTop = window.scrollY || window.pageYOffset;
                var bodyTop = bodyEl.getBoundingClientRect().top + scrollTop;
                var bodyBottom = bodyTop + bodyEl.offsetHeight;
                var viewportBottom = scrollTop + window.innerHeight;
                
                // Вычисляем позицию для fixed
                if (scrollTop < initialTop) {
                  // Еще не дошли до начала body
                  sidebar.style.position = 'absolute';
                  sidebar.style.top = 'auto';
                } else if (viewportBottom > bodyBottom - sidebar.offsetHeight) {
                  // Дошли до конца body
                  sidebar.style.position = 'absolute';
                  sidebar.style.top = (bodyBottom - sidebar.offsetHeight - bodyTop) + 'px';
                } else {
                  // В середине - используем fixed
                  sidebar.style.position = 'fixed';
                  sidebar.style.top = '24px';
                  sidebar.style.width = sidebarWidth + 'px';
                  sidebar.style.right = 'auto';
                  // Вычисляем left на основе позиции body
                  var bodyLeft = bodyEl.getBoundingClientRect().left;
                  var bodyWidth = bodyEl.offsetWidth;
                  sidebar.style.left = (bodyLeft + bodyWidth - sidebarWidth - 24) + 'px';
                }
              }
              
              window.addEventListener('scroll', updateFixedPosition);
              window.addEventListener('resize', updateFixedPosition);
              updateFixedPosition();
            }
          }
        }, { once: true, passive: true });
      }
    }
    
    setTimeout(checkAndApplyFixedFallback, 1000);
  }

  async function onCheckout(deliveryInfo){
    try{
      // Проверяем, что у нас есть товары для добавления
      if (Object.keys(state.qty).length === 0) {
        alert('Добавьте товары перед оформлением заказа');
        return;
      }
      
      var payload = {
        week: state.week || "",
        items: Object.fromEntries(Object.entries(state.qty).map(function(kv){return [kv[0], Number(kv[1])]})),
        total_portions: totalPortions(),
        total_price: Number((Math.round(totalPrice()*100)/100).toFixed(2))
      };
      
      // Проверяем, что payload корректный
      if (payload.total_price <= 0) {
        alert('Ошибка: некорректная сумма заказа');
        return;
      }
      
      // Сначала проверяем, есть ли уже набор в корзине
      var cartResponse = await fetch(CFG.ajax_url, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
        body: 'action=wmb_get_cart_contents&nonce=' + CFG.nonce,
        credentials: 'same-origin'
      });
      
      var existingCartItem = null;
      if (cartResponse.ok) {
        try {
          var cartResult = await cartResponse.json();
          if (cartResult.success && cartResult.data && cartResult.data.items) {
            // Ищем существующий товар meal builder
            existingCartItem = cartResult.data.items.find(function(item) {
              return item.wmb_payload && item.product_id == CFG.product_id;
            });
          }
        } catch (parseError) {
          console.error('Ошибка парсинга ответа корзины:', parseError);
        }
      }
      
      // Если есть существующий товар, сначала удаляем его
      if (existingCartItem) {
        // Используем наш собственный AJAX для удаления товара
        var removeResponse = await fetch(CFG.ajax_url, {
          method: 'POST',
          headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
          body: 'action=wmb_remove_cart_item&cart_item_key=' + existingCartItem.key + '&nonce=' + CFG.nonce,
          credentials: 'same-origin'
        });
        
        // Ждем немного, чтобы удаление завершилось
        await new Promise(resolve => setTimeout(resolve, 200));
      }
      
      // Добавляем новый товар
      var body = new URLSearchParams();
      body.append('action', 'wmb_add_to_cart');
      body.append('nonce', CFG.nonce);
      body.append('product_id', String(CFG.product_id));
      body.append('payload', JSON.stringify(payload));

      var res = await fetch(CFG.ajax_url, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
        body: body.toString(),
        credentials: 'same-origin'
      });
      
      if (!res.ok) {
        throw new Error('HTTP ' + res.status + ': ' + res.statusText);
      }
      
      var json = await res.json();
      
      if (!json || !json.success) {
        var errorMsg = json && json.data ? json.data : 'Неизвестная ошибка сервера';
        throw new Error(errorMsg);
      }
      
      // НЕ очищаем локальное состояние - оставляем синхронизированным с корзиной
      
      if (json.data && json.data.redirect) {
        window.location.href = json.data.redirect;
      } else {
        alert('Обновлено в корзине, но ссылка на корзину не получена.');
      }
    }catch(e){
      alert('ОШИБКА: ' + e.message);
      console.error('Ошибка в onCheckout:', e);
    }
  }

  async function boot(){
    var root = document.getElementById('meal-builder-root');
    if (!root) return;

    // Параллельно загружаем меню и синхронизируемся с корзиной
    var menuPromise = fetch(MENU_URL, {credentials:'same-origin'}).then(function(res){
      if (!res.ok) throw new Error('HTTP '+res.status);
      return res.json();
    }).catch(function(e){
      console.error('Не удалось загрузить меню из', MENU_URL, e);
      return { description:'', sections: [] };
    });
    
    var cartPromise = syncWithCart();
    
    // Ждем оба запроса параллельно
    menu = await menuPromise;
    await cartPromise;
    
    restore();
    render(root);
    setupDesktopSticky(root);
  }

  // Глобальный обработчик событий для делегирования (один раз, не дублируется)
  var globalClickHandler = function(e){
    var t = e.target;
    var root = document.getElementById('meal-builder-root');
    if (!root) return;
    
    // Кнопки количества
    if (t.classList.contains('wmb-qty-inc')) {
      e.preventDefault();
      e.stopPropagation();
      changeQty(t.dataset.id, +1, root);
      updateSummary();
    } else if (t.classList.contains('wmb-qty-dec')) {
      e.preventDefault();
      e.stopPropagation();
      changeQty(t.dataset.id, -1, root);
      updateSummary();
    }
    // Кнопки состава и аллергенов
    else if (t.classList.contains('wmb-ing-btn')) {
      e.preventDefault();
      e.stopPropagation();
      openIngredientsFor(t.getAttribute('data-id'));
    } else if (t.classList.contains('wmb-allergens-btn')) {
      e.preventDefault();
      e.stopPropagation();
      openAllergensFor(t.getAttribute('data-id'));
    }
  };
  
  // Добавляем обработчик один раз на document
  document.addEventListener('click', globalClickHandler, true);

  // Запускаем сразу, если DOM уже готов, иначе ждем DOMContentLoaded
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
