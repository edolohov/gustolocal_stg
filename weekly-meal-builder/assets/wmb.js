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
  var menuMercat = null;
  var menuGlovoUber = null;
  var state = { week:"", qty:{}, qtyMercat:{}, filters:{ sections:[], tags:[] }, activeTab: 'smart_food' };
  var countdownTimer = null;
  var cartUpdateTimer = null; // Таймер для debounce обновления корзины

  function flatItems(menuData){
    menuData = menuData || menu;
    return (menuData && menuData.sections ? menuData.sections : [])
      .flatMap(function(s){
        return (s.items||[]).map(function(it){
          return Object.assign({_sectionTitle:s.title}, it);
        });
      });
  }
  function getActiveMenu(){
    if (state.activeTab === 'mercat') return menuMercat;
    if (state.activeTab === 'glovo_uber') return menuGlovoUber;
    return menu;
  }
  function getActiveQty(){
    if (state.activeTab === 'mercat') return state.qtyMercat;
    return state.qty;
  }
  function byId(id, menuData){ 
    var allMenus = [menu, menuMercat, menuGlovoUber].filter(Boolean);
    for (var i = 0; i < allMenus.length; i++) {
      var found = flatItems(allMenus[i]).find(function(i){ return String(i.id)===String(id); });
      if (found) return found;
    }
    return null;
  }
  function totalPortions(){ 
    return Object.values(state.qty).reduce(function(a,b){return a+b},0) + 
           Object.values(state.qtyMercat).reduce(function(a,b){return a+b},0);
  }
  function totalPrice(){
    var smartFoodTotal = Object.entries(state.qty).reduce(function(sum, kv){
      var it=byId(kv[0]); return sum + (it ? (Number(it.price)||0)*kv[1] : 0);
    }, 0);
    var mercatTotal = Object.entries(state.qtyMercat).reduce(function(sum, kv){
      var it=byId(kv[0]); return sum + (it ? (Number(it.price)||0)*kv[1] : 0);
    }, 0);
    return smartFoodTotal + mercatTotal;
  }
  function persist(){ try{ localStorage.setItem(LS_KEY, JSON.stringify(state)); }catch(e){} }
  function restore(){
    try{
      var raw=localStorage.getItem(LS_KEY); if(!raw) return;
      var saved=JSON.parse(raw);
      if(saved && typeof saved==="object"){
        state.qty=saved.qty||{};
        state.qtyMercat=saved.qtyMercat||{};
        state.filters=saved.filters||{sections:[],tags:[]};
        state.activeTab=saved.activeTab||'smart_food';
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
      var cartQtyMercat = {};
      var needsSuperfood = false;
      var needsMercat = false;
      
      // Собираем товары из корзины
      cartItems.forEach(function(item){
        if (item.wmb_payload) {
          try{
            var payload = JSON.parse(item.wmb_payload);
            var saleType = payload.sale_type || 'smart_food';
            var items = payload.items || {};
            
            Object.entries(items).forEach(function([id, qty]){
                if (qty > 0) {
                if (saleType === 'mercat') {
                  cartQtyMercat[id] = qty;
                  needsMercat = true;
                } else {
                  cartQty[id] = qty;
                  needsSuperfood = true;
                }
            }
            });
          }catch(e){
            console.error('Error parsing cart payload:', e);
          }
        }
      });
      
      // ВСЕГДА синхронизируем с корзиной
      state.qty = cartQty;
      state.qtyMercat = cartQtyMercat;
      persist();
      
      // Загружаем необходимые меню для корректного расчета суммы
      var loadPromises = [];
      if (needsSuperfood && !menu) {
        loadPromises.push(
          fetch(MENU_URL + '?sale_type=smart_food', {credentials:'same-origin'}).then(function(res){
            if (!res.ok) throw new Error('HTTP '+res.status);
            return res.json();
          }).then(function(data){
            menu = data;
          }).catch(function(e){
            console.error('Не удалось загрузить меню Superfood из', MENU_URL, e);
            menu = { description:'', sections: [] };
          })
        );
      }
      if (needsMercat && !menuMercat) {
        loadPromises.push(
          fetch(MENU_URL + '?sale_type=mercat', {credentials:'same-origin'}).then(function(res){
            if (!res.ok) throw new Error('HTTP '+res.status);
            return res.json();
          }).then(function(data){
            menuMercat = data;
          }).catch(function(e){
            console.error('Не удалось загрузить меню Mercat из', MENU_URL, e);
            menuMercat = { description:'', sections: [] };
          })
        );
      }
      
      // Ждем загрузки всех необходимых меню
      if (loadPromises.length > 0) {
        await Promise.all(loadPromises);
      }
      
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

  /* ======== PHOTO MODAL ======== */
  function ensurePhotoModal(){
    if (document.getElementById('wmb-photo-modal')) return;
    document.body.insertAdjacentHTML('beforeend', [
      '<div id="wmb-photo-modal" aria-hidden="true">',
        '<div class="back" tabindex="-1"></div>',
        '<div class="dialog" role="dialog" aria-modal="true" aria-labelledby="wmb-photo-title">',
          '<button class="close" aria-label="Закрыть">×</button>',
          '<h3 id="wmb-photo-title"></h3>',
          '<div class="wmb-photo-container">',
            '<img id="wmb-photo-img" src="" alt="">',
          '</div>',
        '</div>',
      '</div>'
    ].join(''));
    var modal = el('#wmb-photo-modal');
    var close = function(){ modal.classList.remove('open'); modal.setAttribute('aria-hidden','true'); };
    modal.querySelector('.back').addEventListener('click', close);
    modal.querySelector('.close').addEventListener('click', close);
    document.addEventListener('keydown', function(e){ if(e.key==='Escape') close(); });
  }

  function openPhotoFor(url, alt, title){
    if (!url) return;
    ensurePhotoModal();
    var modal = el('#wmb-photo-modal');
    el('#wmb-photo-title').textContent = title || 'Фото блюда';
    var img = el('#wmb-photo-img');
    img.src = url;
    img.alt = alt || title || 'Фото блюда';
    modal.classList.add('open');
    modal.setAttribute('aria-hidden','false');
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
  function allSectionTitles(menuData){
    menuData = menuData || getActiveMenu();
    return (menuData && menuData.sections ? menuData.sections : []).map(function(s){return s.title});
  }
  function allTags(menuData){
    menuData = menuData || getActiveMenu();
    var set = new Set();
    (menuData && menuData.sections ? menuData.sections : []).forEach(function(s){ 
      (s.items||[]).forEach(function(it){ 
        normalizeTags(it.tags).forEach(function(t){ set.add(t) }) 
      }) 
    });
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
    var activeMenu = getActiveMenu();
    if (!activeMenu && state.activeTab !== 'glovo_uber') { 
      root.innerHTML = '<div class="wmb-loading">Загрузка меню…</div>'; 
      return; 
    }

    // Модальные окна создаем лениво (только при первом использовании)
    // ensureIngredientsModal() вызывается при первом клике

    var secTitles = allSectionTitles(activeMenu);
    var tags = allTags(activeMenu);
    var dcfg = menu && menu.delivery_config ? menu.delivery_config : null;
    var delivery = dcfg ? computeDelivery(dcfg) : null;

    root.innerHTML = [
      '<div class="wmb-wrapper">',
        '<div class="wmb-tabs">',
          '<button class="wmb-tab' + (state.activeTab === 'smart_food' ? ' wmb-tab-active' : '') + '" data-tab="smart_food">Superfood</button>',
          '<button class="wmb-tab' + (state.activeTab === 'mercat' ? ' wmb-tab-active' : '') + '" data-tab="mercat">Mercat</button>',
          // Вкладка Glovo/Uber временно скрыта
          // '<button class="wmb-tab' + (state.activeTab === 'glovo_uber' ? ' wmb-tab-active' : '') + '" data-tab="glovo_uber">Glovo / Uber</button>',
        '</div>',
        '<div class="wmb-header">',
          '<div>',
            // Баннер доставки полностью удален
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
            (state.activeTab === 'glovo_uber' ? renderGlovoUber(menuGlovoUber) : 
             (activeMenu && activeMenu.sections ? activeMenu.sections.map(renderSection).join('') : 
              '<div class="wmb-empty">Прямо сейчас мы разрабатываем новое меню для вас, скоро оно появится здесь.</div>')),
          '</div>',
          '<aside class="wmb-sidebar">',
            '<div class="wmb-summary">',
              '<div class="wmb-summary-row"><span>Порций</span><strong id="wmb-total-portions">'+totalPortions()+'</strong></div>',
              '<div class="wmb-summary-row"><span>Итого</span><strong id="wmb-total-price">'+money(totalPrice())+'</strong></div>',
              '<button id="wmb-checkout" class="wmb-checkout-btn" '+(totalPortions()===0?'disabled':'')+'>Перейти к оформлению</button>',
              '<button id="wmb-clear-cart" class="wmb-clear-cart-btn" '+(totalPortions()===0?'disabled':'')+'>Очистить корзину</button>',
            '</div>',
          '</aside>',
        '</div>',
        // Mobile sticky bar
        '<div class="wmb-sticky">',
          '<div style="display:flex;flex-direction:column;gap:2px">',
            '<div style="font-size:12px;color:#666">Итого</div>',
            '<div style="font-weight:700" id="wmb-total-price-mobile">'+money(totalPrice())+'</div>',
          '</div>',
          '<div style="display:flex;gap:8px;flex-direction:column;">',
          '<button id="wmb-checkout-mobile" class="wmb-checkout-btn" '+(totalPortions()===0?'disabled':'')+'>Перейти к оформлению</button>',
            '<button id="wmb-clear-cart-mobile" class="wmb-clear-cart-btn" '+(totalPortions()===0?'disabled':'')+'>Очистить корзину</button>',
          '</div>',
        '</div>',
      '</div>'
    ].join("");

    // Баннер доставки полностью отключен
    // if (delivery && dcfg){ renderBanner(el('#wmb-banner', root), dcfg, delivery); }

    // Переключение табов
    els('.wmb-tab', root).forEach(function(tab){
      tab.addEventListener('click', function(){
        var newTab = tab.getAttribute('data-tab');
        if (newTab && newTab !== state.activeTab) {
          state.activeTab = newTab;
          persist();
          // Обновляем URL hash для возможности поделиться ссылкой
          if (history.pushState) {
            var newUrl = window.location.pathname + '#' + newTab;
            history.pushState(null, '', newUrl);
          }
          
          // Ленивая загрузка меню для переключенной вкладки
          loadMenuForTab(newTab).then(function(){
            render(root);
          });
        }
      });
    });

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
    
    // Кнопка очистки корзины (десктоп)
    var clearCartBtn = el('#wmb-clear-cart', root);
    if (clearCartBtn) {
      clearCartBtn.addEventListener('click', function(e){
        e.preventDefault();
        e.stopPropagation();
        if (confirm('Вы уверены, что хотите очистить корзину?')) {
          clearCart();
        }
      });
    }
    
    // Кнопка очистки корзины (мобилка)
    var clearCartBtnMobile = el('#wmb-clear-cart-mobile', document);
    if (clearCartBtnMobile) {
      clearCartBtnMobile.addEventListener('click', function(e){
        e.preventDefault();
        e.stopPropagation();
        if (confirm('Вы уверены, что хотите очистить корзину?')) {
          clearCart();
        }
      });
    }
  }
  
  async function clearCart(){
    try {
      // Получаем все товары из корзины
      var cartResponse = await fetch(CFG.ajax_url, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
        body: 'action=wmb_get_cart_contents&nonce=' + CFG.nonce,
        credentials: 'same-origin'
      });
      
      if (cartResponse.ok) {
        var cartResult = await cartResponse.json();
        if (cartResult.success && cartResult.data && cartResult.data.items) {
          // Удаляем только товары Meal Builder (Smart Food и Mercat)
          var itemsToRemove = cartResult.data.items.filter(function(item) {
            return item.wmb_payload && (item.product_id == CFG.product_id || item.product_id == (CFG.mercat_product_id || CFG.product_id));
          });
          
          // Удаляем товары по очереди
          for (var i = 0; i < itemsToRemove.length; i++) {
            await fetch(CFG.ajax_url, {
              method: 'POST',
              headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
              body: 'action=wmb_remove_cart_item&cart_item_key=' + itemsToRemove[i].key + '&nonce=' + CFG.nonce,
              credentials: 'same-origin'
            });
            await new Promise(resolve => setTimeout(resolve, 100));
          }
        }
      }
      
      // Очищаем локальное состояние
      state.qty = {};
      state.qtyMercat = {};
      persist();
      
      // Обновляем UI
      var root = document.getElementById('meal-builder-root');
      if (root) {
        // Обновляем все карточки, чтобы убрать индикаторы "В корзине"
        var allCards = root.querySelectorAll('.wmb-card');
        allCards.forEach(function(card) {
          var id = card.getAttribute('data-item-id');
          if (id) {
            updateCardQty(card, id, 0);
          }
        });
        updateSummary();
      }
    } catch(e) {
      console.error('Ошибка при очистке корзины:', e);
      alert('Ошибка при очистке корзины: ' + e.message);
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
    var activeQty = getActiveQty();
    var q = activeQty[item.id] || 0;
    var unit = item.unit || item.unit_text || item.unit_label || "";
    var tags = normalizeTags(item.tags);
    var allergens = Array.isArray(item.allergens) ? item.allergens : [];
    var hasIngredients = !!(item.ingredients && String(item.ingredients).trim().length);
    var hasAllergens = allergens.length > 0;
    var inCart = q > 0; // Индикатор что товар уже в корзине
    var photoUrl = item.photo_url || '';
    var photoAlt = item.photo_alt || item.name || '';
    var nutrition = item.nutrition || '';
    var shelfLife = item.shelf_life || '';

    return [
      '<div class="wmb-card' + (inCart ? ' wmb-card-in-cart' : '') + '" data-item-id="' + escapeHtml(item.id) + '">',
        (photoUrl ? '<div class="wmb-card-image"><img src="'+escapeHtml(photoUrl)+'" alt="'+escapeHtml(photoAlt)+'" loading="lazy" class="wmb-photo-clickable" data-photo-url="'+escapeHtml(photoUrl)+'" data-photo-alt="'+escapeHtml(photoAlt)+'" data-photo-title="'+escapeHtml(item.name)+'"></div>' : ''),
        '<div class="wmb-card-content">',
        '<div class="wmb-card-title">',
          '<span class="wmb-card-title-text">'+escapeHtml(item.name)+'</span>',
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
          (nutrition ? '<div class="wmb-card-nutrition">'+escapeHtml(nutrition)+'</div>' : ''),
          (shelfLife ? '<div class="wmb-card-shelf-life">'+escapeHtml(shelfLife)+'</div>' : ''),
        (tags.length? '<div class="wmb-card-tags">'+tags.map(function(t){return '<span class="wmb-tag">'+escapeHtml(t)+'</span>'}).join('')+'</div>' : ''),
        '<div class="wmb-qty">',
          '<button class="wmb-qty-dec" data-id="'+item.id+'" '+(q===0?'disabled':'')+' aria-label="Уменьшить">–</button>',
          '<span class="wmb-qty-value" aria-live="polite">'+q+'</span>',
          '<button class="wmb-qty-inc" data-id="'+item.id+'" aria-label="Увеличить">+</button>',
          '</div>',
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
    var clearCart = el('#wmb-clear-cart');
    var clearCartMobile = el('#wmb-clear-cart-mobile');
    var hasItems = totalPortions() > 0;
    if(checkout) checkout.disabled = !hasItems;
    if(checkoutMobile) checkoutMobile.disabled = !hasItems;
    if(clearCart) clearCart.disabled = !hasItems;
    if(clearCartMobile) clearCartMobile.disabled = !hasItems;
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
        if(title) {
          // Добавляем после названия, но перед кнопками
          var titleText = title.querySelector('.wmb-card-title-text');
          if(titleText && titleText.nextSibling) {
            title.insertBefore(badge, titleText.nextSibling);
          } else {
            title.appendChild(badge);
          }
        }
      }
    } else {
      cardEl.classList.remove('wmb-card-in-cart');
      if(inCartBadge) inCartBadge.remove();
    }
  }

  // Ленивая загрузка меню для конкретной вкладки
  async function loadMenuForTab(tab){
    if (tab === 'smart_food' && !menu) {
      menu = await fetch(MENU_URL + '?sale_type=smart_food', {credentials:'same-origin'}).then(function(res){
        if (!res.ok) throw new Error('HTTP '+res.status);
        return res.json();
      }).catch(function(e){
        console.error('Не удалось загрузить меню Superfood из', MENU_URL, e);
        return { description:'', sections: [] };
      });
    } else if (tab === 'mercat' && !menuMercat) {
      menuMercat = await fetch(MENU_URL + '?sale_type=mercat', {credentials:'same-origin'}).then(function(res){
        if (!res.ok) throw new Error('HTTP '+res.status);
        return res.json();
      }).catch(function(e){
        console.error('Не удалось загрузить меню Mercat из', MENU_URL, e);
        return { description:'', sections: [] };
      });
    } else if (tab === 'glovo_uber' && !menuGlovoUber) {
      menuGlovoUber = await fetch(MENU_URL + '?sale_type=glovo_uber', {credentials:'same-origin'}).then(function(res){
        if (!res.ok) throw new Error('HTTP '+res.status);
        return res.json();
      }).catch(function(e){
        console.error('Не удалось загрузить меню Glovo/Uber из', MENU_URL, e);
        return { description:'', sections: [] };
      });
    }
  }

  function changeQty(id, delta, root){
    var activeQty = getActiveQty();
    var cur = activeQty[id] || 0;
    var next = cur + delta;
    if (next < 0) next = 0;
    if (next !== cur) {
      activeQty[id] = next;
      if (next === 0) delete activeQty[id];
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
      
      // НЕ обновляем корзину автоматически - только при нажатии "Перейти к оформлению"
      // Это предотвращает дублирование товаров и лишние запросы
    }
  }
  
  // Автоматическое обновление корзины на основе текущего состояния
  async function updateCartFromState(){
    try {
      var hasSmartFood = Object.keys(state.qty).length > 0;
      var hasMercat = Object.keys(state.qtyMercat).length > 0;
      
      if (!hasSmartFood && !hasMercat) {
        // Если корзина пустая, очищаем корзину WooCommerce
        await clearCart();
        return;
      }
      
      // Обновляем Smart Food товары
      if (hasSmartFood) {
        var payload = {
          week: state.week || "",
          items: Object.fromEntries(Object.entries(state.qty).map(function(kv){return [kv[0], Number(kv[1])]})),
          total_portions: Object.values(state.qty).reduce(function(a,b){return a+b},0),
          total_price: Object.entries(state.qty).reduce(function(sum, kv){
            var it=byId(kv[0]); return sum + (it ? (Number(it.price)||0)*kv[1] : 0);
          }, 0),
          sale_type: 'smart_food'
        };
        await addToCart(payload, CFG.product_id);
      } else {
        // Если Smart Food товаров нет, удаляем их из корзины
        await removeCartItemsByType('smart_food', CFG.product_id);
      }
      
      // Обновляем Mercat товары
      if (hasMercat) {
        var mercatPayload = {
          week: "",
          items: Object.fromEntries(Object.entries(state.qtyMercat).map(function(kv){return [kv[0], Number(kv[1])]})),
          total_portions: Object.values(state.qtyMercat).reduce(function(a,b){return a+b},0),
          total_price: Object.entries(state.qtyMercat).reduce(function(sum, kv){
            var it=byId(kv[0]); return sum + (it ? (Number(it.price)||0)*kv[1] : 0);
          }, 0),
          sale_type: 'mercat'
        };
        var mercatProductId = CFG.mercat_product_id || CFG.product_id;
        await addToCart(mercatPayload, mercatProductId);
      } else {
        // Если Mercat товаров нет, удаляем их из корзины
        var mercatProductId = CFG.mercat_product_id || CFG.product_id;
        await removeCartItemsByType('mercat', mercatProductId);
      }
      
      // Синхронизируемся с корзиной после обновления
      await syncWithCart();
      
      // Обновляем UI
      var root = document.getElementById('meal-builder-root');
      if (root) {
        updateSummary();
        // Обновляем визуальное состояние карточек
        Object.keys(state.qty).forEach(function(id) {
          var cardEl = root.querySelector('[data-item-id="' + id + '"]');
          if (cardEl) updateCardQty(cardEl, id, state.qty[id]);
        });
        Object.keys(state.qtyMercat).forEach(function(id) {
          var cardEl = root.querySelector('[data-item-id="' + id + '"]');
          if (cardEl) updateCardQty(cardEl, id, state.qtyMercat[id]);
        });
      }
    } catch(e) {
      console.error('Ошибка при автоматическом обновлении корзины:', e);
    }
  }
  
  // Удаление товаров определенного типа из корзины
  async function removeCartItemsByType(saleType, productId){
    try {
      var cartResponse = await fetch(CFG.ajax_url, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
        body: 'action=wmb_get_cart_contents&nonce=' + CFG.nonce,
        credentials: 'same-origin'
      });
      
      if (cartResponse.ok) {
        var cartResult = await cartResponse.json();
        if (cartResult.success && cartResult.data && cartResult.data.items) {
          var itemsToRemove = cartResult.data.items.filter(function(item) {
            if (!item.wmb_payload || item.product_id != productId) return false;
            try {
              var itemPayload = JSON.parse(item.wmb_payload);
              return itemPayload.sale_type === saleType;
            } catch(e) {
              return false;
            }
          });
          
          for (var i = 0; i < itemsToRemove.length; i++) {
            await fetch(CFG.ajax_url, {
              method: 'POST',
              headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
              body: 'action=wmb_remove_cart_item&cart_item_key=' + itemsToRemove[i].key + '&nonce=' + CFG.nonce,
              credentials: 'same-origin'
            });
            await new Promise(resolve => setTimeout(resolve, 100));
          }
        }
      }
    } catch(e) {
      console.error('Ошибка при удалении товаров из корзины:', e);
    }
  }
  
  function renderGlovoUber(menuData){
    if (!menuData || !menuData.sections) return '<div class="wmb-empty">Загрузка меню…</div>';
    
    var items = flatItems(menuData);
    if (!items.length) return '<div class="wmb-empty">Пока нет доступных позиций на Glovo/Uber.</div>';
    
    // Находим общие ссылки на Glovo и Uber Eats (берем из первого товара, у которого они есть)
    var glovoUrl = '';
    var uberUrl = '';
    for (var i = 0; i < items.length; i++) {
      if (items[i].glovo_url && !glovoUrl) glovoUrl = items[i].glovo_url;
      if (items[i].uber_url && !uberUrl) uberUrl = items[i].uber_url;
      if (glovoUrl && uberUrl) break;
    }
    
    // Используем renderSection для отображения по категориям, но с модифицированным renderCard
    var sectionsHtml = (menuData.sections || []).map(function(section) {
      var sectionItems = (section.items || [])
        .map(function(it) { return Object.assign({_sectionTitle: section.title}, it); })
        .filter(function(it) { return itemPasses(it, section.title); });
      
      if (!sectionItems.length) return '';
      
      return [
        '<section class="wmb-section">',
          '<h2 class="wmb-section-title">'+escapeHtml(section.title)+'</h2>',
          '<div class="wmb-grid">',
            sectionItems.map(renderGlovoUberCard).join(''),
          '</div>',
        '</section>'
      ].join('');
    }).join('');
    
    return [
      '<div class="wmb-glovo-uber-intro">',
        '<p>Заказывайте наши блюда через Glovo и Uber Eats. Доставка день в день!</p>',
        '<div class="wmb-glovo-uber-main-buttons">',
          (glovoUrl ? '<a href="'+escapeHtml(glovoUrl)+'" target="_blank" rel="noopener noreferrer" class="wmb-external-link wmb-glovo-link">Заказать на Glovo</a>' : ''),
          (uberUrl ? '<a href="'+escapeHtml(uberUrl)+'" target="_blank" rel="noopener noreferrer" class="wmb-external-link wmb-uber-link">Заказать на Uber Eats</a>' : ''),
        '</div>',
      '</div>',
      sectionsHtml
    ].join('');
  }
  
  function renderGlovoUberCard(item){
    // Та же карточка, что и обычная, но без кнопок добавления в корзину
    var unit = item.unit || item.unit_text || item.unit_label || "";
    var tags = normalizeTags(item.tags);
    var allergens = Array.isArray(item.allergens) ? item.allergens : [];
    var hasIngredients = !!(item.ingredients && String(item.ingredients).trim().length);
    var hasAllergens = allergens.length > 0;
    var photoUrl = item.photo_url || '';
    var photoAlt = item.photo_alt || item.name || '';
    var nutrition = item.nutrition || '';
    var shelfLife = item.shelf_life || '';

    return [
      '<div class="wmb-card wmb-card-glovo-uber" data-item-id="' + escapeHtml(item.id) + '">',
        (photoUrl ? '<div class="wmb-card-image"><img src="'+escapeHtml(photoUrl)+'" alt="'+escapeHtml(photoAlt)+'" loading="lazy" class="wmb-photo-clickable" data-photo-url="'+escapeHtml(photoUrl)+'" data-photo-alt="'+escapeHtml(photoAlt)+'" data-photo-title="'+escapeHtml(item.name)+'"></div>' : ''),
        '<div class="wmb-card-content">',
          '<div class="wmb-card-title">',
            '<span class="wmb-card-title-text">'+escapeHtml(item.name)+'</span>',
            '<div class="wmb-card-buttons">',
              (hasIngredients ? '<button class="wmb-ing-btn" data-id="'+item.id+'" aria-label="Состав блюда '+escapeHtml(item.name)+'">Состав</button>' : ''),
              (hasAllergens ? '<button class="wmb-allergens-btn" data-id="'+item.id+'" aria-label="Аллергены блюда '+escapeHtml(item.name)+'">Аллергены</button>' : ''),
            '</div>',
          '</div>',
          '<div class="wmb-card-meta">',
            '<span>'+money(Number(item.price)||0)+'</span>',
            (unit ? '<span class="wmb-unit">'+escapeHtml(unit)+'</span>' : ''),
          '</div>',
          (nutrition ? '<div class="wmb-card-nutrition">'+escapeHtml(nutrition)+'</div>' : ''),
          (shelfLife ? '<div class="wmb-card-shelf-life">'+escapeHtml(shelfLife)+'</div>' : ''),
          (tags.length? '<div class="wmb-card-tags">'+tags.map(function(t){return '<span class="wmb-tag">'+escapeHtml(t)+'</span>'}).join('')+'</div>' : ''),
        '</div>',
      '</div>'
    ].join('');
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
      var hasSmartFood = Object.keys(state.qty).length > 0;
      var hasMercat = Object.keys(state.qtyMercat).length > 0;
      
      if (!hasSmartFood && !hasMercat) {
        alert('Добавьте товары перед оформлением заказа');
        return;
      }
      
      // Добавляем Smart Food товары
      if (hasSmartFood) {
      var payload = {
        week: state.week || "",
        items: Object.fromEntries(Object.entries(state.qty).map(function(kv){return [kv[0], Number(kv[1])]})),
          total_portions: Object.values(state.qty).reduce(function(a,b){return a+b},0),
          total_price: Object.entries(state.qty).reduce(function(sum, kv){
            var it=byId(kv[0]); return sum + (it ? (Number(it.price)||0)*kv[1] : 0);
          }, 0),
          sale_type: 'smart_food'
        };
        
        await addToCart(payload, CFG.product_id);
      }
      
      // Добавляем Mercat товары отдельным продуктом
      if (hasMercat) {
        var mercatPayload = {
          week: "",
          items: Object.fromEntries(Object.entries(state.qtyMercat).map(function(kv){return [kv[0], Number(kv[1])]})),
          total_portions: Object.values(state.qtyMercat).reduce(function(a,b){return a+b},0),
          total_price: Object.entries(state.qtyMercat).reduce(function(sum, kv){
            var it=byId(kv[0]); return sum + (it ? (Number(it.price)||0)*kv[1] : 0);
          }, 0),
          sale_type: 'mercat'
        };
        
        // Используем отдельный продукт для Mercat
        var mercatProductId = CFG.mercat_product_id || CFG.product_id;
        await addToCart(mercatPayload, mercatProductId);
      }
      
      // Очищаем локальное состояние после успешного добавления в корзину
      state.qty = {};
      state.qtyMercat = {};
      persist();
      
      // Перенаправляем на страницу корзины
      var cartUrl = CFG.cart_url || '/cart/';
      window.location.href = cartUrl;
      return;
    }catch(e){
      alert('ОШИБКА: ' + e.message);
      console.error('Ошибка в onCheckout:', e);
    }
  }
  
  async function addToCart(payload, productId){
    try {
      // Проверяем, что payload корректный
      if (payload.total_price <= 0) {
        throw new Error('Некорректная сумма заказа');
      }
      
      // Сначала проверяем, есть ли уже товар того же типа в корзине
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
            // Ищем существующий товар того же типа продажи
            existingCartItem = cartResult.data.items.find(function(item) {
              if (!item.wmb_payload || item.product_id != productId) return false;
              try {
                var itemPayload = JSON.parse(item.wmb_payload);
                return itemPayload.sale_type === payload.sale_type;
              } catch(e) {
                return false;
              }
            });
          }
        } catch (parseError) {
          console.error('Ошибка парсинга ответа корзины:', parseError);
        }
      }
      
      // Если есть существующий товар того же типа, сначала удаляем его
      if (existingCartItem) {
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
      body.append('product_id', String(productId));
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
    } catch(e) {
      throw e;
    }
  }

  async function boot(){
    var root = document.getElementById('meal-builder-root');
    if (!root) return;

    // Проверяем hash в URL для открытия нужной вкладки
    var hash = window.location.hash.replace('#', '');
    if (hash && ['smart_food', 'mercat', 'glovo_uber'].indexOf(hash) !== -1) {
      state.activeTab = hash;
      persist();
    }

    // Оптимизация: загружаем только активную вкладку сразу, остальные - лениво
    var cartPromise = syncWithCart();
    
    // Загружаем меню для активной вкладки сразу
    if (state.activeTab === 'smart_food') {
      menu = await fetch(MENU_URL + '?sale_type=smart_food', {credentials:'same-origin'}).then(function(res){
      if (!res.ok) throw new Error('HTTP '+res.status);
      return res.json();
    }).catch(function(e){
        console.error('Не удалось загрузить меню Superfood из', MENU_URL, e);
      return { description:'', sections: [] };
    });
    } else if (state.activeTab === 'mercat') {
      menuMercat = await fetch(MENU_URL + '?sale_type=mercat', {credentials:'same-origin'}).then(function(res){
        if (!res.ok) throw new Error('HTTP '+res.status);
        return res.json();
      }).catch(function(e){
        console.error('Не удалось загрузить меню Mercat из', MENU_URL, e);
        return { description:'', sections: [] };
      });
    } else if (state.activeTab === 'glovo_uber') {
      menuGlovoUber = await fetch(MENU_URL + '?sale_type=glovo_uber', {credentials:'same-origin'}).then(function(res){
        if (!res.ok) throw new Error('HTTP '+res.status);
        return res.json();
      }).catch(function(e){
        console.error('Не удалось загрузить меню Glovo/Uber из', MENU_URL, e);
        return { description:'', sections: [] };
      });
    }
    
    // Синхронизируемся с корзиной (она сама загрузит необходимые меню для товаров в корзине)
    await cartPromise;
    
    // После синхронизации обновляем summary, чтобы сумма отобразилась корректно
    updateSummary();
    
    // Остальные меню загружаем в фоне (ленивая загрузка)
    if (!menu && state.activeTab !== 'smart_food') {
      fetch(MENU_URL + '?sale_type=smart_food', {credentials:'same-origin'}).then(function(res){
        if (!res.ok) throw new Error('HTTP '+res.status);
        return res.json();
      }).then(function(data){
        menu = data;
      }).catch(function(e){
        console.error('Не удалось загрузить меню Superfood из', MENU_URL, e);
        menu = { description:'', sections: [] };
      });
    }
    
    if (!menuMercat && state.activeTab !== 'mercat') {
      fetch(MENU_URL + '?sale_type=mercat', {credentials:'same-origin'}).then(function(res){
        if (!res.ok) throw new Error('HTTP '+res.status);
        return res.json();
      }).then(function(data){
        menuMercat = data;
      }).catch(function(e){
        console.error('Не удалось загрузить меню Mercat из', MENU_URL, e);
        menuMercat = { description:'', sections: [] };
      });
    }
    
    if (!menuGlovoUber && state.activeTab !== 'glovo_uber') {
      fetch(MENU_URL + '?sale_type=glovo_uber', {credentials:'same-origin'}).then(function(res){
        if (!res.ok) throw new Error('HTTP '+res.status);
        return res.json();
      }).then(function(data){
        menuGlovoUber = data;
      }).catch(function(e){
        console.error('Не удалось загрузить меню Glovo/Uber из', MENU_URL, e);
        menuGlovoUber = { description:'', sections: [] };
      });
    }
    
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
    // Клик на фото для открытия в модальном окне
    else if (t.classList.contains('wmb-photo-clickable') || t.closest('.wmb-photo-clickable')) {
      e.preventDefault();
      e.stopPropagation();
      var img = t.classList.contains('wmb-photo-clickable') ? t : t.closest('.wmb-photo-clickable');
      var url = img.getAttribute('data-photo-url') || img.src;
      var alt = img.getAttribute('data-photo-alt') || img.alt;
      var title = img.getAttribute('data-photo-title') || '';
      openPhotoFor(url, alt, title);
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
