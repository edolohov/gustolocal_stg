<?php
/**
 * Plugin Name: Weekly Meal Builder
 * Description: Конструктор рационов для WooCommerce: админка (быстрый ввод, инлайн-редактирование, массовые действия, импорт CSV) + фронт с дедлайнами доставки (Вт/Пт), модалкой «Состав» и аллергенами.
 * Version: 2.0.0
 * Author: Vikey
 */

if (!defined('ABSPATH')) exit;

define('WMB_VERSION', '2.0.0');
if (!defined('WMB_PRODUCT_ID')) define('WMB_PRODUCT_ID', 44);
if (!defined('WMB_MERCAT_PRODUCT_ID')) define('WMB_MERCAT_PRODUCT_ID', 713);

/* ---------- assets ---------- */
function wmb_assets_url($p){ return plugins_url('assets/'.$p, __FILE__); }
function wmb_assets_path($p){ return plugin_dir_path(__FILE__).'assets/'.$p; }
function wmb_file_ver($p){ $f=wmb_assets_path($p); return file_exists($f)?filemtime($f):WMB_VERSION; }

/* ---------- settings (description + delivery) ---------- */
function wmb_default_settings(){
  $tz = get_option('timezone_string');
  if (!$tz) { $offset = (float) get_option('gmt_offset'); $tz = $offset ? 'UTC'.($offset>=0?'+':'').$offset : 'UTC'; }

  return [
    'description' => '',
    'delivery' => [
      'tuesday' => [
        'enabled'  => true,
        'deadline' => ['dow' => 0, 'time' => '14:00'],
      ],
      'friday' => [
        'enabled'  => true,
        'deadline' => ['dow' => 3, 'time' => '14:00'],
      ],
      'timezone' => $tz,
      'blackout' => [],
      'banner'   => 'Соберите заказ до {deadline}, и мы привезём его {weekday_short}, {delivery_date}. Осталось {countdown}.',
    ],
    'delivery_slots' => [],
    'limits'         => ['max_portions'=>0],
  ];
}
function wmb_get_settings(){
  $out = wmb_default_settings();
  $json = get_option('wmb_menu_json','');
  if ($json){
    $arr = json_decode($json, true);
    if (is_array($arr)){
      foreach(['description','delivery','delivery_slots','limits'] as $k){
        if (array_key_exists($k,$arr)) $out[$k]=$arr[$k];
      }
    }
  }
  return $out;
}

/* ---------- Category order settings ---------- */
function wmb_get_category_order($sale_type = 'smart_food'){
  $default_smart_food = [
    'Авторские сэндвичи',
    'Готовые салаты',
    'Роллы кимпаб',
    'Протеины',
    'Готовые боулы',
    'Паста ручной работы (требует доготовки)',
    'Десерты',
  ];
  $default_mercat = [
    'Завтраки',
    'Супы и крем-супы (требует доготовки)',
    'Салаты',
    'Заморозка (требует доготовки)',
  ];
  
  $order_json = get_option('wmb_category_order', '');
  if ($order_json) {
    $order_arr = json_decode($order_json, true);
    if (is_array($order_arr)) {
      if ($sale_type === 'smart_food' && isset($order_arr['smart_food']) && is_array($order_arr['smart_food'])) {
        return $order_arr['smart_food'];
      }
      if ($sale_type === 'mercat' && isset($order_arr['mercat']) && is_array($order_arr['mercat'])) {
        return $order_arr['mercat'];
      }
    }
  }
  
  // Возвращаем значения по умолчанию
  return $sale_type === 'mercat' ? $default_mercat : $default_smart_food;
}

/* ---------- admin menu ---------- */
add_action('admin_menu', function(){
  add_menu_page('Meal Builder','Meal Builder','manage_options','wmb_root','wmb_page_root','dashicons-carrot',56);
  add_submenu_page('wmb_root','Блюда','Блюда','edit_posts','wmb_items','wmb_page_items');
  add_submenu_page('wmb_root','Импорт CSV','Импорт CSV','manage_options','wmb_import','wmb_page_import');
  add_submenu_page('wmb_root','Настройки конструктора','Настройки','manage_options','wmb_settings','wmb_page_settings');
  add_submenu_page('wmb_root','Очистить блюда (hard delete)','Очистить блюда (hard delete)','manage_options','wmb_purge','wmb_page_purge');
});
function wmb_page_root(){ echo '<div class="wrap"><h1>Meal Builder</h1><p>Выберите раздел слева.</p></div>'; }

/* ---------- CPT + tax ---------- */
add_action('init', function(){
  register_post_type('wmb_dish', [
    'labels'=>[
      'name'=>'Блюда','singular_name'=>'Блюдо','add_new'=>'Добавить блюдо','add_new_item'=>'Добавить блюдо',
      'edit_item'=>'Редактировать блюдо','new_item'=>'Новое блюдо','view_item'=>'Смотреть блюдо',
      'search_items'=>'Искать блюдо','menu_name'=>'Блюда',
    ],
    'public'=>false,'show_ui'=>true,'show_in_menu'=>false,'supports'=>['title'],'capability_type'=>'post',
  ]);
  register_taxonomy('wmb_section','wmb_dish',[
    'labels'=>['name'=>'Категории','singular_name'=>'Категория'],
    'public'=>false,'show_ui'=>true,'hierarchical'=>false,
  ]);
  register_taxonomy('wmb_tag','wmb_dish',[
    'labels'=>['name'=>'Теги','singular_name'=>'Тег'],
    'public'=>false,'show_ui'=>true,'hierarchical'=>false,
  ]);
});

/* ---------- meta box ---------- */
add_action('add_meta_boxes_wmb_dish', function(){
  add_meta_box('wmb_meta','Параметры блюда','wmb_render_meta','wmb_dish','side','default');
});
function wmb_render_meta($post){
  $price = get_post_meta($post->ID,'wmb_price',true);
  $unit  = get_post_meta($post->ID,'wmb_unit',true);
  $ing   = get_post_meta($post->ID,'wmb_ingredients',true);
  $alrg  = get_post_meta($post->ID,'wmb_allergens',true);
  $active= get_post_meta($post->ID,'wmb_active',true)==='1';
  $shelf_life = get_post_meta($post->ID,'wmb_shelf_life',true);
  $photo_url = get_post_meta($post->ID,'wmb_photo_url',true);
  $photo_alt = get_post_meta($post->ID,'wmb_photo_alt',true);
  $nutrition = get_post_meta($post->ID,'wmb_nutrition',true);
  $sale_type = get_post_meta($post->ID,'wmb_sale_type',true) ?: 'smart_food';
  $available_on_glovo_uber = get_post_meta($post->ID,'wmb_available_on_glovo_uber',true)==='1';
  $glovo_url = get_post_meta($post->ID,'wmb_glovo_url',true);
  $uber_url = get_post_meta($post->ID,'wmb_uber_url',true);
  wp_nonce_field('wmb_save_meta','wmb_meta_nonce');
  echo '<p><label>Цена (€)<br><input name="wmb_price" type="number" step="0.01" value="'.esc_attr($price).'" style="width:100%"></label></p>';
  echo '<p><label>Единица (текст)<br><input name="wmb_unit" type="text" value="'.esc_attr($unit).'" placeholder="200 г 2 порции" style="width:100%"></label></p>';
  echo '<p><label>Тип продажи<br><select name="wmb_sale_type" style="width:100%">';
  echo '<option value="smart_food" '.selected($sale_type,'smart_food',false).'>Smart Food</option>';
  echo '<option value="mercat" '.selected($sale_type,'mercat',false).'>Mercat</option>';
  echo '<option value="both" '.selected($sale_type,'both',false).'>Оба (Smart Food + Mercat)</option>';
  echo '</select></label></p>';
  echo '<p><label><input type="checkbox" name="wmb_available_on_glovo_uber" value="1" '.checked($available_on_glovo_uber,true,false).'> Доступно на Glovo/Uber</label></p>';
  echo '<p><label>Glovo URL<br><input name="wmb_glovo_url" type="url" value="'.esc_url($glovo_url).'" placeholder="https://glovoapp.com/..." style="width:100%"></label></p>';
  echo '<p><label>Uber Eats URL<br><input name="wmb_uber_url" type="url" value="'.esc_url($uber_url).'" placeholder="https://ubereats.com/..." style="width:100%"></label></p>';
  echo '<p><label>Состав (текст)<br><textarea name="wmb_ingredients" rows="3" style="width:100%">'.esc_textarea($ing).'</textarea></label></p>';
  echo '<p><label>Аллергены (через запятую)<br><input name="wmb_allergens" type="text" value="'.esc_attr($alrg).'" placeholder="глютен, молоко, яйца" style="width:100%"></label></p>';
  echo '<p><label>Срок хранения<br><input name="wmb_shelf_life" type="text" value="'.esc_attr($shelf_life).'" placeholder="2-3 дня, до 2х дней" style="width:100%"></label></p>';
  echo '<p><label>Фото (URL)<br><input name="wmb_photo_url" type="url" value="'.esc_url($photo_url).'" placeholder="https://..." style="width:100%"></label></p>';
  echo '<p><label>Alt текст для фото (SEO)<br><input name="wmb_photo_alt" type="text" value="'.esc_attr($photo_alt).'" placeholder="Описание фото для поисковиков" style="width:100%"></label></p>';
  echo '<p><label>КБЖУ (100 г)<br><input name="wmb_nutrition" type="text" value="'.esc_attr($nutrition).'" placeholder="~120 ккал, Б ~3 г, Ж ~5 г, У ~15 г" style="width:100%"></label></p>';
  echo '<p><label><input type="checkbox" name="wmb_active" value="1" '.checked($active,true,false).'> Активно</label></p>';
}
add_action('save_post_wmb_dish', function($post_id){
  if (!isset($_POST['wmb_meta_nonce']) || !wp_verify_nonce($_POST['wmb_meta_nonce'],'wmb_save_meta')) return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (!current_user_can('edit_post',$post_id)) return;
  update_post_meta($post_id,'wmb_price', isset($_POST['wmb_price'])?floatval(str_replace(',', '.', $_POST['wmb_price'])):0);
  update_post_meta($post_id,'wmb_unit', sanitize_text_field($_POST['wmb_unit']??''));
  update_post_meta($post_id,'wmb_ingredients', sanitize_textarea_field($_POST['wmb_ingredients']??''));
  update_post_meta($post_id,'wmb_allergens', sanitize_text_field($_POST['wmb_allergens']??''));
  update_post_meta($post_id,'wmb_shelf_life', sanitize_text_field($_POST['wmb_shelf_life']??''));
  update_post_meta($post_id,'wmb_sale_type', sanitize_text_field($_POST['wmb_sale_type']??'smart_food'));
  update_post_meta($post_id,'wmb_available_on_glovo_uber', !empty($_POST['wmb_available_on_glovo_uber'])?'1':'0');
  update_post_meta($post_id,'wmb_glovo_url', esc_url_raw($_POST['wmb_glovo_url']??''));
  update_post_meta($post_id,'wmb_uber_url', esc_url_raw($_POST['wmb_uber_url']??''));
  update_post_meta($post_id,'wmb_photo_url', esc_url_raw($_POST['wmb_photo_url']??''));
  update_post_meta($post_id,'wmb_photo_alt', sanitize_text_field($_POST['wmb_photo_alt']??''));
  update_post_meta($post_id,'wmb_nutrition', sanitize_text_field($_POST['wmb_nutrition']??''));
  update_post_meta($post_id,'wmb_active', !empty($_POST['wmb_active'])?'1':'0');
});

