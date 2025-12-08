<?php
/**
 * Title: Кастомный заказ
 * Slug: gustolocal/custom-page
 * Categories: featured
 * Block Types: core/post-content
 * Description: Страница для заказа кастомного тестового набора
 */
?>
<!-- wp:group {"className":"gl-custom-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group gl-custom-section">
	<!-- wp:heading {"level":1} -->
	<h1>Попробуйте тестовый набор за спеццену</h1>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"className":"gl-lead"} -->
	<p class="gl-lead">Закажите пробный сет на 2–3 дня: мы привезём, вы спокойно попробуете вкус и формат. Если зайдёт — соберём персональное меню на неделю 💚</p>
	<!-- /wp:paragraph -->

	<!-- wp:html -->
	<div class="gl-badges" style="margin:1rem 0 2rem">
		<span class="gl-badge">готовим &lt; 24 часов до доставки</span>
		<span class="gl-badge">честный состав и порции</span>
		<span class="gl-badge">нал/карта/переводы — всё по закону</span>
		<span class="gl-badge">учтём диеты, вкусы и аллергии</span>
	</div>
	<!-- /wp:html -->

	<!-- wp:group {"className":"gl-panel","layout":{"type":"constrained"}} -->
	<div class="wp-block-group gl-panel">
		<!-- wp:html -->
		<div class="gl-form-grid">
			<div class="gl-form-group">
				<label><strong>Сколько вас дома?</strong></label>
				[text* family-size placeholder "например: 2 взрослых и ребёнок"]
			</div>

			<div class="gl-form-group">
				<label><strong>Где вы живёте?</strong></label>
				[text* user-district placeholder "район или улица — чтобы уточнить доставку"]
			</div>

			<div class="gl-form-group">
				<label><strong>Как с вами связаться?</strong></label>
				[text* user-contact placeholder "@telegram / WhatsApp / телефон"]
			</div>

			<div class="gl-form-group">
				<label><strong>Удобный день доставки</strong></label>
				[select* delivery-day "Ближайший вторник" "Ближайший четверг" "Договоримся индивидуально"]
			</div>

			<div class="gl-form-group">
				<label><strong>Время</strong></label>
				[radio* delivery-slot use_label_element "День (12:00–16:00)" "Вечер (18:00–21:00)" "Гибко — уточним"]
			</div>

			<div class="gl-form-group">
				<label><strong>Пожелания и особенности</strong></label>
				[textarea user-message placeholder "аллергии, любимые продукты, диета — всё что важно"]
			</div>

			<div class="gl-form-group">
				<label><strong>Хочу продолжать после теста</strong></label>
				[checkbox opt-continue "Да, интересно еженедельное меню"]
			</div>
		</div>
		<!-- /wp:html -->

		<!-- wp:html -->
		<div class="gl-form-actions">
			[submit class:gl-button class:gl-button--primary "Хочу попробовать!"]
		</div>
		<!-- /wp:html -->

		<!-- wp:paragraph {"style":{"color":{"text":"var(--gl-color-text-muted)"},"typography":{"fontSize":"0.875rem"}}} -->
		<p style="color:var(--gl-color-text-muted);font-size:0.875rem">Мы свяжемся в ближайшее время и предложим наполнение под ваш состав семьи.</p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:paragraph {"style":{"color":{"text":"var(--gl-color-text-muted)"},"typography":{"fontSize":"0.875rem"}}} -->
	<p style="color:var(--gl-color-text-muted);font-size:0.875rem">Все поля с <em>*</em> обязательны. Отправляя форму, вы соглашаетесь на обработку данных для связи по заказу.</p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

