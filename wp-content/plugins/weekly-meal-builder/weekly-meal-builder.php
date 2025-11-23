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
  wp_nonce_field('wmb_save_meta','wmb_meta_nonce');
  echo '<p><label>Цена (€)<br><input name="wmb_price" type="number" step="0.01" value="'.esc_attr($price).'" style="width:100%"></label></p>';
  echo '<p><label>Единица (текст)<br><input name="wmb_unit" type="text" value="'.esc_attr($unit).'" placeholder="200 г 2 порции" style="width:100%"></label></p>';
  echo '<p><label>Состав (текст)<br><textarea name="wmb_ingredients" rows="3" style="width:100%">'.esc_textarea($ing).'</textarea></label></p>';
  echo '<p><label>Аллергены (через запятую)<br><input name="wmb_allergens" type="text" value="'.esc_attr($alrg).'" placeholder="глютен, молоко, яйца" style="width:100%"></label></p>';
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
        $active= !empty($row['active']) ? '1' : '0';
        update_post_meta($id,'wmb_price',$price);
        update_post_meta($id,'wmb_unit',$unit);
        update_post_meta($id,'wmb_ingredients',$ing);
        update_post_meta($id,'wmb_allergens',$alrg);
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
      $active= !empty($q['active']) ? '1' : '0';
      $id = wp_insert_post(['post_type'=>'wmb_dish','post_status'=>'publish','post_title'=>$title]);
      if (!is_wp_error($id)){
        update_post_meta($id,'wmb_price',$price);
        update_post_meta($id,'wmb_unit',$unit);
        update_post_meta($id,'wmb_ingredients',$ing);
        update_post_meta($id,'wmb_allergens',$alrg);
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
    .wmb-admin .quick-grid{display:grid;grid-template-columns:1.5fr .6fr 1fr 1fr 1.5fr 1.2fr 1.4fr .9fr .6fr;gap:8px;align-items:center;margin:8px 0 14px}
    .wmb-admin .quick-grid input[type=text]{width:100%}
    .wmb-admin table.widefat input[type=text]{width:100%}
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
      $rows = array_map('str_getcsv', preg_split('/\r\n|\r|\n/',$csv));
      $header = array_map('trim', array_shift($rows));
      $map = array_flip($header);

      $updated=0; $created=0; $skipped=0;
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
        $ing    = isset($map['Состав'])     ? trim((string)($r[$map['Состав']]??''))     : '';
        $alrg   = isset($map['Аллергены'])  ? trim((string)($r[$map['Аллергены']]??''))  : '';
        $active = (string)trim($r[$map['Активно']]??'')==='1' ? '1' : '0';

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
      $report = compact('updated','created','skipped');
    }
  }

  echo '<div class="wrap"><h1>Импорт CSV</h1>';
  echo '<p>Ожидаемые столбцы: <code>Название, Цена, Единица, Категория, Теги, Состав, Аллергены, Активно</code>. Кодировка UTF-8, разделитель — запятая.</p>';
  if ($report){
    echo '<div class="updated notice"><p>Создано: <strong>'.$report['created'].'</strong>, обновлено: <strong>'.$report['updated'].'</strong>, пропущено: <strong>'.$report['skipped'].'</strong>.</p></div>';
  }
  if ($errors){
    echo '<div class="error notice"><p>'.esc_html(implode(' ', $errors)).'</p></div>';
  }
  echo '<form method="post" enctype="multipart/form-data" style="max-width:900px">';
  wp_nonce_field('wmb_import','wmb_import_nonce');
  echo '<h2>Вариант 1 — Загрузка файла</h2><input type="file" name="wmb_file" accept=".csv">';
  echo '<h2>Вариант 2 — Вставить CSV текстом</h2><textarea name="wmb_text" rows="10" style="width:100%" placeholder="Название,Цена,Единица,Категория,Теги,Состав,Аллергены,Активно&#10;Борщ,6.5,500 мл,Суп,&quot;Для мам, Для детей&quot;,&quot;Бульон..., овощи...&quot;,&quot;глютен, молоко&quot;,1"></textarea>';
  echo '<p><button class="button button-primary">Импортировать</button></p>';
  echo '</form></div>';
}