/* ---------- Admin: Блюда (грид + быстрый ввод + массовые действия) ---------- */
function wmb_page_items(){
  if (!current_user_can('edit_posts')) return;

  if (!empty($_POST['wmb_items_nonce']) && wp_verify_nonce($_POST['wmb_items_nonce'],'wmb_items_save')){
    if (!empty($_POST['bulk_action']) && !empty($_POST['ids']) && is_array($_POST['ids'])){
      $ids = array_map('intval', $_POST['ids']);
      if ($_POST['bulk_action']==='activate'){ foreach($ids as $id) update_post_meta($id,'wmb_active','1'); }
      elseif ($_POST['bulk_action']==='deactivate'){ foreach($ids as $id) update_post_meta($id,'wmb_active','0'); }
      elseif ($_POST['bulk_action']==='delete'){ foreach($ids as $id) wp_delete_post($id, true); }
      echo '<div class="updated notice"><p>Массовое действие выполнено.</p></div>';
    }
    if (!empty($_POST['row']) && is_array($_POST['row'])){
      foreach($_POST['row'] as $id=>$row){
        $id = intval($id);
        if (!current_user_can('edit_post',$id)) continue;
        $title = sanitize_text_field($row['title']??'');
        if ($title) wp_update_post(['ID'=>$id,'post_title'=>$title]);
        $price = isset($row['price']) ? floatval(str_replace(',','.', $row['price'])) : 0;
        $unit  = sanitize_text_field($row['unit']??'');
        $ing   = sanitize_textarea_field($row['ingredients']??'');
        $alrg  = sanitize_text_field($row['allergens']??'');
        $shelf_life = sanitize_text_field($row['shelf_life']??'');
        $active= !empty($row['active']) ? '1' : '0';
        $photo_url = esc_url_raw($row['photo_url']??'');
        $photo_alt = sanitize_text_field($row['photo_alt']??'');
        $nutrition = sanitize_text_field($row['nutrition']??'');
        $sale_type = sanitize_text_field($row['sale_type']??'smart_food');
        $available_on_glovo_uber = !empty($row['available_on_glovo_uber']) ? '1' : '0';
        $glovo_url = esc_url_raw($row['glovo_url']??'');
        $uber_url = esc_url_raw($row['uber_url']??'');
        update_post_meta($id,'wmb_price',$price);
        update_post_meta($id,'wmb_unit',$unit);
        update_post_meta($id,'wmb_ingredients',$ing);
        update_post_meta($id,'wmb_allergens',$alrg);
        update_post_meta($id,'wmb_shelf_life',$shelf_life);
        update_post_meta($id,'wmb_sale_type',$sale_type);
        update_post_meta($id,'wmb_available_on_glovo_uber',$available_on_glovo_uber);
        update_post_meta($id,'wmb_glovo_url',$glovo_url);
        update_post_meta($id,'wmb_uber_url',$uber_url);
        update_post_meta($id,'wmb_photo_url',$photo_url);
        update_post_meta($id,'wmb_photo_alt',$photo_alt);
        update_post_meta($id,'wmb_nutrition',$nutrition);
        update_post_meta($id,'wmb_active',$active);
        $section = sanitize_text_field($row['section']??'');
        if ($section!==''){
          $term = term_exists($section,'wmb_section') ?: wp_insert_term($section,'wmb_section');
          if (!is_wp_error($term)) wp_set_object_terms($id,(int)($term['term_id']??$term),'wmb_section',false);
        } else { wp_set_object_terms($id,[], 'wmb_section', false); }
        $tags_str = (string)($row['tags']??''); $tag_ids=[];
        foreach(array_filter(array_map('trim', explode(',', $tags_str))) as $t){
          $term = term_exists($t,'wmb_tag') ?: wp_insert_term($t,'wmb_tag');
          if (!is_wp_error($term)) $tag_ids[]=(int)($term['term_id']??$term);
        }
        wp_set_object_terms($id,$tag_ids,'wmb_tag',false);
      }
      echo '<div class="updated notice"><p>Изменения сохранены.</p></div>';
    }
    if (!empty($_POST['quick']['title'])){
      $q = $_POST['quick'];
      $title = sanitize_text_field($q['title']);
      $price = isset($q['price']) ? floatval(str_replace(',','.', $q['price'])) : 0;
      $unit  = sanitize_text_field($q['unit']??'');
      $ing   = sanitize_textarea_field($q['ingredients']??'');
      $alrg  = sanitize_text_field($q['allergens']??'');
        $shelf_life = sanitize_text_field($q['shelf_life']??'');
      $active= !empty($q['active']) ? '1' : '0';
        $photo_url = esc_url_raw($q['photo_url']??'');
        $photo_alt = sanitize_text_field($q['photo_alt']??'');
        $nutrition = sanitize_text_field($q['nutrition']??'');
        $sale_type = sanitize_text_field($q['sale_type']??'smart_food');
        $available_on_glovo_uber = !empty($q['available_on_glovo_uber']) ? '1' : '0';
        $glovo_url = esc_url_raw($q['glovo_url']??'');
        $uber_url = esc_url_raw($q['uber_url']??'');
      $id = wp_insert_post(['post_type'=>'wmb_dish','post_status'=>'publish','post_title'=>$title]);
      if (!is_wp_error($id)){
        update_post_meta($id,'wmb_price',$price);
        update_post_meta($id,'wmb_unit',$unit);
        update_post_meta($id,'wmb_ingredients',$ing);
        update_post_meta($id,'wmb_allergens',$alrg);
          update_post_meta($id,'wmb_shelf_life',$shelf_life);
          update_post_meta($id,'wmb_sale_type',$sale_type);
          update_post_meta($id,'wmb_available_on_glovo_uber',$available_on_glovo_uber);
          update_post_meta($id,'wmb_glovo_url',$glovo_url);
          update_post_meta($id,'wmb_uber_url',$uber_url);
          update_post_meta($id,'wmb_photo_url',$photo_url);
          update_post_meta($id,'wmb_photo_alt',$photo_alt);
          update_post_meta($id,'wmb_nutrition',$nutrition);
        update_post_meta($id,'wmb_active',$active);
        $section = sanitize_text_field($q['section']??'');
        if ($section){
          $term = term_exists($section,'wmb_section') ?: wp_insert_term($section,'wmb_section');
          if (!is_wp_error($term)) wp_set_object_terms($id,(int)($term['term_id']??$term),'wmb_section',false);
        }
        $tag_ids=[]; foreach(array_filter(array_map('trim', explode(',', (string)($q['tags']??'')))) as $t){
          $term = term_exists($t,'wmb_tag') ?: wp_insert_term($t,'wmb_tag');
          if (!is_wp_error($term)) $tag_ids[]=(int)($term['term_id']??$term);
        }
        wp_set_object_terms($id,$tag_ids,'wmb_tag',false);
        echo '<div class="updated notice"><p>Блюдо «'.esc_html($title).'» создано.</p></div>';
      } else { echo '<div class="error notice"><p>Не удалось создать «'.esc_html($title).'».</p></div>'; }
    }
  }

  $posts = get_posts(['post_type'=>'wmb_dish','numberposts'=>-1,'orderby'=>'title','order'=>'ASC']);
  echo '<div class="wrap wmb-admin"><h1>Блюда</h1>';

  echo '<style>
    .wmb-admin .quick-grid{display:grid;grid-template-columns:repeat(auto-fit, minmax(150px, 1fr));gap:8px;align-items:center;margin:8px 0 14px}
    .wmb-admin .quick-grid-row2{display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:8px;align-items:center;margin:8px 0 14px}
    .wmb-admin .quick-grid input[type=text], .wmb-admin .quick-grid input[type=url]{width:100%}
    .wmb-admin table.widefat input[type=text], .wmb-admin table.widefat input[type=url]{width:100%}
    .wmb-admin .title-cell{display:flex;gap:6px;align-items:center}
    .wmb-admin .center{ text-align:center }
  </style>';

  echo '<form method="post">';
  wp_nonce_field('wmb_items_save','wmb_items_nonce');

  echo '<h2 class="title">Быстрое добавление</h2>';
  echo '<div class="quick-grid">';
    echo '<input type="text" name="quick[title]" placeholder="Название">';
    echo '<input type="text" name="quick[price]" placeholder="Цена">';
    echo '<input type="text" name="quick[unit]"  placeholder="Ед. (например: 200 г 2 порции)">';
    echo '<input type="text" name="quick[section]" placeholder="Категория (Суп, Завтрак…)">';
    echo '<input type="text" name="quick[ingredients]" placeholder="Состав (текст)">';
    echo '<input type="text" name="quick[allergens]" placeholder="Аллергены (через запятую)">';
    echo '<input type="text" name="quick[tags]" placeholder="Теги через запятую">';
    echo '<label class="center" style="display:flex;align-items:center;gap:6px;justify-content:center"><input type="checkbox" name="quick[active]" value="1"> Активно</label>';
    echo '<button class="button button-primary" style="justify-self:end">Создать</button>';
  echo '</div>';
  echo '<div class="quick-grid-row2">';
    echo '<input type="text" name="quick[shelf_life]" placeholder="Срок хранения: 2-3 дня">';
    echo '<input type="url" name="quick[photo_url]" placeholder="Фото (URL)">';
    echo '<input type="text" name="quick[photo_alt]" placeholder="Alt текст для фото (SEO)">';
    echo '<input type="text" name="quick[nutrition]" placeholder="КБЖУ: ~120 ккал, Б ~3 г, Ж ~5 г, У ~15 г">';
    echo '<select name="quick[sale_type]" style="width:100%"><option value="smart_food">Smart Food</option><option value="mercat">Mercat</option><option value="both">Оба</option></select>';
    echo '<label style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="quick[available_on_glovo_uber]" value="1"> Glovo/Uber</label>';
    echo '<input type="url" name="quick[glovo_url]" placeholder="Glovo URL">';
    echo '<input type="url" name="quick[uber_url]" placeholder="Uber Eats URL">';
  echo '</div>';

  echo '<div style="display:flex;gap:8px;align-items:center;margin:8px 0">';
  echo '<select name="bulk_action"><option value="">Действия</option><option value="activate">Включить</option><option value="deactivate">Выключить</option><option value="delete">Удалить</option></select>';
  echo '<button class="button">Применить</button>';
  echo '</div>';

  echo '<style>
    .wmb-admin .more-cell{ width:70px; text-align:center }
    .wmb-admin .more-btn{ padding:4px 10px; border:1px solid #dcdcde; background:#fff; border-radius:6px; cursor:pointer }
    .wmb-admin tr.wmb-more-row td{ background:#fafafa; padding:12px 16px; border-top:0 }
    .wmb-admin .more-grid{ display:grid; grid-template-columns:1fr 1fr; gap:12px }
    .wmb-admin .more-grid textarea{ width:100%; min-height:44px; resize:none }
    @media (max-width:1200px){ .wmb-admin .more-grid{ grid-template-columns:1fr } }
  </style>';

  echo '<table class="widefat fixed striped"><thead><tr>';
  echo '<th style="width:30px"><input type="checkbox" onclick="jQuery(\'.wmb-rowcb\').prop(\'checked\', this.checked)"></th>';
  echo '<th>Название</th><th style="width:16%">Категория</th><th style="width:8%">Цена</th><th style="width:14%">Ед.</th><th>Теги</th><th style="width:8%">Активно</th><th class="more-cell">Подробнее</th>';
  echo '</tr></thead><tbody>';

  foreach($posts as $p){
    $id = $p->ID;
    $price = get_post_meta($id,'wmb_price',true);
    $unit  = get_post_meta($id,'wmb_unit',true);
    $ing   = get_post_meta($id,'wmb_ingredients',true);
    $alrg  = get_post_meta($id,'wmb_allergens',true);
    $active= get_post_meta($id,'wmb_active',true)==='1';
    $shelf_life = get_post_meta($id,'wmb_shelf_life',true);
    $photo_url = get_post_meta($id,'wmb_photo_url',true);
    $photo_alt = get_post_meta($id,'wmb_photo_alt',true);
    $nutrition = get_post_meta($id,'wmb_nutrition',true);
    $sale_type = get_post_meta($id,'wmb_sale_type',true) ?: 'smart_food';
    $available_on_glovo_uber = get_post_meta($id,'wmb_available_on_glovo_uber',true)==='1';
    $glovo_url = get_post_meta($id,'wmb_glovo_url',true);
    $uber_url = get_post_meta($id,'wmb_uber_url',true);
    $sec_terms = wp_get_post_terms($id,'wmb_section',['fields'=>'names']); $sec = $sec_terms ? $sec_terms[0] : '';
    $tags = wp_get_post_terms($id,'wmb_tag',['fields'=>'names']);
    $edit_url = admin_url('post.php?post='.$id.'&action=edit');

    echo '<tr>';
      echo '<td><input type="checkbox" class="wmb-rowcb" name="ids[]" value="'.$id.'"></td>';
      echo '<td class="title-cell"><input type="text" name="row['.$id.'][title]" value="'.esc_attr(get_the_title($id)).'" title="'.esc_attr(get_the_title($id)).'"><a href="'.esc_url($edit_url).'" class="button button-small" target="_blank">Открыть</a></td>';
      echo '<td><input type="text" name="row['.$id.'][section]" value="'.esc_attr($sec).'"></td>';
      echo '<td><input type="text" name="row['.$id.'][price]" value="'.esc_attr($price).'"></td>';
      echo '<td><input type="text" name="row['.$id.'][unit]" value="'.esc_attr($unit).'"></td>';
      echo '<td><input type="text" name="row['.$id.'][tags]" value="'.esc_attr(implode(', ', $tags)).'"></td>';
      echo '<td class="center"><input type="checkbox" name="row['.$id.'][active]" value="1" '.checked($active,true,false).'></td>';
      echo '<td class="more-cell"><button type="button" class="more-btn" data-target="wmb-more-'.$id.'">✎</button></td>';
    echo '</tr>';

    echo '<tr id="wmb-more-'.$id.'" class="wmb-more-row" style="display:none">';
      echo '<td colspan="8">';
        echo '<div class="more-grid">';
          echo '<label><strong>Состав</strong><br><textarea class="js-autosize" name="row['.$id.'][ingredients]" placeholder="Состав (текст)">'.esc_textarea($ing).'</textarea></label>';
          echo '<label><strong>Аллергены (через запятую)</strong><br><textarea class="js-autosize" name="row['.$id.'][allergens]" placeholder="глютен, молоко, яйца">'.esc_textarea($alrg).'</textarea></label>';
          echo '<label><strong>Срок хранения</strong><br><input type="text" name="row['.$id.'][shelf_life]" value="'.esc_attr($shelf_life).'" placeholder="2-3 дня, до 2х дней"></label>';
          echo '<label><strong>Фото (URL)</strong><br><input type="url" name="row['.$id.'][photo_url]" value="'.esc_url($photo_url).'" placeholder="https://..."></label>';
          echo '<label><strong>Alt текст для фото (SEO)</strong><br><input type="text" name="row['.$id.'][photo_alt]" value="'.esc_attr($photo_alt).'" placeholder="Описание фото"></label>';
          echo '<label><strong>КБЖУ (100 г)</strong><br><input type="text" name="row['.$id.'][nutrition]" value="'.esc_attr($nutrition).'" placeholder="~120 ккал, Б ~3 г, Ж ~5 г, У ~15 г"></label>';
          echo '<label><strong>Тип продажи</strong><br><select name="row['.$id.'][sale_type]" style="width:100%">';
          echo '<option value="smart_food" '.selected($sale_type,'smart_food',false).'>Smart Food</option>';
          echo '<option value="mercat" '.selected($sale_type,'mercat',false).'>Mercat</option>';
          echo '<option value="both" '.selected($sale_type,'both',false).'>Оба (Superfood + Mercat)</option>';
          echo '</select></label>';
          echo '<label style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="row['.$id.'][available_on_glovo_uber]" value="1" '.checked($available_on_glovo_uber,true,false).'> Доступно на Glovo/Uber</label>';
          echo '<label><strong>Glovo URL</strong><br><input type="url" name="row['.$id.'][glovo_url]" value="'.esc_url($glovo_url).'" placeholder="https://glovoapp.com/..."></label>';
          echo '<label><strong>Uber Eats URL</strong><br><input type="url" name="row['.$id.'][uber_url]" value="'.esc_url($uber_url).'" placeholder="https://ubereats.com/..."></label>';
        echo '</div>';
      echo '</td>';
    echo '</tr>';
  }

  echo '</tbody></table>';

  echo '<script>
    (function(){
      const $ = document.querySelector.bind(document);
      const $$ = (s, r) => Array.from((r||document).querySelectorAll(s));
      function autosize(t){ t.style.height="auto"; t.style.height=(t.scrollHeight)+"px"; }
      $$(".more-btn").forEach(btn=>{
        btn.addEventListener("click", function(){
          const id = this.getAttribute("data-target");
          const row = document.getElementById(id);
          if (!row) return;
          const open = row.style.display!=="none";
          row.style.display = open ? "none" : "";
          $$(".js-autosize", row).forEach(autosize);
        });
      });
      $$(".js-autosize").forEach(t=>{
        autosize(t);
        t.addEventListener("input", ()=>autosize(t));
      });
    })();
  </script>';

  echo '<p><button class="button button-primary">Сохранить изменения</button></p>';
  echo '</form></div>';
}

/* ---------- Import CSV ---------- */
function wmb_page_import(){
  if (!current_user_can('manage_options')) return;

  $report=null; $errors=[];
  if (!empty($_POST['wmb_import_nonce']) && wp_verify_nonce($_POST['wmb_import_nonce'],'wmb_import')){
    $csv = '';
    if (!empty($_FILES['wmb_file']['tmp_name']))      $csv = file_get_contents($_FILES['wmb_file']['tmp_name']);
    elseif (!empty($_POST['wmb_text']))               $csv = wp_unslash($_POST['wmb_text']);

    if (!$csv){ $errors[]='CSV не получен.'; }
    else {
      // Парсим CSV с учетом кавычек - используем правильный парсер
      $lines = preg_split('/\r\n|\r|\n/', trim($csv));
      $rows = [];
      foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        // Используем str_getcsv с правильными параметрами для обработки кавычек
        $parsed = str_getcsv($line, ',', '"', '');
        if (!empty($parsed)) {
          $rows[] = $parsed;
        }
      }
      
      if (empty($rows)) {
        $errors[] = 'CSV файл пуст или не может быть распарсен.';
      } else {
      $header = array_map('trim', array_shift($rows));
      $map = array_flip($header);

        // Отладочная информация
        if (empty($map['Тип продажи'])) {
          $errors[] = 'Внимание: колонка "Тип продажи" не найдена в CSV. Найденные колонки: ' . implode(', ', array_keys($map));
        }
      }

      $updated=0; $created=0; $skipped=0; $with_sale_type=0; $with_glovo_uber=0;
      foreach($rows as $r){
        if (!is_array($r) || count(array_filter($r))==0) { $skipped++; continue; }
        $title = trim($r[$map['Название']]??''); if(!$title){ $skipped++; continue; }
        $price = floatval(str_replace(',','.', $r[$map['Цена']]??0));
        $unit  = trim($r[$map['Единица']]??'');
        $section_raw = trim($r[$map['Категория']]??'');
        
        // Маппинг категории через функцию темы, если доступна
        $section = function_exists('gustolocal_map_category_by_alias') 
          ? gustolocal_map_category_by_alias($section_raw) 
          : $section_raw;
        $tags_str= trim($r[$map['Теги']]??'');
        $shelf_life = isset($map['Срок хранения']) ? sanitize_text_field(trim((string)($r[$map['Срок хранения']]??''))) : '';
        $ing    = isset($map['Состав'])     ? trim((string)($r[$map['Состав']]??''))     : '';
        $alrg   = isset($map['Аллергены'])  ? trim((string)($r[$map['Аллергены']]??''))  : '';
        $photo_url = isset($map['Фото']) ? esc_url_raw(trim((string)($r[$map['Фото']]??''))) : '';
        $photo_alt = isset($map['Alt']) ? sanitize_text_field(trim((string)($r[$map['Alt']]??''))) : '';
        $nutrition = isset($map['КБЖУ']) ? sanitize_text_field(trim((string)($r[$map['КБЖУ']]??''))) : '';
        // Обработка новых полей для типов продаж
        $sale_type_raw = '';
        if (isset($map['Тип продажи']) && isset($r[$map['Тип продажи']])) {
          $sale_type_raw = trim((string)$r[$map['Тип продажи']]);
        }
        
        // Если "Тип продажи" пустой, проверяем колонку "Glovo/Uber" (на случай ошибки в CSV)
        if (empty($sale_type_raw) && isset($map['Glovo/Uber']) && isset($r[$map['Glovo/Uber']])) {
          $glovo_uber_check = trim((string)$r[$map['Glovo/Uber']]);
          $glovo_uber_lower = strtolower($glovo_uber_check);
          // Если в "Glovo/Uber" написано название типа продажи, переносим его
          if (in_array($glovo_uber_lower, ['mercat', 'smart_food', 'smart food', 'smartfood', 'both', 'оба'])) {
            $sale_type_raw = $glovo_uber_check;
            // Очищаем "Glovo/Uber", так как там было неправильное значение
            $r[$map['Glovo/Uber']] = '';
          }
        }
        
        // Нормализуем значение (регистронезависимо, поддерживаем разные варианты написания)
        $sale_type_normalized = strtolower($sale_type_raw);
        if (in_array($sale_type_normalized, ['smart_food', 'smart food', 'smartfood'])) {
          $sale_type = 'smart_food';
        } elseif (in_array($sale_type_normalized, ['mercat'])) {
          $sale_type = 'mercat';
        } elseif (in_array($sale_type_normalized, ['both', 'оба', 'оба (smart food + mercat)'])) {
          $sale_type = 'both';
        } else {
          $sale_type = 'smart_food'; // По умолчанию
        }
        
        $available_on_glovo_uber_raw = '';
        if (isset($map['Glovo/Uber']) && isset($r[$map['Glovo/Uber']])) {
          $available_on_glovo_uber_raw = trim((string)$r[$map['Glovo/Uber']]);
        }
        // Проверяем, что это не название типа продажи (уже обработано выше)
        $glovo_uber_lower = strtolower($available_on_glovo_uber_raw);
        if (in_array($glovo_uber_lower, ['mercat', 'smart_food', 'smart food', 'smartfood', 'both', 'оба'])) {
          $available_on_glovo_uber = '0'; // Это был тип продажи, а не флаг Glovo/Uber
        } else {
          $available_on_glovo_uber = ($available_on_glovo_uber_raw === '1' || $glovo_uber_lower === 'да' || $glovo_uber_lower === 'yes') ? '1' : '0';
        }
        
        $glovo_url = '';
        if (isset($map['Glovo URL']) && isset($r[$map['Glovo URL']])) {
          $glovo_url = esc_url_raw(trim((string)$r[$map['Glovo URL']]));
        }
        
        $uber_url = '';
        if (isset($map['Uber URL']) && isset($r[$map['Uber URL']])) {
          $uber_url = esc_url_raw(trim((string)$r[$map['Uber URL']]));
        }
        // Активно по умолчанию, если поле пустое или равно '1'. Только явный '0' делает неактивным.
        $active_raw = trim($r[$map['Активно']]??'');
        $active = ($active_raw === '' || $active_raw === '1' || strtolower($active_raw) === 'да' || strtolower($active_raw) === 'yes') ? '1' : '0';

        $existing = get_posts([
          'post_type'=>'wmb_dish','title'=>$title,
          'post_status'=>['publish','draft','pending','private'],
          'numberposts'=>1,'fields'=>'ids','suppress_filters'=>true
        ]);
        if ($existing){ $id=$existing[0]; $updated++; }
        else { $id=wp_insert_post(['post_type'=>'wmb_dish','post_status'=>'publish','post_title'=>$title]); if (is_wp_error($id)){ $skipped++; continue; } $created++; }

        update_post_meta($id,'wmb_price',$price);
        update_post_meta($id,'wmb_unit',$unit);
        update_post_meta($id,'wmb_ingredients',$ing);
        update_post_meta($id,'wmb_allergens',$alrg);
        update_post_meta($id,'wmb_sale_type',$sale_type);
        update_post_meta($id,'wmb_available_on_glovo_uber',$available_on_glovo_uber);
        update_post_meta($id,'wmb_glovo_url',$glovo_url);
        update_post_meta($id,'wmb_uber_url',$uber_url);
        update_post_meta($id,'wmb_photo_url',$photo_url);
        update_post_meta($id,'wmb_photo_alt',$photo_alt);
        update_post_meta($id,'wmb_nutrition',$nutrition);
        update_post_meta($id,'wmb_shelf_life',$shelf_life);
        update_post_meta($id,'wmb_active',$active);

        if ($section){
          $term = term_exists($section,'wmb_section') ?: wp_insert_term($section,'wmb_section');
          if (!is_wp_error($term)) wp_set_object_terms($id,(int)($term['term_id']??$term),'wmb_section',false);
        }
        $tag_ids=[]; foreach(array_filter(array_map('trim', explode(',', $tags_str))) as $t){
          $term = term_exists($t,'wmb_tag') ?: wp_insert_term($t,'wmb_tag');
          if (!is_wp_error($term)) $tag_ids[]=(int)($term['term_id']??$term);
        }
        wp_set_object_terms($id,$tag_ids,'wmb_tag',false);
      }
      $report = compact('updated','created','skipped','with_sale_type','with_glovo_uber');
      if (!empty($errors)) {
        $report['errors'] = $errors;
      }
    }
  }

  echo '<div class="wrap"><h1>Импорт CSV</h1>';
  if (!empty($report['errors'])) {
    echo '<div class="error notice"><p><strong>Ошибки:</strong></p><ul>';
    foreach ($report['errors'] as $err) {
      echo '<li>'.esc_html($err).'</li>';
    }
    echo '</ul></div>';
  }
  echo '<p>Ожидаемые столбцы: <code>Название, Цена, Единица, Категория, Теги, Срок хранения, Состав, Аллергены, Фото, Alt, КБЖУ, Тип продажи, Glovo/Uber, Glovo URL, Uber URL, Активно</code>. Кодировка UTF-8, разделитель — запятая.</p>';
  echo '<p><strong>Примечание:</strong></p>';
  echo '<ul style="margin-left:20px">';
  echo '<li><strong>Тип продажи</strong>: <code>smart_food</code> (Smart Food), <code>mercat</code> (Mercat), <code>both</code> (Оба). По умолчанию: <code>smart_food</code></li>';
  echo '<li><strong>Glovo/Uber</strong>: <code>1</code> или <code>да</code> или <code>yes</code> — доступно на Glovo/Uber, <code>0</code> — нет. По умолчанию: <code>0</code></li>';
  echo '<li><strong>Glovo URL</strong> и <strong>Uber URL</strong>: ссылки на внешние площадки (опционально)</li>';
  echo '<li><strong>Срок хранения</strong>: например "2-3 дня", "до 2х дней"</li>';
  echo '<li><strong>Фото</strong>: URL изображения</li>';
  echo '<li><strong>Alt</strong>: alt текст для SEO</li>';
  echo '<li><strong>КБЖУ</strong>: формат: ~120 ккал, Б ~3 г, Ж ~5 г, У ~15 г</li>';
  echo '</ul>';
  echo '<p><small>Примечание: <code>Срок хранения</code> — например "2-3 дня", "до 2х дней", <code>Фото</code> — URL изображения, <code>Alt</code> — alt текст для SEO, <code>КБЖУ</code> — формат: ~120 ккал, Б ~3 г, Ж ~5 г, У ~15 г</small></p>';
  if ($report){
    echo '<div class="updated notice"><p>Создано: <strong>'.$report['created'].'</strong>, обновлено: <strong>'.$report['updated'].'</strong>, пропущено: <strong>'.$report['skipped'].'</strong>.</p>';
    if (isset($report['with_sale_type']) || isset($report['with_glovo_uber'])) {
      echo '<p><strong>Статистика по новым полям:</strong><br>';
      if (isset($report['with_sale_type'])) {
        echo 'Записей с типом продажи (не Smart Food): <strong>'.intval($report['with_sale_type']).'</strong><br>';
      }
      if (isset($report['with_glovo_uber'])) {
        echo 'Записей доступных на Glovo/Uber: <strong>'.intval($report['with_glovo_uber']).'</strong>';
      }
      echo '</p>';
    }
    echo '</div>';
  }
  if ($errors){
    echo '<div class="error notice"><p>'.esc_html(implode(' ', $errors)).'</p></div>';
  }
  echo '<form method="post" enctype="multipart/form-data" style="max-width:900px">';
  wp_nonce_field('wmb_import','wmb_import_nonce');
  echo '<h2>Вариант 1 — Загрузка файла</h2><input type="file" name="wmb_file" accept=".csv">';
  echo '<h2>Вариант 2 — Вставить CSV текстом</h2><textarea name="wmb_text" rows="10" style="width:100%" placeholder="Название,Цена,Единица,Категория,Теги,Состав,Аллергены,Фото,Alt,КБЖУ,Активно&#10;Борщ,6.5,500 мл,Суп,&quot;Для мам, Для детей&quot;,&quot;Бульон..., овощи...&quot;,&quot;глютен, молоко&quot;,https://example.com/photo.jpg,Борщ с овощами,&quot;~120 ккал, Б ~3 г, Ж ~5 г, У ~15 г&quot;,1"></textarea>';
  echo '<p><button class="button button-primary">Импортировать</button></p>';
  echo '</form></div>';
}

/* ---------- Settings page ---------- */
function wmb_page_settings(){
  if (!current_user_can('manage_options')) return;

  $saved=false; $settings = wmb_get_settings();
  $delivery = $settings['delivery'];

  if (!empty($_POST['wmb_settings_nonce']) && wp_verify_nonce($_POST['wmb_settings_nonce'],'wmb_settings_save')){
    // Настройки доставки временно отключены - не сохраняем
    // $delivery['tuesday']['enabled']  = !empty($_POST['wmb_del_tue_enabled']);
    // $delivery['friday']['enabled']   = !empty($_POST['wmb_del_fri_enabled']);
    // ... остальные настройки доставки ...
    
    // Сохраняем порядок категорий
    $smart_food_order = trim((string)($_POST['wmb_category_order_smart_food'] ?? ''));
    $mercat_order = trim((string)($_POST['wmb_category_order_mercat'] ?? ''));
    $category_order = [
      'smart_food' => array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $smart_food_order)))),
      'mercat' => array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $mercat_order)))),
    ];
    update_option('wmb_category_order', wp_json_encode($category_order, JSON_UNESCAPED_UNICODE));
    
    $saved=true;
  }
  
  $smart_food_order = wmb_get_category_order('smart_food');
  $mercat_order = wmb_get_category_order('mercat');

  $dow_opts = [0=>'Воскресенье',1=>'Понедельник',2=>'Вторник',3=>'Среда',4=>'Четверг',5=>'Пятница',6=>'Суббота'];

  echo '<div class="wrap"><h1>Настройки конструктора</h1>';
  if ($saved) echo '<div class="updated notice"><p>Сохранено.</p></div>';
  echo '<form method="post" style="max-width:980px">';
  wp_nonce_field('wmb_settings_save','wmb_settings_nonce');

  // Секция "Доставка" временно отключена
  // echo '<h2 style="margin-top:24px">Доставка</h2>';
  // echo '<table class="form-table" role="presentation"><tbody>';
  // echo '<tr><th scope="row">Вторник</th><td>';
  //   echo '<label><input type="checkbox" name="wmb_del_tue_enabled" value="1" '.checked(!empty($delivery['tuesday']['enabled']),true,false).'> включено</label><br>';
  //   echo '<label>Дедлайн (день недели + время): ';
  //     echo '<select name="wmb_del_tue_dow" style="min-width:180px">';
  //     foreach($dow_opts as $i=>$lab){
  //       $sel = selected(intval($delivery['tuesday']['deadline']['dow']), $i, false);
  //       echo '<option value="'.$i.'" '.$sel.'>'.$lab.'</option>';
  //     }
  //     echo '</select> ';
  //     echo '<input type="time" name="wmb_del_tue_time" value="'.esc_attr($delivery['tuesday']['deadline']['time']).'">';
  //   echo '</label>';
  // echo '</td></tr>';
  // echo '<tr><th scope="row">Пятница</th><td>';
  //   echo '<label><input type="checkbox" name="wmb_del_fri_enabled" value="1" '.checked(!empty($delivery['friday']['enabled']),true,false).'> включено</label><br>';
  //   echo '<label>Дедлайн (день недели + время): ';
  //     echo '<select name="wmb_del_fri_dow" style="min-width:180px">';
  //     foreach($dow_opts as $i=>$lab){
  //       $sel = selected(intval($delivery['friday']['deadline']['dow']), $i, false);
  //       echo '<option value="'.$i.'" '.$sel.'>'.$lab.'</option>';
  //     }
  //     echo '</select> ';
  //     echo '<input type="time" name="wmb_del_fri_time" value="'.esc_attr($delivery['friday']['deadline']['time']).'">';
  //   echo '</label>';
  // echo '</td></tr>';
  // echo '<tr><th scope="row"><label for="wmb_del_tz">Часовой пояс</label></th><td><input type="text" id="wmb_del_tz" name="wmb_del_tz" class="regular-text" value="'.esc_attr($delivery['timezone']).'"> <span class="description">По умолчанию берётся из настроек WordPress</span></td></tr>';
  // echo '<tr><th scope="row"><label for="wmb_del_banner">Текст баннера</label></th><td><input type="text" id="wmb_del_banner" name="wmb_del_banner" class="regular-text" style="width:100%" value="'.esc_attr($delivery['banner']).'"><p class="description">Плейсхолдеры: {delivery_date}, {weekday}, {weekday_short}, {deadline}, {countdown}</p></td></tr>';
  // echo '<tr><th scope="row"><label for="wmb_del_blackout">Нерабочие даты</label></th><td><textarea id="wmb_del_blackout" name="wmb_del_blackout" rows="4" class="large-text" placeholder="YYYY-MM-DD по одной на строку">'.esc_textarea(implode("\n",$delivery['blackout'])).'</textarea></td></tr>';
  // echo '</tbody></table>';
  
  echo '<h2 style="margin-top:32px">Порядок категорий</h2>';
  echo '<p class="description">Укажите порядок отображения категорий товаров. Каждая категория на новой строке. Порядок сверху вниз.</p>';
  echo '<table class="form-table" role="presentation"><tbody>';

  echo '<tr><th scope="row"><label for="wmb_category_order_smart_food">Superfood</label></th><td>';
  echo '<textarea id="wmb_category_order_smart_food" name="wmb_category_order_smart_food" rows="10" class="large-text" placeholder="Авторские сэндвичи&#10;Готовые салаты&#10;Роллы кимпаб&#10;Протеины&#10;Готовые боулы&#10;Паста ручной работы (требует доготовки)&#10;Десерты">'.esc_textarea(implode("\n", $smart_food_order)).'</textarea>';
  echo '<p class="description">Категории для Superfood. Используйте точные названия из CSV.</p>';
  echo '</td></tr>';

  echo '<tr><th scope="row"><label for="wmb_category_order_mercat">Mercat</label></th><td>';
  echo '<textarea id="wmb_category_order_mercat" name="wmb_category_order_mercat" rows="10" class="large-text" placeholder="Завтраки&#10;Супы и крем-супы (требует доготовки)&#10;Салаты&#10;Заморозка (требует доготовки)">'.esc_textarea(implode("\n", $mercat_order)).'</textarea>';
  echo '<p class="description">Категории для Mercat. Используйте точные названия из CSV.</p>';
  echo '</td></tr>';

  echo '</tbody></table>';
  echo '<p><button class="button button-primary">Сохранить</button></p>';
  echo '</form></div>';
}

/* ---------- ПУРЖ ---------- */
function wmb_page_purge(){
  if (!current_user_can('manage_options')) return;
  $deleted = null;
  if (!empty($_POST['wmb_purge_confirm']) && $_POST['wmb_purge_confirm']==='1'){
    check_admin_referer('wmb_purge_all');
    $ids = get_posts(['post_type'=>'wmb_dish','post_status'=>'any','numberposts'=>-1,'fields'=>'ids','suppress_filters'=>true]);
    foreach($ids as $id) wp_delete_post($id, true);
    $deleted = count($ids);
  }
  echo '<div class="wrap"><h1>Очистить блюда (hard delete)</h1>';
  if ($deleted!==null) echo '<div class="updated notice"><p>Удалено записей: <b>'.intval($deleted).'</b></p></div>';
  echo '<p>Операция удалит все блюда безвозвратно (включая корзину/черновики).</p>';
  echo '<form method="post">';
  wp_nonce_field('wmb_purge_all');
  echo '<input type="hidden" name="wmb_purge_confirm" value="1">';
  echo '<button class="button button-secondary" onclick="return confirm(\'Удалить ВСЁ безвозвратно?\')">Удалить всё</button>';
  echo '</form></div>';
}

/* ---------- REST: /wmb/v1/menu ---------- */
add_action('rest_api_init', function () {
  // Основной эндпоинт с фильтрацией по типу продажи
  register_rest_route('wmb/v1', '/menu', [
    'methods'             => 'GET',
    'permission_callback' => '__return_true',
    'callback'            => function ($request) {
      $sale_type = $request->get_param('sale_type') ?: 'smart_food'; // smart_food, mercat, glovo_uber
      $settings = wmb_get_settings();
      
      $meta_query = [
        'relation' => 'AND',
        [
          'key'   => 'wmb_active',
          'value' => '1',
        ],
      ];
      
      // Фильтрация по типу продажи
      if ($sale_type === 'smart_food') {
        $meta_query[] = [
          'key'     => 'wmb_sale_type',
          'value'   => ['smart_food', 'both'],
          'compare' => 'IN',
        ];
      } elseif ($sale_type === 'mercat') {
        $meta_query[] = [
          'key'     => 'wmb_sale_type',
          'value'   => ['mercat', 'both'],
          'compare' => 'IN',
        ];
      } elseif ($sale_type === 'glovo_uber') {
        $meta_query[] = [
          'key'   => 'wmb_available_on_glovo_uber',
          'value' => '1',
        ];
        $meta_query[] = [
          'key'     => 'wmb_sale_type',
          'value'   => ['smart_food', 'both'],
          'compare' => 'IN',
        ];
      }
      
      $posts = get_posts([
        'post_type'   => 'wmb_dish',
        'numberposts' => -1,
        'meta_query'  => $meta_query,
        'orderby'     => 'title',
        'order'       => 'ASC',
      ]);

      $sections = [];
      foreach ($posts as $p) {
        $price = (float) get_post_meta($p->ID, 'wmb_price', true);
        $unit  = (string) get_post_meta($p->ID, 'wmb_unit', true);
        $ingredients = (string) get_post_meta($p->ID, 'wmb_ingredients', true);
        $allergens_raw = (string) get_post_meta($p->ID, 'wmb_allergens', true);
        $allergens = array_values(array_filter(array_map('trim', explode(',', $allergens_raw))));
        $shelf_life = (string) get_post_meta($p->ID, 'wmb_shelf_life', true);
        $photo_url = (string) get_post_meta($p->ID, 'wmb_photo_url', true);
        $photo_alt = (string) get_post_meta($p->ID, 'wmb_photo_alt', true);
        $nutrition = (string) get_post_meta($p->ID, 'wmb_nutrition', true);
        $sale_type = (string) get_post_meta($p->ID, 'wmb_sale_type', true) ?: 'smart_food';
        $available_on_glovo_uber = get_post_meta($p->ID, 'wmb_available_on_glovo_uber', true) === '1';
        $glovo_url = (string) get_post_meta($p->ID, 'wmb_glovo_url', true);
        $uber_url = (string) get_post_meta($p->ID, 'wmb_uber_url', true);

        $sec_terms = wp_get_post_terms($p->ID, 'wmb_section', ['fields' => 'names']);
        $sec = $sec_terms ? $sec_terms[0] : 'Прочее';
        if (!isset($sections[$sec])) $sections[$sec] = [];

        $sections[$sec][] = [
          'id'                    => 'dish-' . $p->ID,
          'name'                  => get_the_title($p),
          'price'                 => $price,
          'unit'                  => $unit,
          'ingredients'           => $ingredients,
          'allergens'             => $allergens,
          'tags'                  => wp_get_post_terms($p->ID, 'wmb_tag', ['fields' => 'names']),
          'shelf_life'            => $shelf_life,
          'photo_url'             => $photo_url,
          'photo_alt'             => $photo_alt,
          'nutrition'             => $nutrition,
          'sale_type'             => $sale_type,
          'available_on_glovo_uber' => $available_on_glovo_uber,
          'glovo_url'             => $glovo_url,
          'uber_url'              => $uber_url,
          'kcal'                  => 0,
        ];
      }

      $out_sections = [];
      foreach ($sections as $title => $items) {
        // Используем отображаемое название категории, если функция доступна
        $display_title = function_exists('gustolocal_get_category_display_name') 
          ? gustolocal_get_category_display_name($title) 
          : $title;
        // Сохраняем оригинальное название для сортировки
        $out_sections[] = ['title' => $display_title, 'original_title' => $title, 'items' => $items];
      }

      // Используем настройки порядка категорий из плагина
      $category_order = wmb_get_category_order($sale_type);
      if (!empty($category_order) && is_array($category_order)) {
        // Нормализуем названия для сравнения
        $normalize = function($str) {
          return mb_strtolower(trim(preg_replace('/\s+/', ' ', $str)));
        };
        
        $order_map = [];
        foreach ($category_order as $index => $cat_name) {
          $normalized = $normalize($cat_name);
          $order_map[$normalized] = $index;
        }
        
        usort($out_sections, function($a, $b) use ($order_map, $normalize) {
          // Сравниваем по оригинальному названию из CSV
          $a_normalized = $normalize($a['original_title']);
          $b_normalized = $normalize($b['original_title']);
          
          $ai = isset($order_map[$a_normalized]) ? $order_map[$a_normalized] : PHP_INT_MAX;
          $bi = isset($order_map[$b_normalized]) ? $order_map[$b_normalized] : PHP_INT_MAX;
          
          if ($ai === $bi) {
            // Если обе категории не найдены в порядке, сортируем по алфавиту
            return strcmp($a['title'], $b['title']);
          }
          return $ai - $bi;
        });
      } else {
        // Fallback: сортировка по алфавиту, если порядок не задан
        usort($out_sections, function($a, $b) {
          return strcmp($a['title'], $b['title']);
        });
      }

      return rest_ensure_response([
        'description'     => '',
        'delivery_config' => $settings['delivery'],
        'delivery_slots'  => [],
        'limits'          => ['max_portions' => 0],
        'sections'        => $out_sections,
      ]);
    }
  ]);
});