/* ---------- Settings page ---------- */
function wmb_page_settings(){
  if (!current_user_can('manage_options')) return;

  $saved=false; $settings = wmb_get_settings();
  $delivery = $settings['delivery'];

  if (!empty($_POST['wmb_settings_nonce']) && wp_verify_nonce($_POST['wmb_settings_nonce'],'wmb_settings_save')){
    $delivery['tuesday']['enabled']  = !empty($_POST['wmb_del_tue_enabled']);
    $delivery['friday']['enabled']   = !empty($_POST['wmb_del_fri_enabled']);
    $delivery['tuesday']['deadline']['dow']  = intval($_POST['wmb_del_tue_dow'] ?? 0);
    $delivery['tuesday']['deadline']['time'] = sanitize_text_field($_POST['wmb_del_tue_time'] ?? '14:00');
    $delivery['friday']['deadline']['dow']   = intval($_POST['wmb_del_fri_dow'] ?? 3);
    $delivery['friday']['deadline']['time']  = sanitize_text_field($_POST['wmb_del_fri_time'] ?? '14:00');
    $delivery['timezone'] = sanitize_text_field($_POST['wmb_del_tz'] ?? $delivery['timezone']);
    $delivery['banner']   = sanitize_text_field($_POST['wmb_del_banner'] ?? $delivery['banner']);
    $blackout = trim((string)($_POST['wmb_del_blackout'] ?? ''));
    $delivery['blackout'] = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $blackout))));
    $settings['delivery'] = $delivery;
    update_option('wmb_menu_json', wp_json_encode($settings, JSON_UNESCAPED_UNICODE));
    $saved=true;
  }

  $dow_opts = [0=>'Воскресенье',1=>'Понедельник',2=>'Вторник',3=>'Среда',4=>'Четверг',5=>'Пятница',6=>'Суббота'];

  echo '<div class="wrap"><h1>Настройки конструктора</h1>';
  if ($saved) echo '<div class="updated notice"><p>Сохранено.</p></div>';
  echo '<form method="post" style="max-width:980px">';
  wp_nonce_field('wmb_settings_save','wmb_settings_nonce');

  echo '<h2 style="margin-top:24px">Доставка</h2>';
  echo '<table class="form-table" role="presentation"><tbody>';

  echo '<tr><th scope="row">Вторник</th><td>';
    echo '<label><input type="checkbox" name="wmb_del_tue_enabled" value="1" '.checked(!empty($delivery['tuesday']['enabled']),true,false).'> включено</label><br>';
    echo '<label>Дедлайн (день недели + время): ';
      echo '<select name="wmb_del_tue_dow" style="min-width:180px">';
      foreach($dow_opts as $i=>$lab){
        $sel = selected(intval($delivery['tuesday']['deadline']['dow']), $i, false);
        echo '<option value="'.$i.'" '.$sel.'>'.$lab.'</option>';
      }
      echo '</select> ';
      echo '<input type="time" name="wmb_del_tue_time" value="'.esc_attr($delivery['tuesday']['deadline']['time']).'">';
    echo '</label>';
  echo '</td></tr>';

  echo '<tr><th scope="row">Пятница</th><td>';
    echo '<label><input type="checkbox" name="wmb_del_fri_enabled" value="1" '.checked(!empty($delivery['friday']['enabled']),true,false).'> включено</label><br>';
    echo '<label>Дедлайн (день недели + время): ';
      echo '<select name="wmb_del_fri_dow" style="min-width:180px">';
      foreach($dow_opts as $i=>$lab){
        $sel = selected(intval($delivery['friday']['deadline']['dow']), $i, false);
        echo '<option value="'.$i.'" '.$sel.'>'.$lab.'</option>';
      }
      echo '</select> ';
      echo '<input type="time" name="wmb_del_fri_time" value="'.esc_attr($delivery['friday']['deadline']['time']).'">';
    echo '</label>';
  echo '</td></tr>';

  echo '<tr><th scope="row"><label for="wmb_del_tz">Часовой пояс</label></th><td><input type="text" id="wmb_del_tz" name="wmb_del_tz" class="regular-text" value="'.esc_attr($delivery['timezone']).'"> <span class="description">По умолчанию берётся из настроек WordPress</span></td></tr>';
  echo '<tr><th scope="row"><label for="wmb_del_banner">Текст баннера</label></th><td><input type="text" id="wmb_del_banner" name="wmb_del_banner" class="regular-text" style="width:100%" value="'.esc_attr($delivery['banner']).'"><p class="description">Плейсхолдеры: {delivery_date}, {weekday}, {weekday_short}, {deadline}, {countdown}</p></td></tr>';
  echo '<tr><th scope="row"><label for="wmb_del_blackout">Нерабочие даты</label></th><td><textarea id="wmb_del_blackout" name="wmb_del_blackout" rows="4" class="large-text" placeholder="YYYY-MM-DD по одной на строку">'.esc_textarea(implode("\n",$delivery['blackout'])).'</textarea></td></tr>';

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
  register_rest_route('wmb/v1', '/menu', [
    'methods'             => 'GET',
    'permission_callback' => '__return_true',
    'callback'            => function () {
      $settings = wmb_get_settings();
      $posts = get_posts([
        'post_type'   => 'wmb_dish',
        'numberposts' => -1,
        'meta_key'    => 'wmb_active',
        'meta_value'  => '1',
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

        $sec_terms = wp_get_post_terms($p->ID, 'wmb_section', ['fields' => 'names']);
        $sec = $sec_terms ? $sec_terms[0] : 'Прочее';
        if (!isset($sections[$sec])) $sections[$sec] = [];

        $sections[$sec][] = [
          'id'          => 'dish-' . $p->ID,
          'name'        => get_the_title($p),
          'price'       => $price,
          'unit'        => $unit,
          'ingredients' => $ingredients,
          'allergens'   => $allergens,
          'tags'        => wp_get_post_terms($p->ID, 'wmb_tag', ['fields' => 'names']),
          'kcal'        => 0,
        ];
      }

      $out_sections = [];
      foreach ($sections as $title => $items) {
        // Используем отображаемое название категории, если функция доступна
        $display_title = function_exists('gustolocal_get_category_display_name') 
          ? gustolocal_get_category_display_name($title) 
          : $title;
        $out_sections[] = ['title' => $display_title, 'items' => $items];
      }

      // Используем настройки категорий из темы, если доступны
      if (function_exists('gustolocal_get_ordered_categories')) {
        $ordered_settings = gustolocal_get_ordered_categories();
        $order_map = [];
        foreach ($ordered_settings as $original => $config) {
          $display = !empty($config['display']) ? $config['display'] : $original;
          $order_map[mb_strtolower($display)] = isset($config['order']) ? (int)$config['order'] : 999;
        }
        
        usort($out_sections, function($a, $b) use ($order_map) {
          $a_title_lower = mb_strtolower($a['title']);
          $b_title_lower = mb_strtolower($b['title']);
          $ai = isset($order_map[$a_title_lower]) ? $order_map[$a_title_lower] : PHP_INT_MAX;
          $bi = isset($order_map[$b_title_lower]) ? $order_map[$b_title_lower] : PHP_INT_MAX;
          if ($ai === $bi) return strcmp($a['title'], $b['title']);
          return $ai - $bi;
        });
      } else {
        // Fallback на старый порядок, если функции темы недоступны
        $hard_order = [
          'Завтраки и сладкое',
          'Авторские сэндвичи и перекусы',
          'Паста ручной работы',
          'Основные блюда',
          'Гарниры и зелень',
          'Супы и крем-супы',
          'Для запаса / в морозильник',
        ];

        $index = array_map('mb_strtolower', $hard_order);
        usort($out_sections, function($a, $b) use ($index) {
          $ai = array_search(mb_strtolower($a['title']), $index);
          $bi = array_search(mb_strtolower($b['title']), $index);
          $ai = ($ai === false) ? PHP_INT_MAX : $ai;
          $bi = ($bi === false) ? PHP_INT_MAX : $bi;
          if ($ai === $bi) return strcmp($a['title'], $b['title']);
          return $ai - $bi;
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
    'menu_url'=>$menu_url,
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
      $kcal=0; 
    }
    else { $name=$id; $price=0; $unit=''; $kcal=0; }
    $total_price += $price*$qty;
    $items_list[] = ['id'=>$id,'name'=>$name,'unit'=>$unit,'kcal'=>$kcal,'price'=>$price,'qty'=>$qty];
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
// Улучшенное отображение деталей заказа в корзине и на checkout
add_filter('woocommerce_cart_item_name', 'wmb_display_cart_item_details', 10, 3);
function wmb_display_cart_item_details($name, $cart_item, $cart_item_key) {
  if (isset($cart_item['wmb_payload'])) {
    $payload = json_decode($cart_item['wmb_payload'], true);
    if ($payload && isset($payload['items_list']) && is_array($payload['items_list'])) {
      $details = [];
      foreach ($payload['items_list'] as $item) {
        if (isset($item['qty']) && $item['qty'] > 0) {
          $item_name = isset($item['name']) ? esc_html($item['name']) : 'Неизвестное блюдо';
          $item_unit = isset($item['unit']) ? esc_html($item['unit']) : '';
          $item_price = isset($item['price']) ? floatval($item['price']) : 0;
          $item_qty = intval($item['qty']);
          $total_price = $item_price * $item_qty;
          
          // Форматируем дочерние товары: "Название (единица) × количество — цена"
          $unit_display = $item_unit ? ' (' . $item_unit . ')' : '';
          // Используем формат WooCommerce для цены (учитывает настройки валюты)
          $formatted_price = function_exists('wc_price') ? strip_tags(wc_price($total_price)) : number_format($total_price, 2, ',', '') . ' €';
          $details[] = $item_name . $unit_display . ' × ' . $item_qty . ' — ' . $formatted_price;
        }
      }
      if (!empty($details)) {
        // Извлекаем только название товара (без количества и цены, если они были добавлены)
        $clean_name = wp_strip_all_tags($name);
        // Удаляем возможные паттерны " × количество — цена" из конца названия
        $clean_name = preg_replace('/\s*×\s*\d+\s*—\s*[€$]?[\d,\.]+\s*$/', '', $clean_name);
        $clean_name = trim($clean_name);
        
        // Основной товар отображаем без количества и цены, только название
        // Дочерние товары отображаем в столбик под основным товаром
        $name = '<div class="wmb-product-name-main">' . esc_html($clean_name) . '</div>';
        $name .= '<div class="wmb-product-details">';
        $name .= implode('<br>', $details);
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
    $lines[] = sprintf('%s%s × %d — %s', $name, $unit_display, $qty, strip_tags(wc_price($subtotal)));
  }
  if (!$lines) return;
  if ($plain_text){
    echo "\n" . implode("\n", $lines) . "\n";
  } else {
    $html = implode('<br>', array_map('esc_html', $lines));
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
    $lines[] = sprintf('%s%s × %d — %s', $name, $unit_display, $qty, strip_tags(wc_price($subtotal)));
  }
  if (!$lines) return;
  $html = implode('<br>', array_map('esc_html', $lines));
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

// Global tweak for WooCommerce cart/checkout tables on mobile
add_action('wp_enqueue_scripts', function(){
  if (function_exists('is_cart') && (is_cart() || is_checkout())){
    $css = 'html,body{overflow-x:hidden} .woocommerce .cart, .woocommerce table.shop_table{table-layout:fixed; width:100%} .woocommerce table.shop_table td, .woocommerce table.shop_table th{word-wrap:break-word;white-space:normal}';
    wp_register_style('wmb-cart-fix', false);
    wp_enqueue_style('wmb-cart-fix');
    wp_add_inline_style('wmb-cart-fix', $css);
  }
}, 100);
?>