/* ---------- Shortcode с оптимизацией производительности ---------- */
add_shortcode('meal_builder', function(){
  // Не выполняем шорткод в админке
  if (is_admin()) {
    return '';
  }
  
  $menu_url = esc_url_raw(rest_url('wmb/v1/menu'));
  $ajax_url = admin_url('admin-ajax.php');
  $nonce = wp_create_nonce('wmb');
  $product_id = (int)WMB_PRODUCT_ID;
  $mercat_product_id = (int)WMB_MERCAT_PRODUCT_ID;
  
  // Предзагрузка данных через <link rel="preload"> - добавляем один раз
  static $preload_added = false;
  if (!$preload_added) {
    add_action('wp_head', function() use ($menu_url) {
      echo '<link rel="preload" as="fetch" href="'.esc_url($menu_url).'" crossorigin="anonymous">'."\n";
    }, 1);
    $preload_added = true;
  }
  
  // Загружаем CSS и JS
  wp_enqueue_style('wmb-style', wmb_assets_url('wmb.css'), [], wmb_file_ver('wmb.css'));
  wp_register_script('wmb-app', wmb_assets_url('wmb.js'), [], wmb_file_ver('wmb.js'), true);
  wp_localize_script('wmb-app','WMB',[
    'ajax_url'=>$ajax_url,
    'nonce'=>$nonce,
    'product_id'=>$product_id,
    'mercat_product_id'=>$mercat_product_id,
    'menu_url'=>$menu_url,
    'cart_url'=>function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/'),
    'menu_url_base'=>get_permalink(get_page_by_path('menu')) ?: home_url('/menu/'),
  ]);
  wp_enqueue_script('wmb-app');
  
  return '<div id="meal-builder-root"><div class="wmb-loading">Загрузка меню…</div></div>';
});

/* ---------- AJAX handlers ---------- */
add_action('wp_ajax_wmb_add_to_cart','wmb_ajax_add_to_cart');
add_action('wp_ajax_wmb_get_cart_contents','wmb_ajax_get_cart_contents');
add_action('wp_ajax_wmb_remove_cart_item','wmb_ajax_remove_cart_item');
add_action('wp_ajax_nopriv_wmb_add_to_cart','wmb_ajax_add_to_cart');
add_action('wp_ajax_nopriv_wmb_get_cart_contents','wmb_ajax_get_cart_contents');
add_action('wp_ajax_nopriv_wmb_remove_cart_item','wmb_ajax_remove_cart_item');

function wmb_ajax_get_cart_contents(){
  if(!function_exists('WC')) wp_send_json_error('WooCommerce не активирован.');
  check_ajax_referer('wmb','nonce');
  $cart = WC()->cart;
  $items = [];
  foreach($cart->get_cart() as $cart_item_key => $cart_item) {
    $product = $cart_item['data'];
    $wmb_payload = isset($cart_item['wmb_payload']) ? $cart_item['wmb_payload'] : '';
    $items[] = [
      'key' => $cart_item_key,
      'product_id' => $product->get_id(),
      'quantity' => $cart_item['quantity'],
      'wmb_payload' => $wmb_payload
    ];
  }
  wp_send_json_success(['items' => $items]);
}

function wmb_ajax_remove_cart_item(){
  if(!function_exists('WC')) wp_send_json_error('WooCommerce не активирован.');
  check_ajax_referer('wmb','nonce');
  $cart_item_key = isset($_POST['cart_item_key']) ? sanitize_text_field($_POST['cart_item_key']) : '';
  if(empty($cart_item_key)) wp_send_json_error('Неверные данные.');
  $removed = WC()->cart->remove_cart_item($cart_item_key);
  if(!$removed) wp_send_json_error('Не удалось удалить товар из корзины.');
  wp_send_json_success(['message' => 'Товар удален из корзины']);
}

function wmb_ajax_add_to_cart(){
  try {
  if(!function_exists('WC')) wp_send_json_error('WooCommerce не активирован.');
  check_ajax_referer('wmb','nonce');
  $product_id   = isset($_POST['product_id'])?absint($_POST['product_id']):0;
  $payload_json = isset($_POST['payload'])?wp_unslash($_POST['payload']):'';
  if(!$product_id || !$payload_json) wp_send_json_error('Неверные данные.');
  $payload = json_decode($payload_json,true);
  if(!is_array($payload)) wp_send_json_error('Невалидный JSON.');
  $items = isset($payload['items']) && is_array($payload['items']) ? $payload['items'] : [];
  $total_price=0.0; $total_portions=0; $items_list=[];
  foreach($items as $id=>$qty){
    $qty = max(0, intval($qty)); if(!$qty) continue;
    $total_portions += $qty;
    $post_id = (strpos($id,'dish-')===0) ? intval(substr($id,5)) : 0;
    if ($post_id>0){ 
      $name=get_the_title($post_id); 
      $price=floatval(get_post_meta($post_id,'wmb_price',true)); 
      $unit=get_post_meta($post_id,'wmb_unit',true);
      $nutrition=get_post_meta($post_id,'wmb_nutrition',true); // КБЖУ
      $kcal=0; 
    }
    else { $name=$id; $price=0; $unit=''; $nutrition=''; $kcal=0; }
    $total_price += $price*$qty;
    $items_list[] = ['id'=>$id,'name'=>$name,'unit'=>$unit,'nutrition'=>$nutrition,'kcal'=>$kcal,'price'=>$price,'qty'=>$qty];
  }
  $payload['items_list']=$items_list;
  $payload['total_portions']=$total_portions;
  $payload['total_price']=round($total_price,2);
  $added = WC()->cart->add_to_cart($product_id, 1, 0, [], ['wmb_payload'=>json_encode($payload),'wmb_uid'=>wp_generate_uuid4()]);
  if(!$added) wp_send_json_error('Не удалось добавить в корзину.');
  $redirect = function_exists('wc_get_cart_url') ? wc_get_cart_url() : wc_get_page_permalink('cart');
  wp_send_json_success(['redirect'=>$redirect]);
  } catch (Exception $e) {
    wp_send_json_error('Ошибка: ' . $e->getMessage());
  }
}

/* ---------- WooCommerce integration ---------- */
// Функция для парсинга КБЖУ из строки (например "~120 ккал, Б ~3 г, Ж ~5 г, У ~15 г")
function wmb_parse_nutrition($nutrition_str) {
  if (empty($nutrition_str)) return ['kcal' => 0, 'protein' => 0, 'fat' => 0, 'carbs' => 0];
  
  $result = ['kcal' => 0, 'protein' => 0, 'fat' => 0, 'carbs' => 0];
  
  // Парсим ккал
  if (preg_match('/(?:~|≈)?\s*(\d+)\s*ккал/i', $nutrition_str, $matches)) {
    $result['kcal'] = floatval($matches[1]);
  }
  
  // Парсим белки (Б)
  if (preg_match('/Б\s*(?:~|≈)?\s*(\d+(?:[.,]\d+)?)\s*г/i', $nutrition_str, $matches)) {
    $result['protein'] = floatval(str_replace(',', '.', $matches[1]));
  }
  
  // Парсим жиры (Ж)
  if (preg_match('/Ж\s*(?:~|≈)?\s*(\d+(?:[.,]\d+)?)\s*г/i', $nutrition_str, $matches)) {
    $result['fat'] = floatval(str_replace(',', '.', $matches[1]));
  }
  
  // Парсим углеводы (У)
  if (preg_match('/У\s*(?:~|≈)?\s*(\d+(?:[.,]\d+)?)\s*г/i', $nutrition_str, $matches)) {
    $result['carbs'] = floatval(str_replace(',', '.', $matches[1]));
  }
  
  return $result;
}

// Функция для форматирования КБЖУ
function wmb_format_nutrition($nutrition) {
  $parts = [];
  if ($nutrition['kcal'] > 0) {
    $parts[] = '~' . round($nutrition['kcal']) . ' ккал';
  }
  if ($nutrition['protein'] > 0) {
    $parts[] = 'Б ~' . round($nutrition['protein'], 1) . ' г';
  }
  if ($nutrition['fat'] > 0) {
    $parts[] = 'Ж ~' . round($nutrition['fat'], 1) . ' г';
  }
  if ($nutrition['carbs'] > 0) {
    $parts[] = 'У ~' . round($nutrition['carbs'], 1) . ' г';
  }
  return implode(', ', $parts);
}

// Улучшенное отображение деталей заказа в корзине и на checkout
add_filter('woocommerce_cart_item_name', 'wmb_display_cart_item_details', 10, 3);
function wmb_display_cart_item_details($name, $cart_item, $cart_item_key) {
  if (isset($cart_item['wmb_payload'])) {
    $payload = json_decode($cart_item['wmb_payload'], true);
    if ($payload && isset($payload['items_list']) && is_array($payload['items_list'])) {
      // Убираем бейджи - они дублируют информацию, так как товары уже разделены
      $sale_type_label = '';
      
      $details = [];
      foreach ($payload['items_list'] as $item) {
        if (isset($item['qty']) && $item['qty'] > 0) {
          $item_name = isset($item['name']) ? esc_html($item['name']) : 'Неизвестное блюдо';
          $item_unit = isset($item['unit']) ? esc_html($item['unit']) : '';
          $item_price = isset($item['price']) ? floatval($item['price']) : 0;
          $item_qty = intval($item['qty']);
          $item_nutrition = isset($item['nutrition']) ? trim($item['nutrition']) : '';
          $total_price = $item_price * $item_qty;
          
          // Форматируем дочерние товары: "Название (единица) — цена [КБЖУ]"
          $unit_display = $item_unit ? ' (' . $item_unit . ')' : '';
          // Используем формат WooCommerce для цены (учитывает настройки валюты)
          $formatted_price = function_exists('wc_price') ? strip_tags(wc_price($total_price)) : number_format($total_price, 2, ',', '') . ' €';
          $detail_line = $item_name . $unit_display . ' — ' . $formatted_price;
          // Добавляем КБЖУ если есть
          if ($item_nutrition) {
            $detail_line .= ' <span class="wmb-cart-nutrition">' . esc_html($item_nutrition) . '</span>';
          }
          $details[] = $detail_line;
        }
      }
      if (!empty($details)) {
        // Извлекаем только название товара (без количества и цены, если они были добавлены)
        $clean_name = wp_strip_all_tags($name);
        // Удаляем возможные паттерны " × количество — цена" из конца названия
        $clean_name = preg_replace('/\s*×\s*\d+\s*—\s*[€$]?[\d,\.]+\s*$/', '', $clean_name);
        $clean_name = trim($clean_name);
        
        // Получаем общую цену и подытог для добавления в детали
        $total_item_price = 0;
        $total_item_subtotal = 0;
        foreach ($payload['items_list'] as $item) {
          if (isset($item['qty']) && $item['qty'] > 0) {
            $item_price = isset($item['price']) ? floatval($item['price']) : 0;
            $item_qty = intval($item['qty']);
            $total_item_price += $item_price;
            $total_item_subtotal += $item_price * $item_qty;
          }
        }
        
        // Основной товар отображаем без количества и цены, только название
        // Дочерние товары отображаем в столбик под основным товаром
        $name = '<div class="wmb-product-name-main">' . esc_html($clean_name) . ' ' . $sale_type_label . '</div>';
        $name .= '<div class="wmb-product-details">';
        $name .= implode('<br>', $details);
        // Добавляем общую цену и подытог в конец деталей
        if ($total_item_price > 0 || $total_item_subtotal > 0) {
          $name .= '<div class="wmb-product-price-summary">';
          if ($total_item_price > 0) {
            $formatted_price = function_exists('wc_price') ? strip_tags(wc_price($total_item_price)) : number_format($total_item_price, 2, ',', '') . ' €';
            $name .= '<span class="wmb-price-label">Цена: </span><span class="wmb-price-value">' . $formatted_price . '</span>';
          }
          if ($total_item_subtotal > 0) {
            $formatted_subtotal = function_exists('wc_price') ? strip_tags(wc_price($total_item_subtotal)) : number_format($total_item_subtotal, 2, ',', '') . ' €';
            $name .= ' <span class="wmb-subtotal-label">Подытог: </span><span class="wmb-subtotal-value">' . $formatted_subtotal . '</span>';
          }
          $name .= '</div>';
        }
        $name .= '</div>';
      }
    }
  }
  return $name;
}

add_action('woocommerce_before_calculate_totals', function($cart){
  if (is_admin() && !defined('DOING_AJAX')) return;
  if (!$cart || is_a($cart,'WP_Error')) return;
  foreach($cart->get_cart() as $ci){
    if (!empty($ci['wmb_payload'])){
      $payload = json_decode($ci['wmb_payload'], true);
      if ($payload && isset($payload['total_price']) && $payload['total_price'] > 0){
        $ci['data']->set_price( floatval($payload['total_price']) );
      }
    }
  }
},10,1);

add_action('woocommerce_checkout_create_order_line_item', function($item,$key,$values){
  if (!empty($values['wmb_payload'])){
    $payload = is_string($values['wmb_payload']) ? json_decode($values['wmb_payload'], true) : $values['wmb_payload'];
    $json = is_string($values['wmb_payload'])
      ? $values['wmb_payload']
      : wp_json_encode($values['wmb_payload'], JSON_UNESCAPED_UNICODE);
    $item->add_meta_data('_wmb_payload', $json, false);
  }
},10,3);

// Подсказки в корзине о возможности дозаказа
add_action('woocommerce_before_cart', 'wmb_show_cart_addon_hints');
function wmb_show_cart_addon_hints() {
  if (!function_exists('WC') || !WC()->cart) return;
  
  $has_smart_food = false;
  $has_mercat = false;
  
  foreach (WC()->cart->get_cart() as $cart_item) {
    if (isset($cart_item['wmb_payload'])) {
      $payload = json_decode($cart_item['wmb_payload'], true);
      if ($payload && isset($payload['sale_type'])) {
        if ($payload['sale_type'] === 'smart_food') {
          $has_smart_food = true;
        } elseif ($payload['sale_type'] === 'mercat') {
          $has_mercat = true;
        }
      }
    }
  }
  
  $menu_url = get_permalink(get_page_by_path('menu'));
  if (!$menu_url) $menu_url = home_url('/menu/');
  
  if ($has_smart_food && !$has_mercat) {
    echo '<div class="wmb-cart-hint" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border: 2px solid #2196f3; border-radius: 12px; padding: 20px; margin-bottom: 24px; box-shadow: 0 4px 12px rgba(33,150,243,0.15);">';
    echo '<div style="display: flex; align-items: flex-start; gap: 16px; flex-wrap: wrap;">';
    echo '<div style="flex: 1; min-width: 200px;">';
    echo '<strong style="display: block; font-size: 18px; color: #1976d2; margin-bottom: 8px;">Добавьте товары из Mercat!</strong>';
    echo '<p style="font-size: 14px; color: #555; margin: 0 0 12px 0; line-height: 1.5;">Вы можете добавить товары из раздела Mercat к вашему заказу Superfood. Доставка будет объединена на следующий день.</p>';
    echo '<a href="' . esc_url($menu_url) . '#mercat" style="display: inline-block; padding: 10px 20px; background: #2196f3; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; transition: all 0.2s;">Перейти к Mercat →</a>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
  } elseif ($has_mercat && !$has_smart_food) {
    echo '<div class="wmb-cart-hint" style="background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%); border: 2px solid #ff9800; border-radius: 12px; padding: 20px; margin-bottom: 24px; box-shadow: 0 4px 12px rgba(255,152,0,0.15);">';
    echo '<div style="display: flex; align-items: flex-start; gap: 16px; flex-wrap: wrap;">';
    echo '<div style="flex: 1; min-width: 200px;">';
    echo '<strong style="display: block; font-size: 18px; color: #f57c00; margin-bottom: 8px;">Добавьте Superfood!</strong>';
    echo '<p style="font-size: 14px; color: #555; margin: 0 0 12px 0; line-height: 1.5;">Вы можете добавить блюда из раздела Superfood к вашему заказу Mercat. Доставка будет объединена на следующий день.</p>';
    echo '<a href="' . esc_url($menu_url) . '#smart_food" style="display: inline-block; padding: 10px 20px; background: #ff9800; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; transition: all 0.2s;">Перейти к Superfood →</a>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
  }
}

// Подсказки при оформлении заказа о возможности дозаказа
add_action('woocommerce_before_checkout_form', 'wmb_show_addon_hints');
function wmb_show_addon_hints() {
  if (!function_exists('WC') || !WC()->cart) return;
  
  $has_smart_food = false;
  $has_mercat = false;
  
  foreach (WC()->cart->get_cart() as $cart_item) {
    if (isset($cart_item['wmb_payload'])) {
      $payload = json_decode($cart_item['wmb_payload'], true);
      if ($payload && isset($payload['sale_type'])) {
        if ($payload['sale_type'] === 'smart_food') {
          $has_smart_food = true;
        } elseif ($payload['sale_type'] === 'mercat') {
          $has_mercat = true;
        }
      }
    }
  }
  
  if ($has_smart_food && !$has_mercat) {
    $menu_url = get_permalink(get_page_by_path('menu'));
    if (!$menu_url) $menu_url = home_url('/menu/');
    echo '<div class="wmb-checkout-hint" style="background: #e3f2fd; border-left: 4px solid #2196f3; padding: 16px; margin-bottom: 20px; border-radius: 4px;">';
    echo '<strong>💡 Хотите добавить товары из Mercat?</strong><br>';
    echo '<span style="font-size: 14px; color: #666;">Вы можете добавить товары из раздела Mercat к вашему заказу Smart Food. Доставка будет объединена на следующий день.</span><br>';
    echo '<a href="' . esc_url($menu_url) . '#mercat" style="display: inline-block; margin-top: 8px; padding: 8px 16px; background: #2196f3; color: white; text-decoration: none; border-radius: 4px; font-weight: 600;">Перейти к Mercat →</a>';
    echo '</div>';
  } elseif ($has_mercat && !$has_smart_food) {
    $menu_url = get_permalink(get_page_by_path('menu'));
    if (!$menu_url) $menu_url = home_url('/menu/');
    echo '<div class="wmb-checkout-hint" style="background: #fff3e0; border-left: 4px solid #ff9800; padding: 16px; margin-bottom: 20px; border-radius: 4px;">';
    echo '<strong>Хотите добавить Superfood?</strong><br>';
    echo '<span style="font-size: 14px; color: #666;">Вы можете добавить блюда из раздела Superfood к вашему заказу Mercat. Доставка будет объединена на следующий день.</span><br>';
    echo '<a href="' . esc_url($menu_url) . '#smart_food" style="display: inline-block; margin-top: 8px; padding: 8px 16px; background: #ff9800; color: white; text-decoration: none; border-radius: 4px; font-weight: 600;">Перейти к Superfood →</a>';
    echo '</div>';
  }
}

// Добавляем стили для бейджей типов продаж
add_action('wp_head', function() {
  if (is_cart() || is_checkout()) {
    echo '<style>
      .wmb-sale-type-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-left: 8px;
      }
      .wmb-sale-type-smart-food {
        background: #e3f2fd;
        color: #1976d2;
      }
      .wmb-sale-type-mercat {
        background: #fff3e0;
        color: #f57c00;
      }
    </style>';
  }
});

add_filter('woocommerce_hidden_order_itemmeta', function($hidden){
  $hidden[] = 'Meal plan payload';
  $hidden[] = '_wmb_payload';
  return $hidden;
});

add_action('woocommerce_order_item_meta_end', function($item_id, $item, $order, $plain_text){
  $meta = $item->get_meta('_wmb_payload', true);
  if (!$meta) $meta = $item->get_meta('Meal plan payload', true);
  if (!$meta) return;
  $payload = json_decode($meta, true);
  if (!$payload || empty($payload['items_list'])) return;
  $lines = [];
  foreach($payload['items_list'] as $row){
    $name = isset($row['name']) ? trim($row['name']) : '';
    $qty  = isset($row['qty']) ? intval($row['qty']) : 0;
    $price= isset($row['price']) ? floatval($row['price']) : 0.0;
    $unit = isset($row['unit']) ? trim($row['unit']) : '';
    if (empty($name) || $qty <= 0) continue;
    $subtotal = $price * $qty;
    $unit_display = $unit ? " ({$unit})" : '';
    $formatted_unit_price = function_exists('wc_price') ? strip_tags(wc_price($price)) : number_format($price, 2, ',', '') . ' €';
    $formatted_total_price = function_exists('wc_price') ? strip_tags(wc_price($subtotal)) : number_format($subtotal, 2, ',', '') . ' €';
    // Формат: "Название (единица) — цена за единицу — количество — подытог"
    $line = sprintf('%s%s — %s — %d — %s', $name, $unit_display, $formatted_unit_price, $qty, $formatted_total_price);
    $lines[] = $line;
  }
  if (!$lines) return;
  if ($plain_text){
    echo "\n" . implode("\n", $lines) . "\n";
  } else {
    $html = implode('<br>', $lines);
    echo '<div class="wmb-order-breakdown">'.$html.'</div>';
  }
}, 10, 4);

add_action('woocommerce_after_order_itemmeta', function($item_id, $item, $product){
  if (!is_admin()) return;
  $meta = $item->get_meta('_wmb_payload', true);
  if (!$meta) $meta = $item->get_meta('Meal plan payload', true);
  if (!$meta) return;
  $payload = json_decode($meta, true);
  if (!$payload || empty($payload['items_list'])) return;
  $lines = [];
  foreach($payload['items_list'] as $row){
    $name = isset($row['name']) ? trim($row['name']) : '';
    $qty  = isset($row['qty']) ? intval($row['qty']) : 0;
    $price= isset($row['price']) ? floatval($row['price']) : 0.0;
    $unit = isset($row['unit']) ? trim($row['unit']) : '';
    if (empty($name) || $qty <= 0) continue;
    $subtotal = $price * $qty;
    $unit_display = $unit ? " ({$unit})" : '';
    $formatted_unit_price = function_exists('wc_price') ? strip_tags(wc_price($price)) : number_format($price, 2, ',', '') . ' €';
    $formatted_total_price = function_exists('wc_price') ? strip_tags(wc_price($subtotal)) : number_format($subtotal, 2, ',', '') . ' €';
    // Формат: "Название (единица) — цена за единицу — количество — подытог"
    $line = sprintf('%s%s — %s — %d — %s', $name, $unit_display, $formatted_unit_price, $qty, $formatted_total_price);
    $lines[] = $line;
  }
  if (!$lines) return;
  $html = implode('<br>', $lines);
  echo '<div class="wmb-order-breakdown" style="margin-top:6px;">'.$html.'</div>';
}, 10, 3);

add_filter('woocommerce_is_purchasable', function($purchasable,$product){
  if ($product && intval($product->get_id())===intval(WMB_PRODUCT_ID)) return true; return $purchasable;
},10,2);
add_filter('woocommerce_product_is_in_stock', function($in_stock,$product){
  if ($product && intval($product->get_id())===intval(WMB_PRODUCT_ID)) return true; return $in_stock;
},10,2);

add_filter('woocommerce_email_order_items_args', function($args){
  $args['show_image'] = false;
  return $args;
}, 10, 1);

// Скрываем колонку количества в корзине и чекауте
add_filter('woocommerce_cart_item_quantity', '__return_empty_string', 10, 3);
add_filter('woocommerce_checkout_cart_item_quantity', '__return_empty_string', 10, 3);

// Скрываем колонки цены и подытога для товаров с wmb_payload (они уже в wmb-product-details)
add_filter('woocommerce_cart_item_price', 'wmb_hide_cart_item_price', 10, 3);
function wmb_hide_cart_item_price($price, $cart_item, $cart_item_key) {
  if (isset($cart_item['wmb_payload'])) {
    return ''; // Возвращаем пустую строку, цена уже в wmb-product-details
  }
  return $price;
}

add_filter('woocommerce_cart_item_subtotal', 'wmb_hide_cart_item_subtotal', 10, 3);
function wmb_hide_cart_item_subtotal($subtotal, $cart_item, $cart_item_key) {
  if (isset($cart_item['wmb_payload'])) {
    return ''; // Возвращаем пустую строку, подытог уже в wmb-product-details
  }
  return $subtotal;
}

// Добавляем общее КБЖУ в корзине - внутри таблицы, перед строкой с actions (купон и доставка)
// Используем woocommerce_cart_contents, который срабатывает ПЕРЕД строкой с actions
add_action('woocommerce_cart_contents', 'wmb_display_total_nutrition');
function wmb_display_total_nutrition() {
  if (!function_exists('WC') || !WC()->cart) return;
  
  $total_nutrition = ['kcal' => 0, 'protein' => 0, 'fat' => 0, 'carbs' => 0];
  $has_nutrition = false;
  
  foreach (WC()->cart->get_cart() as $cart_item) {
    if (isset($cart_item['wmb_payload'])) {
      $payload = json_decode($cart_item['wmb_payload'], true);
      if ($payload && isset($payload['items_list']) && is_array($payload['items_list'])) {
        foreach ($payload['items_list'] as $item) {
          if (isset($item['qty']) && $item['qty'] > 0 && !empty($item['nutrition'])) {
            $nutrition = wmb_parse_nutrition($item['nutrition']);
            $qty = intval($item['qty']);
            $total_nutrition['kcal'] += $nutrition['kcal'] * $qty;
            $total_nutrition['protein'] += $nutrition['protein'] * $qty;
            $total_nutrition['fat'] += $nutrition['fat'] * $qty;
            $total_nutrition['carbs'] += $nutrition['carbs'] * $qty;
            $has_nutrition = true;
          }
        }
      }
    }
  }
  
  if ($has_nutrition && ($total_nutrition['kcal'] > 0 || $total_nutrition['protein'] > 0 || $total_nutrition['fat'] > 0 || $total_nutrition['carbs'] > 0)) {
    $formatted = wmb_format_nutrition($total_nutrition);
    if (!empty($formatted)) {
      // Добавляем как строку таблицы внутри tbody, перед строкой с actions
      echo '<tr class="wmb-total-nutrition-row">';
      echo '<td colspan="5" class="wmb-total-nutrition-cell">';
      echo '<div class="wmb-total-nutrition-summary">';
      echo '<strong>' . esc_html__('Общее КБЖУ:', 'woocommerce') . '</strong> ';
      echo '<span class="wmb-total-nutrition-value">' . esc_html($formatted) . '</span>';
      echo '</div>';
      echo '</td>';
      echo '</tr>';
    }
  }
}

// Скрываем заголовок колонки количества через CSS
add_action('wp_enqueue_scripts', function(){
  if (function_exists('is_cart') && (is_cart() || is_checkout())){
    // Увеличиваем приоритет, чтобы стили загружались после темы
    $css = '
      html,body{overflow-x:hidden} 
      /* Критично: убираем все ограничения для контейнеров корзины (только корзина!) */
      .woocommerce.woocommerce-cart .cart,
      .woocommerce.woocommerce-cart .woocommerce-cart-form,
      .woocommerce.woocommerce-cart .woocommerce-cart-form form {
        overflow-x:visible !important;
        overflow-y:visible !important;
        max-width:100% !important;
        width:100% !important;
        box-sizing:border-box !important;
      }
      /* Критично: исправляем обрезку именно в woocommerce-cart-form - максимальная специфичность */
      form.woocommerce-cart-form,
      .woocommerce-cart-form,
      .woocommerce-cart form.woocommerce-cart-form,
      .woocommerce.woocommerce-cart form.woocommerce-cart-form,
      .woocommerce.woocommerce-cart .woocommerce-cart-form {
        overflow-x:visible !important;
        overflow-y:visible !important;
        overflow:visible !important;
        max-width:none !important;
        width:100% !important;
        min-width:100% !important;
        box-sizing:border-box !important;
      }
      form.woocommerce-cart-form table,
      .woocommerce-cart-form table,
      .woocommerce-cart form.woocommerce-cart-form table,
      .woocommerce.woocommerce-cart form.woocommerce-cart-form table,
      .woocommerce.woocommerce-cart .woocommerce-cart-form table {
        overflow-x:visible !important;
        overflow-y:visible !important;
        overflow:visible !important;
        max-width:none !important;
        width:100% !important;
        min-width:100% !important;
        table-layout:auto !important;
      }
      form.woocommerce-cart-form table tbody,
      .woocommerce-cart-form table tbody,
      .woocommerce-cart form.woocommerce-cart-form table tbody,
      .woocommerce.woocommerce-cart form.woocommerce-cart-form table tbody,
      .woocommerce.woocommerce-cart .woocommerce-cart-form table tbody {
        overflow-x:visible !important;
        overflow-y:visible !important;
        overflow:visible !important;
        max-width:none !important;
        width:auto !important;
        min-width:100% !important;
        display:table-row-group !important;
      }
      form.woocommerce-cart-form table tbody tr,
      .woocommerce-cart form.woocommerce-cart-form table tbody tr {
        overflow-x:visible !important;
        overflow-y:visible !important;
        max-width:100% !important;
        width:100% !important;
      }
      form.woocommerce-cart-form table tbody td,
      .woocommerce-cart form.woocommerce-cart-form table tbody td {
        overflow-x:visible !important;
        overflow-y:visible !important;
        overflow:visible !important;
        max-width:100% !important;
        width:auto !important;
        min-width:0 !important;
        box-sizing:border-box !important;
        word-wrap:break-word !important;
        overflow-wrap:break-word !important;
        word-break:break-word !important;
        white-space:normal !important;
        text-overflow:clip !important;
      }
      /* Меняем table-layout на auto для более гибкого распределения колонок */
      .woocommerce .cart, 
      .woocommerce table.shop_table,
      .woocommerce-cart-form table,
      form.woocommerce-cart-form table,
      .woocommerce table.cart,
      .woocommerce-cart-form table.shop_table {
        table-layout:auto !important;
        width:100% !important;
        max-width:none !important;
        min-width:100% !important;
        overflow-x:visible !important;
        overflow-y:visible !important;
        border-collapse:separate !important;
        border-spacing:0 !important;
      }
      .woocommerce table.shop_table td, 
      .woocommerce table.shop_table th,
      .woocommerce-cart-form table td,
      .woocommerce-cart-form table th {
        word-wrap:break-word !important;
        overflow-wrap:break-word !important;
        word-break:break-word !important;
        white-space:normal !important;
        overflow:visible !important;
        overflow-x:visible !important;
        overflow-y:visible !important;
        max-width:100% !important;
        box-sizing:border-box !important;
        padding-left:8px !important;
        padding-right:8px !important;
      }
      /* Критично: убираем все ограничения overflow для корзины (только специфичные элементы, БЕЗ универсального селектора!) */
      .woocommerce-cart-form table,
      form.woocommerce-cart-form table,
      .woocommerce.woocommerce-cart .woocommerce-cart-form table,
      .woocommerce.woocommerce-cart form.woocommerce-cart-form table {
        overflow-x:visible !important;
        overflow-y:visible !important;
        overflow:visible !important;
        max-width:none !important;
        width:100% !important;
        min-width:100% !important;
        table-layout:auto !important;
        box-sizing:border-box !important;
      }
      /* Критично: tbody должен расширяться, а не иметь фиксированную ширину - максимальная специфичность */
      .woocommerce-cart-form table tbody,
      form.woocommerce-cart-form table tbody,
      .woocommerce table.cart tbody,
      .woocommerce.woocommerce-cart form.woocommerce-cart-form table tbody,
      .woocommerce.woocommerce-cart .woocommerce-cart-form table tbody,
      .woocommerce.woocommerce-cart .woocommerce-cart-form table.shop_table tbody,
      form.woocommerce-cart-form table.shop_table tbody,
      .woocommerce.woocommerce-cart form.woocommerce-cart-form table.shop_table.cart tbody,
      .woocommerce.woocommerce-cart .woocommerce-cart-form table.shop_table.cart.woocommerce-cart-form__contents tbody {
        overflow-x:visible !important;
        overflow-y:visible !important;
        overflow:visible !important;
        max-width:none !important;
        width:auto !important;
        min-width:100% !important;
        box-sizing:border-box !important;
        display:table-row-group !important;
      }
      .woocommerce-cart-form table tbody tr,
      form.woocommerce-cart-form table tbody tr,
      .woocommerce table.cart tbody tr {
        overflow-x:visible !important;
        overflow-y:visible !important;
        max-width:100% !important;
        width:100% !important;
        box-sizing:border-box !important;
      }
      .woocommerce-cart-form table tbody td,
      form.woocommerce-cart-form table tbody td,
      .woocommerce table.cart tbody td {
        overflow-x:visible !important;
        overflow-y:visible !important;
        max-width:100% !important;
        box-sizing:border-box !important;
      }
      /* Только для элементов внутри td.product-name корзины - максимальная специфичность */
      form.woocommerce-cart-form table tbody td.product-name,
      .woocommerce-cart form.woocommerce-cart-form table tbody td.product-name,
      .woocommerce.woocommerce-cart form.woocommerce-cart-form table tbody td.product-name,
      .woocommerce table.cart tbody td.product-name {
        overflow-x:visible !important;
        overflow-y:visible !important;
        overflow:visible !important;
        max-width:100% !important;
        width:100% !important;
        min-width:0 !important;
        box-sizing:border-box !important;
        word-wrap:break-word !important;
        overflow-wrap:break-word !important;
        word-break:break-word !important;
        white-space:normal !important;
        text-overflow:clip !important;
      }
      /* Специфичные элементы внутри product-name - максимальная специфичность */
      form.woocommerce-cart-form table tbody td.product-name .wmb-product-name-main,
      .woocommerce-cart form.woocommerce-cart-form table tbody td.product-name .wmb-product-name-main,
      form.woocommerce-cart-form table tbody td.product-name .wmb-product-details,
      .woocommerce-cart form.woocommerce-cart-form table tbody td.product-name .wmb-product-details,
      form.woocommerce-cart-form table tbody td.product-name .wmb-product-price-summary,
      .woocommerce-cart form.woocommerce-cart-form table tbody td.product-name .wmb-product-price-summary,
      form.woocommerce-cart-form table tbody td.product-name .wmb-cart-nutrition,
      .woocommerce-cart form.woocommerce-cart-form table tbody td.product-name .wmb-cart-nutrition,
      .woocommerce table.cart tbody td.product-name .wmb-product-name-main,
      .woocommerce table.cart tbody td.product-name .wmb-product-details,
      .woocommerce table.cart tbody td.product-name .wmb-product-price-summary,
      .woocommerce table.cart tbody td.product-name .wmb-cart-nutrition {
        overflow-x:visible !important;
        overflow-y:visible !important;
        overflow:visible !important;
        max-width:100% !important;
        width:100% !important;
        min-width:0 !important;
        box-sizing:border-box !important;
        word-wrap:break-word !important;
        overflow-wrap:break-word !important;
        word-break:break-word !important;
        white-space:normal !important;
        text-overflow:clip !important;
      }
      /* Скрываем колонку количества - аккуратно, чтобы не сломать верстку */
      .woocommerce-cart-form thead .product-quantity,
      .woocommerce table.cart thead .product-quantity,
      .woocommerce-checkout-review-order-table thead .product-quantity,
      .woocommerce-cart-form tbody .product-quantity,
      .woocommerce table.cart tbody .product-quantity,
      .woocommerce-checkout-review-order-table tbody .product-quantity {
        display:none !important;
        visibility:hidden !important;
        width:0 !important;
        min-width:0 !important;
        max-width:0 !important;
        padding:0 !important;
        margin:0 !important;
        border:none !important;
      }
      /* Скрываем колонки цены и подытога для товаров с wmb_payload */
      .woocommerce-cart-form thead .product-price,
      .woocommerce table.cart thead .product-price,
      .woocommerce-checkout-review-order-table thead .product-price,
      .woocommerce-cart-form tbody .product-price,
      .woocommerce table.cart tbody .product-price,
      .woocommerce-checkout-review-order-table tbody .product-price,
      .woocommerce-cart-form thead .product-subtotal,
      .woocommerce table.cart thead .product-subtotal,
      .woocommerce-checkout-review-order-table thead .product-subtotal,
      .woocommerce-cart-form tbody .product-subtotal,
      .woocommerce table.cart tbody .product-subtotal,
      .woocommerce-checkout-review-order-table tbody .product-subtotal {
        display:none !important;
        visibility:hidden !important;
        width:0 !important;
        min-width:0 !important;
        max-width:0 !important;
        padding:0 !important;
        margin:0 !important;
        border:none !important;
      }
      /* Расширяем колонку товара максимально - более агрессивно */
      .woocommerce-cart-form thead .product-name,
      .woocommerce table.cart thead .product-name,
      .woocommerce-checkout-review-order-table thead .product-name,
      .woocommerce-cart-form tbody .product-name,
      .woocommerce table.cart tbody .product-name,
      .woocommerce-checkout-review-order-table tbody .product-name {
        width:auto !important;
        min-width:65% !important;
        max-width:none !important;
        overflow:visible !important;
        overflow-x:visible !important;
        overflow-y:visible !important;
        word-wrap:break-word !important;
        overflow-wrap:break-word !important;
        word-break:break-word !important;
        white-space:normal !important;
      }
      /* Уменьшаем отступы в деталях товара - более агрессивно */
      .woocommerce-cart-form .product-name .wmb-product-details,
      .woocommerce table.cart .product-name .wmb-product-details,
      .woocommerce-checkout-review-order-table .product-name .wmb-product-details {
        margin-top:0.4rem !important;
        margin-bottom:0 !important;
        line-height:1.4 !important;
        width:100% !important;
        max-width:100% !important;
        overflow:visible !important;
        overflow-x:visible !important;
        overflow-y:visible !important;
        word-wrap:break-word !important;
        overflow-wrap:break-word !important;
        word-break:break-word !important;
        white-space:normal !important;
        box-sizing:border-box !important;
        display:block !important;
      }
      /* Только для наших элементов внутри wmb-product-details - БЕЗ универсального селектора */
      .woocommerce-cart-form .product-name .wmb-product-details br,
      .woocommerce table.cart .product-name .wmb-product-details br,
      .woocommerce-checkout-review-order-table .product-name .wmb-product-details br {
        margin:0.15em 0 !important;
        line-height:1.4 !important;
        display:block !important;
      }
      /* Для текстовых элементов после br */
      .woocommerce-cart-form .product-name .wmb-product-details br + span,
      .woocommerce table.cart .product-name .wmb-product-details br + span,
      .woocommerce-checkout-review-order-table .product-name .wmb-product-details br + span {
        margin:0.15em 0 !important;
        line-height:1.4 !important;
        width:100% !important;
        max-width:100% !important;
        overflow:visible !important;
        word-wrap:break-word !important;
        overflow-wrap:break-word !important;
        word-break:break-word !important;
        white-space:normal !important;
        box-sizing:border-box !important;
        display:block !important;
      }
      .woocommerce-cart-form .product-name .wmb-product-name-main,
      .woocommerce table.cart .product-name .wmb-product-name-main,
      .woocommerce-checkout-review-order-table .product-name .wmb-product-name-main {
        margin-bottom:0.3rem !important;
        line-height:1.3 !important;
      }
      /* Уменьшаем отступы в абзацах внутри товара */
      .woocommerce-cart-form .product-name p,
      .woocommerce table.cart .product-name p,
      .woocommerce-checkout-review-order-table .product-name p {
        margin:0.2em 0 !important;
        line-height:1.4 !important;
      }
      /* Убираем универсальный селектор - не нужен */
      /* Уменьшаем колонки Цена и Подытог */
      .woocommerce-cart-form thead .product-price,
      .woocommerce table.cart thead .product-price,
      .woocommerce-checkout-review-order-table thead .product-price,
      .woocommerce-cart-form tbody .product-price,
      .woocommerce table.cart tbody .product-price,
      .woocommerce-checkout-review-order-table tbody .product-price {
        width:auto !important;
        min-width:12% !important;
        max-width:18% !important;
        text-align:right !important;
      }
      .woocommerce-cart-form thead .product-subtotal,
      .woocommerce table.cart thead .product-subtotal,
      .woocommerce-checkout-review-order-table thead .product-subtotal,
      .woocommerce-cart-form tbody .product-subtotal,
      .woocommerce table.cart tbody .product-subtotal,
      .woocommerce-checkout-review-order-table tbody .product-subtotal {
        width:auto !important;
        min-width:12% !important;
        max-width:18% !important;
        text-align:right !important;
      }
      /* На мобильных устройствах - улучшаем отображение */
      @media (max-width:768px) {
        .woocommerce-cart-form .product-quantity,
        .woocommerce table.cart .product-quantity,
        .woocommerce-checkout-review-order-table .product-quantity {
          display:none !important;
        }
        /* На мобилке таблица становится блочной, исправляем overflow и отступы */
        .woocommerce-cart .woocommerce-cart-form,
        form.woocommerce-cart-form {
          overflow-x:visible !important;
          overflow-y:visible !important;
          max-width:100% !important;
          width:100% !important;
        }
        .woocommerce-cart .woocommerce-cart-form table,
        form.woocommerce-cart-form table {
          overflow-x:visible !important;
          overflow-y:visible !important;
          max-width:100% !important;
          width:100% !important;
        }
        /* Критично: исправляем flex-контейнеры на мобилке */
        .woocommerce-cart .shop_table tbody td {
          flex-shrink:1 !important;
          flex-grow:1 !important;
          flex-basis:auto !important;
          min-width:0 !important;
          max-width:100% !important;
          overflow-x:visible !important;
          overflow-y:visible !important;
        }
        .woocommerce-cart .shop_table tbody td.product-name {
          display:block !important;
          width:100% !important;
          max-width:100% !important;
          min-width:0 !important;
          padding:0.5rem 0 !important;
          margin-bottom:0.4rem !important;
          overflow:visible !important;
          overflow-x:visible !important;
          overflow-y:visible !important;
          word-wrap:break-word !important;
          overflow-wrap:break-word !important;
          word-break:break-word !important;
          white-space:normal !important;
          box-sizing:border-box !important;
          flex-direction:column !important;
          align-items:flex-start !important;
          justify-content:flex-start !important;
          flex-shrink:1 !important;
          flex-grow:1 !important;
          flex-basis:auto !important;
          text-overflow:clip !important;
        }
        /* Убираем ::before для product-name, чтобы не было меток */
        .woocommerce-cart .shop_table tbody td.product-name::before {
          display:none !important;
          content:none !important;
        }
        .woocommerce-cart .shop_table .product-name .wmb-product-name-main {
          margin-bottom:0.3rem !important;
          font-size:0.95rem !important;
          line-height:1.3 !important;
          width:100% !important;
          max-width:100% !important;
          overflow:visible !important;
          word-wrap:break-word !important;
          overflow-wrap:break-word !important;
        }
        .woocommerce-cart .shop_table .product-name .wmb-product-details {
          margin-top:0.3rem !important;
          margin-bottom:0 !important;
          line-height:1.4 !important;
          width:100% !important;
          max-width:100% !important;
          overflow:visible !important;
          word-wrap:break-word !important;
          overflow-wrap:break-word !important;
        }
        /* Убираем универсальный селектор - используем специфичные селекторы */
        .woocommerce-cart .shop_table .product-name .wmb-product-details br,
        .woocommerce-cart .shop_table .product-name .wmb-product-details br + span {
          margin:0.15em 0 !important;
          line-height:1.4 !important;
          width:100% !important;
          max-width:100% !important;
          overflow:visible !important;
          word-wrap:break-word !important;
          overflow-wrap:break-word !important;
          display:block !important;
        }
        .woocommerce-cart .shop_table .product-name .wmb-cart-nutrition {
          margin-top:0.15rem !important;
          margin-bottom:0 !important;
          font-size:11px !important;
          line-height:1.3 !important;
          width:100% !important;
          max-width:100% !important;
          overflow:visible !important;
          word-wrap:break-word !important;
          overflow-wrap:break-word !important;
        }
        /* Убираем ограничения ширины только для наших элементов внутри product-name - БЕЗ универсального селектора */
        .woocommerce-cart .shop_table .product-name .wmb-product-name-main,
        .woocommerce-cart .shop_table .product-name .wmb-product-details,
        .woocommerce-cart .shop_table .product-name .wmb-product-price-summary,
        .woocommerce-cart .shop_table .product-name .wmb-cart-nutrition {
          max-width:100% !important;
          overflow:visible !important;
          word-wrap:break-word !important;
          overflow-wrap:break-word !important;
          box-sizing:border-box !important;
        }
        /* Исправляем обрезку текста на мобилке - только для элементов корзины */
        .woocommerce-cart .shop_table,
        .woocommerce-cart .woocommerce-cart-form,
        form.woocommerce-cart-form {
          overflow-x:visible !important;
          overflow-y:visible !important;
          max-width:100% !important;
          width:100% !important;
          box-sizing:border-box !important;
        }
        /* Критично: tbody на мобилке должен расширяться */
        .woocommerce-cart .woocommerce-cart-form table,
        form.woocommerce-cart-form table {
          overflow-x:visible !important;
          overflow-y:visible !important;
          max-width:none !important;
          width:100% !important;
          min-width:100% !important;
          table-layout:auto !important;
        }
        .woocommerce-cart .woocommerce-cart-form table tbody,
        form.woocommerce-cart-form table tbody,
        .woocommerce.woocommerce-cart form.woocommerce-cart-form table tbody {
          overflow-x:visible !important;
          overflow-y:visible !important;
          max-width:none !important;
          width:auto !important;
          min-width:100% !important;
          display:table-row-group !important;
        }
        .woocommerce-cart .woocommerce-cart-form table tbody tr,
        form.woocommerce-cart-form table tbody tr {
          overflow-x:visible !important;
          overflow-y:visible !important;
          max-width:100% !important;
          width:100% !important;
        }
        /* Только для td в корзине */
        .woocommerce-cart .shop_table tbody td,
        .woocommerce-cart .woocommerce-cart-form table tbody td,
        form.woocommerce-cart-form table tbody td {
          overflow-x:visible !important;
          overflow-y:visible !important;
          max-width:100% !important;
          box-sizing:border-box !important;
        }
        /* Убираем все ограничения padding для мобильных */
        .woocommerce-cart .shop_table tbody td {
          padding-left:0.5rem !important;
          padding-right:0.5rem !important;
        }
        .woocommerce-cart .shop_table tbody td {
          overflow:visible !important;
          overflow-x:visible !important;
          overflow-y:visible !important;
          word-wrap:break-word !important;
          overflow-wrap:break-word !important;
          word-break:break-word !important;
          hyphens:auto !important;
          max-width:100% !important;
          width:auto !important;
          box-sizing:border-box !important;
        }
        .woocommerce-cart .shop_table .product-name,
        .woocommerce-cart .shop_table .wmb-product-details,
        .woocommerce-cart .shop_table .wmb-product-price-summary {
          overflow:visible !important;
          overflow-x:visible !important;
          overflow-y:visible !important;
          word-wrap:break-word !important;
          overflow-wrap:break-word !important;
          word-break:break-word !important;
          hyphens:auto !important;
          max-width:100% !important;
          width:100% !important;
          box-sizing:border-box !important;
          white-space:normal !important;
          text-overflow:clip !important;
          min-width:0 !important;
        }
        /* Только для наших элементов внутри - БЕЗ универсального селектора */
        .woocommerce-cart .shop_table .wmb-product-name-main,
        .woocommerce-cart .shop_table .wmb-product-details > br,
        .woocommerce-cart .shop_table .wmb-cart-nutrition,
        .woocommerce-cart .shop_table .wmb-price-label,
        .woocommerce-cart .shop_table .wmb-price-value,
        .woocommerce-cart .shop_table .wmb-subtotal-label,
        .woocommerce-cart .shop_table .wmb-subtotal-value {
          overflow:visible !important;
          overflow-x:visible !important;
          overflow-y:visible !important;
          word-wrap:break-word !important;
          overflow-wrap:break-word !important;
          word-break:break-word !important;
          hyphens:auto !important;
          max-width:100% !important;
          box-sizing:border-box !important;
          white-space:normal !important;
        }
        /* Критично: для всех текстовых элементов убираем обрезку на мобилке */
        .woocommerce-cart .shop_table .wmb-product-details {
          display:block !important;
          width:100% !important;
          max-width:100% !important;
          overflow:visible !important;
          word-wrap:break-word !important;
          overflow-wrap:break-word !important;
          word-break:break-word !important;
          white-space:normal !important;
          box-sizing:border-box !important;
        }
        /* Убираем универсальный селектор - используем специфичные селекторы */
        .woocommerce-cart .shop_table .wmb-product-details br,
        .woocommerce-cart .shop_table .wmb-product-details br + span {
          display:block !important;
          width:100% !important;
          max-width:100% !important;
          overflow:visible !important;
          word-wrap:break-word !important;
          overflow-wrap:break-word !important;
          word-break:break-word !important;
          white-space:normal !important;
          box-sizing:border-box !important;
        }
        .woocommerce-cart .shop_table .wmb-product-price-summary {
          display:block !important;
          width:100% !important;
          max-width:100% !important;
          overflow:visible !important;
          word-wrap:break-word !important;
          overflow-wrap:break-word !important;
          word-break:break-word !important;
          white-space:normal !important;
          box-sizing:border-box !important;
        }
        .woocommerce-cart .shop_table .wmb-cart-nutrition,
        .woocommerce-cart .shop_table .wmb-price-label,
        .woocommerce-cart .shop_table .wmb-price-value,
        .woocommerce-cart .shop_table .wmb-subtotal-label,
        .woocommerce-cart .shop_table .wmb-subtotal-value {
          display:inline !important;
          white-space:normal !important;
          word-wrap:break-word !important;
          overflow-wrap:break-word !important;
          word-break:break-word !important;
          overflow:visible !important;
          max-width:none !important;
        }
        /* Критично: убираем все ограничения для текста */
        .woocommerce-cart .shop_table .wmb-product-details,
        .woocommerce-cart .shop_table .wmb-cart-nutrition,
        .woocommerce-cart .shop_table .wmb-price-label,
        .woocommerce-cart .shop_table .wmb-price-value,
        .woocommerce-cart .shop_table .wmb-subtotal-label,
        .woocommerce-cart .shop_table .wmb-subtotal-value {
          display:inline-block !important;
          max-width:100% !important;
          width:auto !important;
          overflow:visible !important;
          word-wrap:break-word !important;
          overflow-wrap:break-word !important;
          word-break:break-word !important;
          white-space:normal !important;
        }
        /* Улучшаем отображение цены и подытога на мобилке */
        .woocommerce-cart .shop_table tbody td.product-price,
        .woocommerce-cart .shop_table tbody td.product-subtotal {
          font-size:0.95rem !important;
          padding:0.4rem 0 !important;
          flex-wrap:wrap !important;
          word-wrap:break-word !important;
          overflow-wrap:break-word !important;
        }
      }
      /* Стили для КБЖУ в корзине - более агрессивно */
      .woocommerce-cart-form .wmb-cart-nutrition,
      .woocommerce table.cart .wmb-cart-nutrition,
      .woocommerce-checkout-review-order-table .wmb-cart-nutrition,
      .wmb-cart-nutrition {
        display:block !important;
        font-size:12px !important;
        color:#666 !important;
        margin-top:0.2rem !important;
        margin-bottom:0 !important;
        font-weight:400 !important;
        line-height:1.3 !important;
      }
      .woocommerce-cart-form .wmb-product-details,
      .woocommerce table.cart .wmb-product-details,
      .woocommerce-checkout-review-order-table .wmb-product-details,
      .wmb-product-details {
        margin-top:0.4rem !important;
        margin-bottom:0 !important;
        line-height:1.4 !important;
      }
      .woocommerce-cart-form .wmb-product-details .wmb-cart-nutrition,
      .woocommerce table.cart .wmb-product-details .wmb-cart-nutrition,
      .woocommerce-checkout-review-order-table .wmb-product-details .wmb-cart-nutrition,
      .wmb-product-details .wmb-cart-nutrition {
        margin-top:0.15rem !important;
        margin-bottom:0 !important;
      }
      /* Стили для блока с ценой и подытогом в деталях товара */
      .woocommerce-cart-form .wmb-product-price-summary,
      .woocommerce table.cart .wmb-product-price-summary,
      .woocommerce-checkout-review-order-table .wmb-product-price-summary {
        margin-top:0.5rem !important;
        padding-top:0.5rem !important;
        border-top:1px solid #f0f0f0 !important;
        font-size:0.9rem !important;
        line-height:1.5 !important;
        width:100% !important;
        max-width:100% !important;
        overflow:visible !important;
        word-wrap:break-word !important;
        overflow-wrap:break-word !important;
        box-sizing:border-box !important;
      }
      .woocommerce-cart-form .wmb-product-price-summary .wmb-price-label,
      .woocommerce table.cart .wmb-product-price-summary .wmb-price-label,
      .woocommerce-checkout-review-order-table .wmb-product-price-summary .wmb-price-label,
      .woocommerce-cart-form .wmb-product-price-summary .wmb-subtotal-label,
      .woocommerce table.cart .wmb-product-price-summary .wmb-subtotal-label,
      .woocommerce-checkout-review-order-table .wmb-product-price-summary .wmb-subtotal-label {
        color:#666 !important;
        margin-right:0.5rem !important;
        display:inline !important;
        white-space:normal !important;
        word-wrap:break-word !important;
        overflow-wrap:break-word !important;
      }
      .woocommerce-cart-form .wmb-product-price-summary .wmb-price-value,
      .woocommerce table.cart .wmb-product-price-summary .wmb-price-value,
      .woocommerce-checkout-review-order-table .wmb-product-price-summary .wmb-price-value {
        font-weight:500 !important;
        color:#333 !important;
        display:inline !important;
        white-space:normal !important;
        word-wrap:break-word !important;
        overflow-wrap:break-word !important;
      }
      .woocommerce-cart-form .wmb-product-price-summary .wmb-subtotal-value,
      .woocommerce table.cart .wmb-product-price-summary .wmb-subtotal-value,
      .woocommerce-checkout-review-order-table .wmb-product-price-summary .wmb-subtotal-value {
        font-weight:600 !important;
        color:#4caf50 !important;
        display:inline !important;
        white-space:normal !important;
        word-wrap:break-word !important;
        overflow-wrap:break-word !important;
      }
      /* Критично: убираем обрезку для всех элементов - правильный подход */
      .woocommerce-cart-form .wmb-product-details,
      .woocommerce table.cart .wmb-product-details {
        display:block !important;
        width:100% !important;
        max-width:100% !important;
        overflow:visible !important;
        overflow-x:visible !important;
        overflow-y:visible !important;
        word-wrap:break-word !important;
        overflow-wrap:break-word !important;
        word-break:break-word !important;
        white-space:normal !important;
        box-sizing:border-box !important;
      }
      /* Для строк внутри wmb-product-details */
      /* Убираем универсальный селектор - используем специфичные селекторы */
      .woocommerce-cart-form .wmb-product-details br,
      .woocommerce table.cart .wmb-product-details br,
      .woocommerce-cart-form .wmb-product-details br + span,
      .woocommerce table.cart .wmb-product-details br + span {
        display:block !important;
        width:100% !important;
        max-width:100% !important;
        overflow:visible !important;
        word-wrap:break-word !important;
        overflow-wrap:break-word !important;
        word-break:break-word !important;
        white-space:normal !important;
        box-sizing:border-box !important;
      }
      /* Для блока с ценой и подытогом */
      .woocommerce-cart-form .wmb-product-price-summary,
      .woocommerce table.cart .wmb-product-price-summary {
        display:block !important;
        width:100% !important;
        max-width:100% !important;
        overflow:visible !important;
        word-wrap:break-word !important;
        overflow-wrap:break-word !important;
        word-break:break-word !important;
        white-space:normal !important;
        box-sizing:border-box !important;
      }
      /* Для inline элементов внутри блока с ценой - оставляем inline */
      .woocommerce-cart-form .wmb-product-price-summary .wmb-price-label,
      .woocommerce table.cart .wmb-product-price-summary .wmb-price-label,
      .woocommerce-cart-form .wmb-product-price-summary .wmb-price-value,
      .woocommerce table.cart .wmb-product-price-summary .wmb-price-value,
      .woocommerce-cart-form .wmb-product-price-summary .wmb-subtotal-label,
      .woocommerce table.cart .wmb-product-price-summary .wmb-subtotal-label,
      .woocommerce-cart-form .wmb-product-price-summary .wmb-subtotal-value,
      .woocommerce table.cart .wmb-product-price-summary .wmb-subtotal-value {
        display:inline !important;
        white-space:normal !important;
        word-wrap:break-word !important;
        overflow-wrap:break-word !important;
        word-break:break-word !important;
        overflow:visible !important;
        max-width:none !important;
      }
      /* Стили для общего КБЖУ - внутри таблицы */
      .wmb-total-nutrition-row {
        border-top:2px solid #e5e5e5 !important;
      }
      .wmb-total-nutrition-cell {
        padding:12px 0 !important;
        border:none !important;
      }
      .wmb-total-nutrition-summary {
        margin:0 !important;
        padding:12px 16px;
        background:#f8f9fa;
        border-radius:8px;
        border-left:4px solid #4caf50;
        font-size:15px;
        line-height:1.5;
        width:100%;
        box-sizing:border-box;
      }
      .wmb-total-nutrition-summary strong {
        color:#2d5a3d;
        font-weight:600;
        margin-right:8px;
      }
      .wmb-total-nutrition-value {
        color:#333;
        font-weight:500;
      }
      /* На мобилке общее КБЖУ */
      @media (max-width:768px) {
        .wmb-total-nutrition-cell {
          display:block !important;
          width:100% !important;
          padding:0.75rem 0 !important;
        }
        .wmb-total-nutrition-cell::before {
          display:none !important;
        }
        .wmb-total-nutrition-summary {
          margin:0 !important;
          padding:10px 12px !important;
          font-size:14px !important;
        }
      }
    ';
    wp_register_style('wmb-cart-fix', false);
    wp_enqueue_style('wmb-cart-fix');
    wp_add_inline_style('wmb-cart-fix', $css);
    // Также добавляем стили через wp_head для максимального приоритета
    add_action('wp_head', function() use ($css) {
      echo '<style id="wmb-cart-fix-head">' . $css . '</style>';
    }, 999);
    // И еще раз через wp_footer для гарантии
    add_action('wp_footer', function() use ($css) {
      if (function_exists('is_cart') && is_cart()) {
        echo '<style id="wmb-cart-fix-footer">' . $css . '</style>';
        // JavaScript для принудительного изменения ширины tbody
        echo '<script>
        (function() {
          function fixTbodyWidth() {
            var tbody = document.querySelector("form.woocommerce-cart-form table tbody, .woocommerce-cart-form table tbody");
            if (tbody) {
              tbody.style.width = "auto";
              tbody.style.maxWidth = "none";
              tbody.style.minWidth = "100%";
            }
          }
          // Выполняем сразу
          fixTbodyWidth();
          // И после загрузки DOM
          if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", fixTbodyWidth);
          } else {
            fixTbodyWidth();
          }
          // И после полной загрузки
          window.addEventListener("load", fixTbodyWidth);
          // И при изменении размера окна
          window.addEventListener("resize", fixTbodyWidth);
        })();
        </script>';
      }
    }, 999);
  }
}, 999);
?>

